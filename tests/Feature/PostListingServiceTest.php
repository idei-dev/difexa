<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Services\Post\PostListingService;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authorA = User::factory()->create(['name' => 'Dr. Marie Curie']);
    $this->authorB = User::factory()->create(['name' => 'Dr. Albert Einstein']);

    $this->unitA = UsimUnit::firstOrCreate(
        ['slug' => 'quimica'],
        ['name' => 'Depto Química', 'type' => 'academic']
    );

    $this->unitB = UsimUnit::firstOrCreate(
        ['slug' => 'fisica'],
        ['name' => 'Depto Física', 'type' => 'academic']
    );

    $this->service = new PostListingService();
});

it('lists all posts with default pagination and sorting', function () {
    Post::factory()->count(3)->create([
        'user_id' => $this->authorA->id,
        'unit_id' => $this->unitA->id,
    ]);

    $result = $this->service->paginate(page: 1, perPage: 10);

    expect($result['total'])->toBe(3)
        ->and($result['items'])->toHaveCount(3);
});

it('filters posts by author when scoped', function () {
    Post::factory()->create([
        'user_id' => $this->authorA->id,
        'unit_id' => $this->unitA->id,
        'title' => 'Post de Marie Curie',
    ]);

    Post::factory()->create([
        'user_id' => $this->authorB->id,
        'unit_id' => $this->unitB->id,
        'title' => 'Post de Albert Einstein',
    ]);

    $result = $this->service->forAuthor($this->authorA->id)->paginate(page: 1, perPage: 10);

    expect($result['total'])->toBe(1)
        ->and($result['items'][0]->title)->toBe('Post de Marie Curie');
});

it('filters posts by status filter', function () {
    Post::factory()->approved()->create([
        'user_id' => $this->authorA->id,
        'unit_id' => $this->unitA->id,
        'title' => 'Post Aprobado',
    ]);

    Post::factory()->pending()->create([
        'user_id' => $this->authorA->id,
        'unit_id' => $this->unitA->id,
        'title' => 'Post Pendiente',
    ]);

    $result = $this->service->paginate(
        page: 1,
        perPage: 10,
        filters: [
            'status' => PostStatus::PENDING->value,
        ]
    );

    expect($result['total'])->toBe(1)
        ->and($result['items'][0]->title)->toBe('Post Pendiente');
});

