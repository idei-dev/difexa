<?php

namespace App\Services\Post;

use App\Models\Post;
use Idei\Usim\Support\EloquentListingService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Listing service for posts with filtering, search, and sorting.
 *
 * @extends EloquentListingService<Post>
 */
class PostListingService extends EloquentListingService
{
    protected string $modelClass = Post::class;

    protected array $with = ['author', 'unit', 'approver'];

    protected ?int $authorId = null;

    protected ?int $unitId = null;

    public function forAuthor(?int $authorId): self
    {
        $this->authorId = $authorId;

        return $this;
    }

    public function forUnit(?int $unitId): self
    {
        $this->unitId = $unitId;

        return $this;
    }

    /**
     * @return Builder<Post>
     */
    protected function newBaseQuery(): Builder
    {
        $query = parent::newBaseQuery();

        if ($this->authorId !== null) {
            $query->where('user_id', $this->authorId);
        }

        if ($this->unitId !== null) {
            $query->where('unit_id', $this->unitId);
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    protected function searchableFields(): array
    {
        return [
            'title' => 'title',
            'content' => 'content',
            'author_name' => 'author.name',
        ];
    }

    /**
     * @return array<string, array{path: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'}>
     */
    protected function filterableFields(): array
    {
        return [
            'status' => [
                'path' => 'status',
                'operator' => '=',
            ],
            'type' => [
                'path' => 'type',
                'operator' => '=',
            ],
            'unit_id' => [
                'path' => 'unit_id',
                'operator' => '=',
                'cast' => 'int',
            ],
            'is_public' => [
                'path' => 'is_public',
                'operator' => '=',
                'cast' => 'bool',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sortableFields(): array
    {
        return [
            'title' => 'title',
            'status' => 'status',
            'type' => 'type',
            'starts_at' => 'starts_at',
            'ends_at' => 'ends_at',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    /**
     * @return array{field: string, direction: 'asc'|'desc'}
     */
    protected function defaultSort(): array
    {
        return [
            'field' => 'created_at',
            'direction' => 'desc',
        ];
    }
}
