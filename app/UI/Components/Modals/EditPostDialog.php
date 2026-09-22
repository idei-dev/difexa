<?php

namespace App\UI\Components\Modals;

use App\Enums\PostType;
use App\Models\Post;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class EditPostDialog
{
    /**
     * Open the edit/create post modal dialog.
     */
    public static function open(
        string $submitAction = 'submit_save_post',
        string $cancelAction = 'close_modal',
        ?Post $post = null,
        ?int $callerServiceId = null
    ): void {
        $dialog = new self();
        $format = $dialog->getUI($submitAction, $cancelAction, $post, $callerServiceId);
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
        string $submitAction = 'submit_save_post',
        string $cancelAction = 'close_modal',
        ?Post $post = null,
        ?int $callerServiceId = null
    ): array {
        $isEditing = $post !== null;
        $titleText = $isEditing ? 'Editar Publicación' : 'Nueva Publicación';

        $container = UI::container('edit_post_dialog')
            ->parent('modal')
            ->shadow(false)
            ->plain()
            ->width(Size::px(720))
            ->padding(Spacing::px(14))
            ->gap(Spacing::px(14));

        $container->add(
            UI::label('dialog_title')
                ->text("📢 {$titleText}")
                ->fontSize('20px')
                ->bold()
        );

        if ($isEditing) {
            $container->add(
                UI::input('post_id')
                    ->type('hidden')
                    ->value((string) $post->id)
            );
        }

        $activeTab = ($isEditing && $post->type->isMedia()) ? 'tab_media' : 'tab_content';

        $tabsContainer = UI::container('post_edit_tabs')
            ->tabs(
                [
                    'tab_content' => ['label' => '📝 Contenido y Programación'],
                    'tab_media' => ['label' => '📎 Archivo Multimedia (Imagen / Video)'],
                ],
                $activeTab
            )
            ->width(Size::full())
            ->plain()
            ->gap(Spacing::px(12));

        // ================= TAB 1: CONTENIDO =================
        $contentSection = UI::container('section_content')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(12))
            ->plain();

        // Título del post
        $contentSection->add(
            UI::input('post_title')
                ->label('Título de la Actividad / Noticia')
                ->placeholder('Ej: Seminario Internacional de Biología Cuántica')
                ->value($post ? $post->title : '')
                ->required(true)
        );

        // Tipo de publicación
        /** @var list<array{value: string, label: string}> $typeOptions */
        $typeOptions = [];
        foreach (PostType::cases() as $case) {
            $typeOptions[] = [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }

        $contentSection->add(
            UI::select('post_type')
                ->label('Tipo de Contenido')
                ->options($typeOptions)
                ->value($post ? $post->type->value : PostType::TEXT->value)
        );

        // Cuerpo del mensaje usando Textarea
        $contentSection->add(
            (new Textarea('post_content'))
                ->label('Cuerpo del Mensaje / Detalle')
                ->placeholder('Descripción detallada para la difusión...')
                ->value($post ? (string) ($post->content ?? '') : '')
                ->height(Size::px(120))
        );

        // Fechas de vigencia
        $datesContainer = UI::container('dates_container')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->gap(Spacing::px(12))
            ->plain();

        $defaultStart = $post ? $post->starts_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i');
        $defaultEnd = $post ? $post->ends_at->format('Y-m-d\TH:i') : now()->addDays(7)->format('Y-m-d\TH:i');

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

        $contentSection->add($datesContainer);

        // Duración en pantalla y Checkbox de Difusión Pública
        $settingsContainer = UI::container('settings_container')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->gap(Spacing::px(12))
            ->plain();

        $settingsContainer->add(
            UI::input('post_display_duration_sec')
                ->label('Duración en Kiosk (segundos)')
                ->type('number')
                ->value($post ? (string) $post->display_duration_sec : '10')
        );

        $settingsContainer->add(
            UI::checkbox('post_is_public')
                ->label('Difusión Pública (Visible en Kiosks Generales / Entrada)')
                ->checked($post ? $post->is_public : false)
        );

        $contentSection->add($settingsContainer);
        $tabsContainer->add($contentSection, tab: 'tab_content');

        // ================= TAB 2: MULTIMEDIA =================
        $mediaSection = UI::container('section_media')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(12))
            ->plain();

        $mediaSection->add(
            UI::label('lbl_media_instructions')
                ->text('📸 / 🎥 Para publicaciones de tipo Imagen o Video, sube el archivo a difundir mediante el uploader a continuación:')
                ->fontSize('13px')
        );

        // Uploader para imagen o video
        $uploader = UI::uploader('post_uploader')
            ->label('Subir Archivo Multimedia (Imagen o Video)')
            ->media()
            ->multiple(false)
            ->maxFiles(1)
            ->maxSize(50);

        if ($post !== null && is_string($post->media_url) && $post->media_url !== '') {
            $uploader->existingFile($post->media_url);
        }

        $mediaSection->add($uploader);

        // URL alternativa u opcional
        $mediaSection->add(
            UI::input('post_media_url')
                ->label('URL Externa (Opcional si subiste archivo mediante el uploader)')
                ->placeholder('https://...')
                ->value($post ? (string) ($post->media_url ?? '') : '')
        );

        if ($post !== null && is_string($post->media_url) && $post->media_url !== '') {
            $mediaSection->add(
                UI::label('lbl_current_media')
                    ->text("📎 Archivo multimedia actual: {$post->media_url}")
                    ->fontSize('12px')
            );
        }

        $tabsContainer->add($mediaSection, tab: 'tab_media');
        $container->add($tabsContainer);

        // ================= BOTONES DE ACCIÓN =================
        $buttonsContainer = UI::container('dialog_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->gap(Spacing::px(10))
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

        $container->add($buttonsContainer);

        return $container->toJson();
    }
}
