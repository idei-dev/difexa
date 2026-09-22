<?php

namespace App\UI\Screens\Communication\Concerns;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Post;
use App\UI\Components\Presenters\PostMediaPresenter;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Concern responsible for building the live review and preview inspector panel
 * in the reviewer screen (PostApprovalScreen).
 */
trait ManagesPostReviewSection
{
    /**
     * Build the review/preview panel container for the right side of the split layout.
     */
    protected function buildReviewPanel(?Post $post): Container
    {
        $panel = UI::container('post_review_panel')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(12))
            ->padding(Spacing::px(14))
            ->width(Size::full())
            ->minHeight(Size::px(580));

        if ($post === null) {
            $emptyContainer = UI::container('review_empty_state')
                ->layout(LayoutType::VERTICAL)
                ->alignItems(AlignItems::CENTER)
                ->justifyContent(JustifyContent::CENTER)
                ->width(Size::full())
                ->height(Size::px(500))
                ->gap(Spacing::px(12))
                ->plain();

            $emptyContainer->add(
                UI::label('empty_state_icon')
                    ->text('👈')
                    ->fontSize('40px')
            );

            $emptyContainer->add(
                UI::label('empty_state_title')
                    ->text('Selecciona una publicación')
                    ->fontSize('18px')
                    ->bold()
            );

            $emptyContainer->add(
                UI::label('empty_state_subtitle')
                    ->text('Haz clic sobre una fila de la tabla para previsualizar su imagen, video o texto y moderarla.')
                    ->size('small')
            );

            $panel->add($emptyContainer);

            return $panel;
        }

        $mediaPresenter = new PostMediaPresenter;

        // Header del post
        $header = UI::container('panel_header')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(4))
            ->plain();

        $statusBadge = match ($post->status) {
            PostStatus::PENDING => '🟡 Pendiente de Moderación',
            PostStatus::APPROVED => '🟢 Aprobado para Difusión',
            PostStatus::REJECTED => '🔴 Rechazado',
            PostStatus::DRAFT => '⚪ Borrador',
            PostStatus::EXPIRED => '⚫ Vencido',
        };

        $titleRow = UI::container('panel_title_row')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain();

        $titleRow->add(
            UI::label('panel_post_title')
                ->text($post->title)
                ->fontSize('18px')
                ->bold()
        );

        $titleRow->add(
            UI::label('panel_post_status')
                ->text($statusBadge)
                ->bold()
        );

        $header->add($titleRow);

        $authorName = $post->author->name;
        $displayName = $post->unit->display_name;
        $unitName = (! empty($displayName) && $displayName !== $post->unit->translation_key)
            ? $displayName
            : ucfirst($post->unit->slug);
        $header->add(
            UI::label('panel_post_meta')
                ->text("👤 Autor: {$authorName} | 🏛️ Unidad: {$unitName} | 📂 Tipo: {$post->type->label()}")
                ->size('small')
        );

        $panel->add($header);

        // Previsualización multimedia (Imagen o Video)
        if ($post->type->isMedia()) {
            $panel->add(
                UI::label('panel_media_label')
                    ->text('Vista Previa de Pantalla:')
                    ->bold()
                    ->fontSize('13px')
            );

            $panel->add(
                UI::label('panel_media_preview')
                    ->html($mediaPresenter->renderMediaForPost($post, 300))
            );
        }

        // Cuerpo del texto utilizando Textarea
        if ($post->content !== null && trim($post->content) !== '') {
            $panel->add(
                (new Textarea('review_post_content'))
                    ->label('Cuerpo del Mensaje / Detalle')
                    ->value((string) $post->content)
                    ->readonly(true)
                    ->height(Size::px(110))
            );
        } elseif ($post->type === PostType::TEXT) {
            $panel->add(
                (new Textarea('review_post_content'))
                    ->label('Cuerpo del Mensaje')
                    ->placeholder('(Sin contenido adicional de texto)')
                    ->value('')
                    ->readonly(true)
                    ->height(Size::px(80))
            );
        }

        // Ficha técnica (Fechas y Pantallas)
        $dates = $post->starts_at->format('d/m/Y H:i').' al '.$post->ends_at->format('d/m/Y H:i');
        $kioskInfo = "{$post->display_duration_sec} segs".($post->is_public ? ' | 🌐 Difusión Pública' : ' | 📍 Kiosks de la Unidad');
        if ($post->devices->isNotEmpty()) {
            $kioskInfo .= ' | 📺 '.$post->devices->pluck('name')->implode(', ');
        }

        $infoRow = UI::container('panel_info_row')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->gap(Spacing::px(12))
            ->plain();

        $infoRow->add(UI::label('lbl_panel_dates')->text("🗓️ {$dates}")->size('small'));
        $infoRow->add(UI::label('lbl_panel_kiosk')->text("⏱️ {$kioskInfo}")->size('small'));

        $panel->add($infoRow);

        if ($post->status === PostStatus::REJECTED && $post->rejection_reason) {
            $panel->add(
                UI::label('lbl_panel_rejection')
                    ->text("⚠️ Motivo de Rechazo: {$post->rejection_reason}")
                    ->style('danger')
            );
        }

        // Botones de acción para el revisor
        $actionsContainer = UI::container('panel_actions')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->gap(Spacing::px(10))
            ->plain();

        $actionsContainer->add(
            UI::button('btn_open_full_view')
                ->label('🔍 Pantalla Completa')
                ->style('secondary')
                ->action('open_view_post_modal', ['model_id' => $post->id, 'post_id' => $post->id])
        );

        if ($post->status === PostStatus::PENDING) {
            $actionsContainer->add(
                UI::button('btn_panel_reject')
                    ->label('Rechazar...')
                    ->style('danger')
                    ->action('open_reject_modal', ['model_id' => $post->id, 'post_id' => $post->id])
            );

            $actionsContainer->add(
                UI::button('btn_panel_approve')
                    ->label('✅ Aprobar y Difundir')
                    ->style('primary')
                    ->action('submit_approve_post', ['model_id' => $post->id, 'post_id' => $post->id])
            );
        }

        $panel->add($actionsContainer);

        return $panel;
    }
}
