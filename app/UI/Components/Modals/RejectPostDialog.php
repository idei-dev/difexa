<?php

namespace App\UI\Components\Modals;

use App\Models\Post;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\ValueObjects\Spacing;

class RejectPostDialog
{
    /**
     * Open the reject post modal dialog.
     */
    public static function open(
        string $submitAction = 'submit_reject_post',
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
        string $submitAction = 'submit_reject_post',
        string $cancelAction = 'close_modal',
        ?Post $post = null,
        ?int $callerServiceId = null
    ): array {
        $container = UI::container('reject_post_dialog')
            ->parent('modal')
            ->shadow(false)
            ->plain()
            ->padding(Spacing::px(10))
            ->gap(Spacing::px(14));

        $container->add(
            UI::label('dialog_title')
                ->text('⚠️ Rechazar Publicación')
                ->fontSize('20px')
                ->bold()
        );

        $postTitle = $post ? $post->title : '';
        $container->add(
            UI::label('post_title_info')
                ->text("Publicación: {$postTitle}")
                ->size('medium')
        );

        if ($post) {
            $container->add(
                UI::input('post_id')
                    ->type('hidden')
                    ->value((string) $post->id)
            );
        }

        $container->add(
            UI::input('rejection_reason')
                ->label('Motivo del Rechazo (Obligatorio)')
                ->placeholder('Especifica las correcciones o motivo por el que no se aprueba...')
                ->required(true)
        );

        // Botones de acción
        $buttonsContainer = UI::container('dialog_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->gap(Spacing::px(10))
            ->plain();

        $buttonsContainer->add(
            UI::button('btn_cancel_reject')
                ->label('Cancelar')
                ->style('secondary')
                ->action($cancelAction)
        );

        $buttonsContainer->add(
            UI::button('btn_confirm_reject')
                ->label('Confirmar Rechazo')
                ->style('danger')
                ->action($submitAction)
        );

        $container->add($buttonsContainer);

        return $container->toJson();
    }
}

