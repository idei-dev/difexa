<?php

namespace App\UI\Components\Modals;

use App\Enums\PostType;
use App\Models\Post;
use App\Services\Device\DeviceService;
use App\Services\Units\UnitContextResolver;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class EditPostDialog
{
    public const DEFAULT_SUBMIT_ACTION = 'submit_save_post';

    public const DEFAULT_CANCEL_ACTION = 'close_modal';

    public const TAB_CONTENT = 'tab_content';

    public const TAB_SCHEDULE = 'tab_schedule';

    public const TAB_MEDIA = 'tab_media';

    public const TAB_DEVICES = 'tab_devices';

    private const DIALOG_WIDTH_PX = 720;

    private const DIALOG_PADDING_PX = 14;

    private const DIALOG_GAP_PX = 14;

    private const SECTION_GAP_PX = 12;

    private const BUTTONS_GAP_PX = 10;

    private const TEXTAREA_HEIGHT_PX = 120;

    private const DEFAULT_DURATION_SEC = '10';

    private const DEFAULT_END_DAYS = 7;

    private const MAX_UPLOAD_SIZE_MB = 50;

    private readonly DeviceService $deviceService;

    public function __construct(?DeviceService $deviceService = null)
    {
        /** @var DeviceService $resolvedService */
        $resolvedService = $deviceService ?? app(DeviceService::class);
        $this->deviceService = $resolvedService;
    }

    /**
     * Open the edit/create post modal dialog.
     */
    public static function open(
        string $submitAction = self::DEFAULT_SUBMIT_ACTION,
        string $cancelAction = self::DEFAULT_CANCEL_ACTION,
        ?Post $post = null,
        ?int $callerServiceId = null,
        ?int $unitId = null
    ): void {
        $dialog = new self;
        $format = $dialog->getUI($submitAction, $cancelAction, $post, $callerServiceId, $unitId);
        /** @var UIChangesCollector $uiChanges */
        $uiChanges = app(UIChangesCollector::class);
        $uiChanges->add($format);
    }

    /**
     * Build the UI format array for the dialog.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUI(
        string $submitAction = self::DEFAULT_SUBMIT_ACTION,
        string $cancelAction = self::DEFAULT_CANCEL_ACTION,
        ?Post $post = null,
        ?int $callerServiceId = null,
        ?int $unitId = null
    ): array {
        $container = $this->createDialogContainer($post);

        $tabsContainer = $this->createTabsContainer($post);
        $tabsContainer->add($this->buildContentSection($post), tab: self::TAB_CONTENT);
        $tabsContainer->add($this->buildScheduleSection($post), tab: self::TAB_SCHEDULE);
        $tabsContainer->add($this->buildMediaSection($post), tab: self::TAB_MEDIA);
        $tabsContainer->add($this->buildDevicesSection($post, $unitId), tab: self::TAB_DEVICES);

        $container->add($tabsContainer);
        $container->add($this->buildActionButtons($submitAction, $cancelAction));

        return $container->toJson();
    }

    /**
     * Create the root modal container and hidden identity fields.
     */
    private function createDialogContainer(?Post $post): Container
    {
        $container = UI::container('edit_post_dialog')
            ->parent('modal')
            ->shadow(false)
            ->plain()
            ->width(Size::px(self::DIALOG_WIDTH_PX))
            ->padding(Spacing::px(self::DIALOG_PADDING_PX))
            ->gap(Spacing::px(self::DIALOG_GAP_PX));

        if ($post !== null) {
            $container->add(
                UI::input('post_id')
                    ->type('hidden')
                    ->value((string) $post->id)
            );
        }

        return $container;
    }

    /**
     * Create the tabbed navigation container.
     */
    private function createTabsContainer(?Post $post): Container
    {
        $activeTab = ($post !== null && $post->type->isMedia())
            ? self::TAB_MEDIA
            : self::TAB_CONTENT;

        return UI::container('post_edit_tabs')
            ->tabs(
                [
                    self::TAB_CONTENT => ['label' => '📝 Contenido'],
                    self::TAB_SCHEDULE => ['label' => '📅 Programación'],
                    self::TAB_MEDIA => ['label' => '📎 Multimedia'],
                    self::TAB_DEVICES => ['label' => '📺 Dispositivos Smart TV'],
                ],
                $activeTab
            )
            ->width(Size::full())
            ->plain()
            ->gap(Spacing::px(self::SECTION_GAP_PX));
    }

    /**
     * Build Tab 1: Content fields.
     */
    private function buildContentSection(?Post $post): Container
    {
        $contentSection = UI::container('section_content')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(self::SECTION_GAP_PX))
            ->plain();

        $contentSection->add(
            UI::input('post_title')
                ->label('Título de la Actividad / Noticia')
                ->placeholder('Ej: Seminario Internacional de Biología Cuántica')
                ->value($post !== null ? $post->title : '')
                ->required(true)
        );

        $contentSection->add(
            UI::select('post_type')
                ->label('Tipo de Contenido')
                ->options($this->buildPostTypeOptions())
                ->value($post !== null ? $post->type->value : PostType::TEXT->value)
        );

        $contentSection->add(
            (new Textarea('post_content'))
                ->label('Cuerpo del Mensaje / Detalle')
                ->placeholder('Descripción detallada para la difusión...')
                ->value($post !== null ? (string) ($post->content ?? '') : '')
                ->height(Size::px(self::TEXTAREA_HEIGHT_PX))
        );

        return $contentSection;
    }

    /**
     * Build Tab 2: Scheduling and display duration fields.
     */
    private function buildScheduleSection(?Post $post): Container
    {
        $scheduleSection = UI::container('section_schedule')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(self::SECTION_GAP_PX))
            ->plain();

        $scheduleSection->add(
            UI::label('lbl_schedule_instructions')
                ->text('📅 Define el período de vigencia en el que se difundirá la publicación y el tiempo de exposición en cada rotación del Kiosk:')
                ->fontSize('13px')
        );

        $scheduleSection->add($this->buildDatesRow($post));

        $scheduleSection->add(
            UI::input('post_display_duration_sec')
                ->label('Duración en Kiosk (segundos)')
                ->type('number')
                ->value($post !== null ? (string) $post->display_duration_sec : self::DEFAULT_DURATION_SEC)
        );

        return $scheduleSection;
    }

    /**
     * Build the start and end datetime inputs row.
     */
    private function buildDatesRow(?Post $post): Container
    {
        $datesContainer = UI::container('dates_container')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->gap(Spacing::px(self::SECTION_GAP_PX))
            ->plain();

        $defaultStart = $post !== null
            ? $post->starts_at->format('Y-m-d\TH:i')
            : now()->format('Y-m-d\TH:i');
        $defaultEnd = $post !== null
            ? $post->ends_at->format('Y-m-d\TH:i')
            : now()->addDays(self::DEFAULT_END_DAYS)->format('Y-m-d\TH:i');

        $datesContainer->add(
            UI::input('post_starts_at')
                ->label('Fecha y Hora de Inicio')
                ->type('datetime-local')
                ->value($defaultStart)
                ->required(true)
        );

        $datesContainer->add(
            UI::input('post_ends_at')
                ->label('Fecha y Hora de Finalización')
                ->type('datetime-local')
                ->value($defaultEnd)
                ->required(true)
        );

        return $datesContainer;
    }

    /**
     * Build Tab 2: Multimedia upload and external URL fields.
     */
    private function buildMediaSection(?Post $post): Container
    {
        $mediaSection = UI::container('section_media')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(self::SECTION_GAP_PX))
            ->plain();

        $mediaSection->add(
            UI::label('lbl_media_instructions')
                ->text('📸 / 🎥 Para publicaciones de tipo Imagen o Video, sube el archivo a difundir mediante el uploader a continuación (las imágenes deben tener proporción 16:9):')
                ->fontSize('13px')
        );

        $uploader = UI::uploader('post_uploader')
            ->label('Subir Archivo Multimedia (Imagen o Video - Proporción 16:9)')
            ->media()
            ->aspect('16:9')
            ->size(3)
            ->multiple(false)
            ->maxFiles(1)
            ->maxSize(self::MAX_UPLOAD_SIZE_MB);

        $hasExistingMedia = $post !== null && is_string($post->media_url) && $post->media_url !== '';

        if ($hasExistingMedia) {
            $uploader->existingFile((string) $post->media_url);
        }

        $mediaSection->add($uploader);

        $mediaSection->add(
            UI::input('post_media_url')
                ->label('URL Externa (Opcional si subiste archivo mediante el uploader)')
                ->placeholder('https://...')
                ->value($post !== null ? (string) ($post->media_url ?? '') : '')
        );

        if ($hasExistingMedia) {
            $mediaSection->add(
                UI::label('lbl_current_media')
                    ->text("📎 Archivo multimedia actual: {$post->media_url}")
                    ->fontSize('12px')
            );
        }

        return $mediaSection;
    }

    /**
     * Build Tab 3: Smart TV devices selection.
     */
    private function buildDevicesSection(?Post $post, ?int $unitId): Container
    {
        $devicesSection = UI::container('section_devices')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(self::SECTION_GAP_PX))
            ->plain();

        $devicesSection->add(
            UI::label('lbl_devices_instructions')
                ->text('📺 Selecciona los dispositivos Smart TV donde deseas proyectar esta publicación. Al seleccionar un dispositivo Institucional / Público, se habilitará automáticamente la difusión pública:')
                ->fontSize('13px')
        );

        $effectiveUnitId = $this->resolveEffectiveUnitId($post, $unitId);
        $deviceOptions = $this->buildDeviceOptions($effectiveUnitId);
        $selectedDeviceIds = $this->resolveSelectedDeviceIds($post);

        if ($deviceOptions !== []) {
            $devicesSection->add(
                UI::checkbox('post_devices')
                    ->label('📺 Dispositivos Smart TV de Difusión')
                    ->options($deviceOptions)
                    ->selectedValues($selectedDeviceIds)
                    ->vertical()
            );
        } else {
            $devicesSection->add(
                UI::label('lbl_no_devices')
                    ->text('ℹ️ No hay dispositivos Smart TV configurados en esta unidad o a nivel institucional.')
                    ->size('small')
            );
        }

        return $devicesSection;
    }

    /**
     * Build footer action buttons.
     */
    private function buildActionButtons(string $submitAction, string $cancelAction): Container
    {
        $buttonsContainer = UI::container('dialog_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->gap(Spacing::px(self::BUTTONS_GAP_PX))
            ->plain();

        $buttonsContainer->add(
            UI::button('btn_cancel')
                ->label('Cancelar')
                ->style('secondary')
                ->action($cancelAction)
        );

        $buttonsContainer->add(
            UI::button('btn_save_draft')
                ->label('Guardar Borrador')
                ->style('secondary')
                ->action($submitAction, ['submit_now' => false])
        );

        $buttonsContainer->add(
            UI::button('btn_submit_review')
                ->label('Enviar a Revisión')
                ->style('primary')
                ->action($submitAction, ['submit_now' => true])
        );

        return $buttonsContainer;
    }

    /**
     * Build options array for PostType select.
     *
     * @return list<array{value: string, label: string}>
     */
    private function buildPostTypeOptions(): array
    {
        $options = [];
        foreach (PostType::cases() as $case) {
            $options[] = [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }

        return $options;
    }

    /**
     * Resolve the effective unit ID for listing available Smart TV devices.
     */
    private function resolveEffectiveUnitId(?Post $post, ?int $unitId): ?int
    {
        $effectiveUnitId = $unitId ?? $post?->unit_id;
        if ($effectiveUnitId !== null) {
            return $effectiveUnitId;
        }

        $user = Auth::user();
        $requestStorage = request()->storage;
        $storageUnit = request()->input('storage.store_unit')
            ?? (is_array($requestStorage) ? ($requestStorage['store_unit'] ?? null) : null);

        if (is_string($storageUnit) && $storageUnit !== '') {
            $resolved = UnitContextResolver::resolve($user, $storageUnit);
            if ($resolved !== null) {
                return $resolved->id;
            }
        }

        if (function_exists('getPermissionsTeamId')) {
            $teamId = getPermissionsTeamId();
            if (is_int($teamId) || (is_string($teamId) && $teamId !== '')) {
                return (int) $teamId;
            }
        }

        return null;
    }

    /**
     * Build checkbox options for Smart TV devices of the resolved unit.
     *
     * @return list<array{value: string, label: string}>
     */
    private function buildDeviceOptions(?int $effectiveUnitId): array
    {
        $devices = $this->deviceService->getSmartTvDevicesForUnit($effectiveUnitId);

        $deviceOptions = [];
        foreach ($devices as $device) {
            $firstUnit = $device->usimUnits->first(
                static fn (UsimUnit $unit): bool => $unit->slug !== 'main'
            );
            $unitLabel = $this->resolveUnitLabel($firstUnit, $device->isPublic());

            $deviceOptions[] = [
                'value' => (string) $device->id,
                'label' => "📺 {$device->name} ({$unitLabel})",
            ];
        }

        return $deviceOptions;
    }

    /**
     * Resolve human-readable label for a unit avoiding untranslated key strings.
     */
    private function resolveUnitLabel(?UsimUnit $unit, bool $isPublic): string
    {
        if ($isPublic) {
            return 'Institucional / Público';
        }

        if ($unit === null) {
            return 'Público';
        }

        $displayName = $unit->display_name;

        return ($displayName !== '' && $displayName !== $unit->translation_key)
            ? $displayName
            : ucfirst($unit->slug);
    }

    /**
     * Extract selected device IDs as a string list for the checkbox component.
     *
     * @return list<string>
     */
    private function resolveSelectedDeviceIds(?Post $post): array
    {
        if ($post === null) {
            return [];
        }

        return array_values(
            $post->devices
                ->pluck('id')
                ->map(static fn (mixed $id): string => is_scalar($id) ? (string) $id : '')
                ->filter(static fn (string $id): bool => $id !== '')
                ->all()
        );
    }
}
