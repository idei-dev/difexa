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
    protected ?string $statusFilter = PostStatus::PENDING->value;

    public function __construct(Table $tableBuilder)
    {
        parent::__construct($tableBuilder);
    }

    public function setStatusFilter(?string $status): self
    {
        $this->statusFilter = $status;

        return $this;
    }

    protected function getFilters(): array
    {
        $filters = [];
        if (! empty($this->statusFilter)) {
            $filters['status'] = $this->statusFilter;
        }

        return $filters;
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
                'width' => 200,
                'sort_by' => 'title',
            ],
            'author' => [
                'label' => 'Autor',
                'width' => 130,
                'sort_by' => 'author_name',
            ],
            'unit' => [
                'label' => 'Unidad',
                'width' => 130,
            ],
            'type' => [
                'label' => 'Tipo',
                'width' => 90,
                'sort_by' => 'type',
            ],
            'status' => [
                'label' => 'Estado',
                'width' => 110,
                'sort_by' => 'status',
            ],
        ];
    }

    /**
     * @param  Post  $item
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
        $displayName = $post->unit->display_name;
        $unitName = (! empty($displayName) && $displayName !== $post->unit->translation_key)
            ? $displayName
            : ucfirst($post->unit->slug);

        return [
            '_model_id' => $post->id,
            'title' => $post->title,
            'author' => $authorName,
            'unit' => $unitName,
            'type' => "{$typeIcon} {$post->type->label()}",
            'status' => $statusBadge,
        ];
    }
}
