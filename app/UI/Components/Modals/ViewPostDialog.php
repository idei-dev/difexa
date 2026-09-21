<?php

namespace App\UI\Components\Modals;

use App\Enums\PostStatus;
use App\Models\Post;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\ValueObjects\Spacing;

class ViewPostDialog
{
    /**
     * Open the view/preview post modal dialog.
     */
    public static function open(
        string $closeAction = 'close_modal',
        string $approveAction = 'submit_approve_post',
        string $rejectAction = 'open_reject_modal',
        ?Post $post = null,
        ?int $callerServiceId = null
    ): void {
        $dialog = new self();
        $format = $dialog->getUI($closeAction, $approveAction, $rejectAction, $post, $callerServiceId);
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
        string $closeAction = 'close_modal',
        string $approveAction = 'submit_approve_post',
        string $rejectAction = 'open_reject_modal',
        ?Post $post = null,
        ?int $callerServiceId = null
    ): array {
        $container = UI::container('view_post_dialog')
            ->parent('modal')
            ->shadow(false)
            ->plain()
            ->padding(Spacing::px(10))
            ->gap(Spacing::px(12));

        if (!$post) {
            $container->add(
                UI::label('empty_info')->text('No se encontró la información del post.')
            );

            return $container->toJson();
        }

        $container->add(
            UI::label('dialog_title')
                ->text("🔍 {$post->title}")
                ->fontSize('20px')
                ->bold()
        );

        $authorName = $post->author->name;
        $unitName = $post->unit->display_name ?: $post->unit->slug;

        $infoRow = UI::container('post_meta_info')
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(16))
            ->plain();

        $infoRow->add(UI::label('lbl_author')->text("👤 Autor: {$authorName}"));
        $infoRow->add(UI::label('lbl_unit')->text("🏛️ Unidad: {$unitName}"));
        $infoRow->add(UI::label('lbl_type')->text("📂 Tipo: {$post->type->label()}"));

        $container->add($infoRow);

        $dates = $post->starts_at->format('d/m/Y H:i') . ' al ' . $post->ends_at->format('d/m/Y H:i');
        $kioskInfo = "{$post->display_duration_sec} segundos" . ($post->is_public ? ' | 🌐 Difusión Pública (Todos los Kiosks)' : ' | 📍 Sólo Kiosks de la Unidad');

        $scheduleRow = UI::container('post_schedule_info')
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(16))
            ->plain();

        $scheduleRow->add(UI::label('lbl_dates')->text("🗓️ Vigencia: {$dates}"));
        $scheduleRow->add(UI::label('lbl_kiosk')->text("⏱️ Pantalla: {$kioskInfo}"));

        $container->add($scheduleRow);

        if ($post->content) {
            $container->add(
                UI::label('lbl_content_title')->text('Contenido:')->bold()
            );
            $container->add(
                UI::label('lbl_content_body')->text((string) $post->content)
            );
        }

        if ($post->media_url) {
            $container->add(
                UI::label('lbl_media_url')->text("🔗 Multimedia: {$post->media_url}")
            );
        }

        if ($post->status === PostStatus::REJECTED && $post->rejection_reason) {
            $container->add(
                UI::label('lbl_rejection_reason')
                    ->text("⚠️ Motivo de Rechazo: {$post->rejection_reason}")
                    ->style('danger')
            );
        }

        // Botones de acción
        $buttonsContainer = UI::container('dialog_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->gap(Spacing::px(10))
            ->plain();

        $buttonsContainer->add(
            UI::button('btn_close_view')
                ->label('Cerrar')
                ->style('secondary')
                ->action($closeAction)
        );

        if ($post->status === PostStatus::PENDING) {
            $buttonsContainer->add(
                UI::button('btn_reject_post')
                    ->label('Rechazar...')
                    ->style('danger')
                    ->action($rejectAction, ['post_id' => $post->id, '_model_id' => $post->id])
            );

            $buttonsContainer->add(
                UI::button('btn_approve_post')
                    ->label('✅ Aprobar y Difundir')
                    ->style('primary')
                    ->action($approveAction, ['post_id' => $post->id, '_model_id' => $post->id])
            );
        }

        $container->add($buttonsContainer);

        return $container->toJson();
    }
}
