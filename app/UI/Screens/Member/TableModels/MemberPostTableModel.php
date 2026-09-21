<?php

namespace App\UI\Screens\Member\TableModels;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Post;
use App\Services\Post\PostListingService;
use Auth;
use Idei\Usim\Components\Table;
use Idei\Usim\DataTable\AbstractListingTableModel;

/**
 * Table model for managing member's own posts listing.
 *
 * @extends AbstractListingTableModel<Post>
 */
class MemberPostTableModel extends AbstractListingTableModel
{
    protected ?int $authorId = null;

    public function __construct(Table $tableBuilder, ?int $authorId = null)
    {
        $this->authorId = $authorId;
        parent::__construct($tableBuilder);
    }

    public function forAuthor(?int $authorId): self
    {
        $this->authorId = $authorId;

        return $this;
    }

    protected function resolveListingService(): PostListingService
    {
        $service = app(PostListingService::class);
        $service->forAuthor(Auth::id());
        // if ($this->authorId !== null) {
        //     $service->forAuthor($this->authorId);
        // }

        return $service;
    }

    /**
     * @return array<string, array{label: string, width?: int, sort_by?: string}>
     */
    public function getColumns(): array
    {
        return [
            'title' => [
                'label' => 'Título',
                'width' => 240,
                'sort_by' => 'title',
            ],
            'type' => [
                'label' => 'Tipo',
                'width' => 110,
                'sort_by' => 'type',
            ],
            'status' => [
                'label' => 'Estado',
                'width' => 150,
                'sort_by' => 'status',
            ],
            'dates' => [
                'label' => 'Vigencia',
                'width' => 200,
                'sort_by' => 'starts_at',
            ],
            'kiosk' => [
                'label' => 'Pantalla / Kiosk',
                'width' => 140,
            ],
            'feedback' => [
                'label' => 'Observaciones / Motivo',
                'width' => 200,
            ],
        ];
    }

    /**
     * @param Post $item
     * @return array{
     *     _model_id: int|string,
     *     title: string,
     *     type: string,
     *     status: string,
     *     dates: string,
     *     kiosk: string,
     *     feedback: string
     * }
     */
    protected function formatRow(object $item): array
    {
        /** @var Post $post */
        $post = $item;

        $typeIcon = match ($post->type) {
            PostType::TEXT => '📝',
            PostType::IMAGE => '🖼️',
            PostType::VIDEO => '🎬',
        };

        $statusBadge = match ($post->status) {
            PostStatus::DRAFT => '⚪ Borrador',
            PostStatus::PENDING => '🟡 Pendiente',
            PostStatus::APPROVED => '🟢 Aprobado',
            PostStatus::REJECTED => '🔴 Rechazado',
            PostStatus::EXPIRED => '⌛ Expirado',
        };

        $dates = $post->starts_at->format('d/m/Y') . ' al ' . $post->ends_at->format('d/m/Y');
        $kioskInfo = "{$post->display_duration_sec}s" . ($post->is_public ? ' (Público)' : '');
        $feedback = ($post->status === PostStatus::REJECTED && $post->rejection_reason)
            ? (string) $post->rejection_reason
            : '-';

        return [
            '_model_id' => $post->id,
            'title' => $post->title,
            'type' => "{$typeIcon} {$post->type->label()}",
            'status' => $statusBadge,
            'dates' => $dates,
            'kiosk' => $kioskInfo,
            'feedback' => $feedback,
        ];
    }
}

