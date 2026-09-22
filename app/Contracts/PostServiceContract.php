<?php

namespace App\Contracts;

use App\Models\Post;
use App\Models\User;

interface PostServiceContract
{
    /**
     * Create a new post in the specified academic unit.
     *
     * @param array{
     *     title: string,
     *     content?: string|null,
     *     type: string,
     *     media_url?: string|null,
     *     media_mime?: string|null,
     *     starts_at: string,
     *     ends_at: string,
     *     display_duration_sec?: int,
     *     is_public?: bool,
     *     submit_now?: bool,
     *     device_ids?: list<int>
     * } $data
     */
    public function create(array $data, User $author, int $unitId): Post;

    /**
     * Update an existing post.
     *
     * @param array{
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
     *     device_ids?: list<int>
     * } $data
     */
    public function update(Post $post, array $data, User $user): Post;

    /**
     * Submit a draft or rejected post for approval by the communication unit.
     */
    public function submitForApproval(Post $post, User $user): Post;

    /**
     * Approve a post for broadcast on kiosks.
     */
    public function approve(Post $post, User $approver): Post;

    /**
     * Reject a post with an explanation.
     */
    public function reject(Post $post, User $approver, string $reason): Post;

    /**
     * Delete a post.
     */
    public function delete(Post $post, User $user): bool;
}
