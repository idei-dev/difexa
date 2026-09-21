<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\PostType;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $unit_id
 * @property string $title
 * @property string|null $content
 * @property PostType $type
 * @property string|null $media_url
 * @property string|null $media_mime
 * @property PostStatus $status
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property int $display_duration_sec
 * @property bool $is_public
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $author
 * @property-read UsimUnit $unit
 * @property-read User|null $approver
 *
 * @method static Builder<static> active()
 * @method static Builder<static> forUnit(int $unitId)
 * @method static Builder<static> publicOnly()
 * @method static Builder<static> pending()
 */
class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected $table = 'posts';

    protected $fillable = [
        'user_id',
        'unit_id',
        'title',
        'content',
        'type',
        'media_url',
        'media_mime',
        'status',
        'starts_at',
        'ends_at',
        'display_duration_sec',
        'is_public',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PostType::class,
            'status' => PostStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_public' => 'boolean',
            'display_duration_sec' => 'integer',
        ];
    }

    /**
     * Author of the post.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Academic unit the post belongs to.
     *
     * @return BelongsTo<UsimUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UsimUnit::class, 'unit_id');
    }

    /**
     * User from communication unit who approved or rejected the post.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope query to only include active approved posts within their schedule window.
     *
     * @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', PostStatus::APPROVED->value)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);
    }

    /**
     * Scope query to posts belonging to a specific academic unit.
     *
     * @param Builder<Post> $query
     * @param int $unitId
     * @return Builder<Post>
     */
    public function scopeForUnit(Builder $query, int $unitId): Builder
    {
        return $query->where('unit_id', $unitId);
    }

    /**
     * Scope query to posts marked as public / broadcastable to all kiosks.
     *
     * @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopePublicOnly(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope query to pending posts awaiting moderation.
     *
     * @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PostStatus::PENDING->value);
    }

    /**
     * Check if the post is currently active and within schedule.
     */
    public function isActive(): bool
    {
        $now = now();

        return $this->status === PostStatus::APPROVED
            && $this->starts_at <= $now
            && $this->ends_at >= $now;
    }

    /**
     * Format the post into a USIM Kiosk media carousel payload item.
     *
     * @return array{id: string, kind: string, url: string, mime: string, title: string, duration_ms: int}
     */
    public function toMediaItem(): array
    {
        $durationMs = max(1000, $this->display_duration_sec * 1000);
        $kind = match ($this->type) {
            PostType::IMAGE => 'image',
            PostType::VIDEO => 'video',
            PostType::TEXT => 'text',
        };

        return [
            'id' => 'post_' . $this->id,
            'kind' => $kind,
            'url' => (string) ($this->media_url ?? ''),
            'mime' => (string) ($this->media_mime ?? ($this->type === PostType::VIDEO ? 'video/mp4' : 'image/jpeg')),
            'title' => $this->title,
            'duration_ms' => $durationMs,
        ];
    }
}

