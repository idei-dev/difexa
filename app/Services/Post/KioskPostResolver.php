<?php

namespace App\Services\Post;

use App\Contracts\KioskPostResolverContract;
use App\Models\Device;
use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;

class KioskPostResolver implements KioskPostResolverContract
{
    /**
     * @inheritDoc
     */
    public function resolveForDevice(Device $device): array
    {
        $unitIds = $device->usimUnits()->pluck('usim_units.id')->all();

        $query = Post::query()->active();

        if ($device->isPublic() || empty($unitIds)) {
            // Public device displays public posts or main unit posts
            $query->where(function ($q): void {
                $q->where('is_public', true)
                    ->orWhereHas('unit', function ($uq): void {
                        $uq->where('slug', 'main');
                    });
            });
        } else {
            // Unit-assigned device displays posts for its unit(s) plus any global/public posts
            $query->where(function ($q) use ($unitIds): void {
                $q->whereIn('unit_id', $unitIds)
                    ->orWhere('is_public', true);
            });
        }

        /** @var Collection<int, Post> $posts */
        $posts = $query->orderBy('starts_at', 'desc')->get();

        if ($posts->isEmpty()) {
            return $this->fallbackMediaItems();
        }

        /** @var list<array{id: string, kind: string, url: string, mime: string, title: string, duration_ms: int}> $media */
        $media = $posts->map(fn(Post $post): array => $post->toMediaItem())->values()->all();

        return $media;
    }

    /**
     * @inheritDoc
     */
    public function resolveForUnit(int $unitId): array
    {
        /** @var Collection<int, Post> $posts */
        $posts = Post::query()
            ->active()
            ->where(function ($q) use ($unitId): void {
                $q->where('unit_id', $unitId)
                    ->orWhere('is_public', true);
            })
            ->orderBy('starts_at', 'desc')
            ->get();

        if ($posts->isEmpty()) {
            return $this->fallbackMediaItems();
        }

        /** @var list<array{id: string, kind: string, url: string, mime: string, title: string, duration_ms: int}> $media */
        $media = $posts->map(fn(Post $post): array => $post->toMediaItem())->values()->all();

        return $media;
    }

    /**
     * @inheritDoc
     */
    public function resolvePublic(): array
    {
        /** @var Collection<int, Post> $posts */
        $posts = Post::query()
            ->active()
            ->where('is_public', true)
            ->orderBy('starts_at', 'desc')
            ->get();

        if ($posts->isEmpty()) {
            return $this->fallbackMediaItems();
        }

        /** @var list<array{id: string, kind: string, url: string, mime: string, title: string, duration_ms: int}> $media */
        $media = $posts->map(fn(Post $post): array => $post->toMediaItem())->values()->all();

        return $media;
    }

    /**
     * Default fallback media items displayed when no active posts are available.
     *
     * @return list<array{id: string, kind: string, url: string, mime: string, title: string, duration_ms: int}>
     */
    protected function fallbackMediaItems(): array
    {
        return [
            [
                'id' => 'fallback_welcome',
                'kind' => 'image',
                'url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1200&q=80',
                'mime' => 'image/jpeg',
                'title' => t('screen.device.kiosk.media.welcome'),
                'duration_ms' => 8000,
            ],
        ];
    }
}
