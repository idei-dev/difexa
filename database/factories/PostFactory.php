<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'unit_id' => 1,
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'type' => PostType::TEXT,
            'media_url' => null,
            'media_mime' => null,
            'status' => PostStatus::DRAFT,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'display_duration_sec' => 10,
            'is_public' => false,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::DRAFT,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::PENDING,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Contenido no cumple pautas de difusión'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::REJECTED,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PostType::IMAGE,
            'media_url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1200&q=80',
            'media_mime' => 'image/jpeg',
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PostType::VIDEO,
            'media_url' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
            'media_mime' => 'video/mp4',
        ]);
    }

    public function publicPost(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }
}

