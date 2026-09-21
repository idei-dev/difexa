<?php

namespace App\UI\Screens\Communication\TableModels;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Post;
use App\Services\Post\PostListingService;
use Idei\Usim\Components\Table;
use Idei\Usim\DataTable\AbstractListingTableModel;

/**
 * Table model for moderation and approval of posts by the communication unit.
 *
 * @extends AbstractListingTableModel<Post>
 */
class PostApprovalTableModel extends AbstractListingTableModel
{
    public function __construct(Table $tableBuilder)
    {
        parent::__construct($tableBuilder);
    }

    protected function resolveListingService(): PostListingService
    {
        return app(PostListingService::class);
    }

    /**
     * @return array<string, array{label: string, width?: int, sort_by?: string}>
     */
    public function getColumns(): array
    {
        return [
            'title' => [
                'label' => 'Título',
                'width' => 220,
                'sort_by' => 'title',
            ],
            'author' => [
                'label' => 'Autor',
                'width' => 160,
                'sort_by' => 'author_name',
            ],
            'unit' => [
                'label' => 'Unidad',
                'width' => 150,
            ],
            'type' => [
                'label' => 'Tipo',
                'width' => 100,
                'sort_by' => 'type',
            ],
            'status' => [
                'label' => 'Estado',
                'width' => 140,
                'sort_by' => 'status',
            ],
            'dates' => [
                'label' => 'Vigencia',
                'width' => 180,
                'sort_by' => 'starts_at',
            ],
            'kiosk' => [
                'label' => 'Kiosk',
                'width' => 130,
            ],
        ];
    }

    /**
     * @param Post $item
     * @return array{
     *     _model_id: int|string,
     *     title: string,
     *     author: string,
     *     unit: string,
     *     type: string,
     *     status: string,
     *     dates: string,
     *     kiosk: string
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

        $authorName = $post->author->name;
        $unitName = $post->unit->display_name ?: $post->unit->slug;
        $dates = $post->starts_at->format('d/m/Y') . ' al ' . $post->ends_at->format('d/m/Y');
        $kiosk = "{$post->display_duration_sec}s" . ($post->is_public ? ' (Público)' : '');

        return [
            '_model_id' => $post->id,
            'title' => $post->title,
            'author' => $authorName,
            'unit' => $unitName,
            'type' => "{$typeIcon} {$post->type->label()}",
            'status' => $statusBadge,
            'dates' => $dates,
            'kiosk' => $kiosk,
        ];
    }
}
