<?php

namespace App\Services\Post;

use App\Contracts\PostServiceContract;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PostService implements PostServiceContract
{
    /**
     * {@inheritDoc}
     */
    public function create(array $data, User $author, int $unitId): Post
    {
        $validated = $this->validatePostData($data);

        $submitNow = (bool) ($data['submit_now'] ?? false);
        $status = $submitNow ? PostStatus::PENDING : PostStatus::DRAFT;

        /** @var Post $post */
        $post = Post::create([
            'user_id' => $author->id,
            'unit_id' => $unitId,
            'title' => (string) ($validated['title'] ?? ''),
            'content' => isset($validated['content']) ? (string) $validated['content'] : null,
            'type' => (string) ($validated['type'] ?? PostType::TEXT->value),
            'media_url' => isset($validated['media_url']) ? (string) $validated['media_url'] : null,
            'media_mime' => isset($validated['media_mime']) ? (string) $validated['media_mime'] : null,
            'status' => $status,
            'starts_at' => Carbon::parse((string) ($validated['starts_at'] ?? now())),
            'ends_at' => Carbon::parse((string) ($validated['ends_at'] ?? now())),
            'display_duration_sec' => isset($validated['display_duration_sec']) ? (int) $validated['display_duration_sec'] : 10,
            'is_public' => isset($validated['is_public']) ? (bool) $validated['is_public'] : false,
        ]);

        if (array_key_exists('device_ids', $validated)) {
            $post->devices()->sync($validated['device_ids'] ?? []);
        }

        return $post;
    }

    /**
     * {@inheritDoc}
     */
    public function update(Post $post, array $data, User $user): Post
    {
        if ($post->user_id !== $user->id && ! $user->hasRole('admin')) {
            throw new InvalidArgumentException(t('service.post.unauthorized_edit'));
        }

        if (! $post->status->canBeEdited()) {
            throw new InvalidArgumentException(t('service.post.cannot_edit_status'));
        }

        $validated = $this->validatePostData($data, isUpdate: true);

        $submitNow = (bool) ($data['submit_now'] ?? false);
        $newStatus = $post->status;
        if ($submitNow) {
            $newStatus = PostStatus::PENDING;
        } elseif ($post->status === PostStatus::REJECTED) {
            $newStatus = PostStatus::DRAFT;
        }

        $post->update([
            'title' => isset($validated['title']) ? (string) $validated['title'] : $post->title,
            'content' => array_key_exists('content', $validated) ? ($validated['content'] !== null ? (string) $validated['content'] : null) : $post->content,
            'type' => isset($validated['type']) ? (string) $validated['type'] : $post->type->value,
            'media_url' => array_key_exists('media_url', $validated) ? ($validated['media_url'] !== null ? (string) $validated['media_url'] : null) : $post->media_url,
            'media_mime' => array_key_exists('media_mime', $validated) ? ($validated['media_mime'] !== null ? (string) $validated['media_mime'] : null) : $post->media_mime,
            'status' => $newStatus,
            'starts_at' => isset($validated['starts_at']) ? Carbon::parse((string) $validated['starts_at']) : $post->starts_at,
            'ends_at' => isset($validated['ends_at']) ? Carbon::parse((string) $validated['ends_at']) : $post->ends_at,
            'display_duration_sec' => isset($validated['display_duration_sec']) ? (int) $validated['display_duration_sec'] : $post->display_duration_sec,
            'is_public' => isset($validated['is_public']) ? (bool) $validated['is_public'] : $post->is_public,
            'rejection_reason' => $submitNow ? null : $post->rejection_reason,
        ]);

        if (array_key_exists('device_ids', $validated)) {
            $post->devices()->sync($validated['device_ids'] ?? []);
        }

        return $post->fresh() ?? $post;
    }

    /**
     * {@inheritDoc}
     */
    public function submitForApproval(Post $post, User $user): Post
    {
        if ($post->user_id !== $user->id && ! $user->hasRole('admin')) {
            throw new InvalidArgumentException(t('service.post.unauthorized_submit'));
        }

        if (! $post->status->canBeSubmitted()) {
            throw new InvalidArgumentException(t('service.post.cannot_submit_status'));
        }

        $post->update([
            'status' => PostStatus::PENDING,
            'rejection_reason' => null,
        ]);

        return $post->fresh() ?? $post;
    }

    /**
     * {@inheritDoc}
     */
    public function approve(Post $post, User $approver): Post
    {
        if (! $post->status->canBeModerated()) {
            throw new InvalidArgumentException(t('service.post.cannot_moderate_status'));
        }

        $post->update([
            'status' => PostStatus::APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return $post->fresh() ?? $post;
    }

    /**
     * {@inheritDoc}
     */
    public function reject(Post $post, User $approver, string $reason): Post
    {
        if (! $post->status->canBeModerated()) {
            throw new InvalidArgumentException(t('service.post.cannot_moderate_status'));
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new InvalidArgumentException(t('service.post.rejection_reason_required'));
        }

        $post->update([
            'status' => PostStatus::REJECTED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $trimmedReason,
        ]);

        return $post->fresh() ?? $post;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Post $post, User $user): bool
    {
        if ($post->user_id !== $user->id && ! $user->hasRole('admin')) {
            throw new InvalidArgumentException(t('service.post.unauthorized_delete'));
        }

        return (bool) $post->delete();
    }

    /**
     * Validate raw input data for post creation/update.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     title?: string,
     *     content?: string|null,
     *     type?: string,
     *     media_url?: string|null,
     *     media_mime?: string|null,
     *     starts_at?: string,
     *     ends_at?: string,
     *     display_duration_sec?: int,
     *     is_public?: bool,
     *     submit_now?: bool,
     *     device_ids?: array<int>|null
     * }
     *
     * @throws ValidationException
     */
    protected function validatePostData(array $data, bool $isUpdate = false): array
    {
        $rules = [
            'title' => array_merge($isUpdate ? ['sometimes'] : [], ['required', 'string', 'max:255']),
            'content' => ['nullable', 'string'],
            'type' => array_merge($isUpdate ? ['sometimes'] : [], ['required', 'string', 'in:'.implode(',', PostType::values())]),
            'media_url' => ['nullable', 'string'],
            'media_mime' => ['nullable', 'string', 'max:100'],
            'starts_at' => array_merge($isUpdate ? ['sometimes'] : [], ['required', 'date']),
            'ends_at' => array_merge($isUpdate ? ['sometimes'] : [], ['required', 'date', 'after_or_equal:starts_at']),
            'display_duration_sec' => ['nullable', 'integer', 'min:3', 'max:120'],
            'is_public' => ['nullable', 'boolean'],
            'submit_now' => ['nullable', 'boolean'],
            'device_ids' => ['nullable', 'array'],
            'device_ids.*' => ['integer', 'exists:devices,id'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{
         *     title?: string,
         *     content?: string|null,
         *     type?: string,
         *     media_url?: string|null,
         *     media_mime?: string|null,
         *     starts_at?: string,
         *     ends_at?: string,
         *     display_duration_sec?: int,
         *     is_public?: bool,
         *     submit_now?: bool,
         *     device_ids?: array<int>|null
         * } $validated */
        $validated = $validator->validated();

        return $validated;
    }
}
