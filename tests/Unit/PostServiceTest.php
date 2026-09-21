<?php

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use App\Services\Post\PostService;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    /** @var UsimUnit $unit */
    $this->unit = UsimUnit::firstOrCreate(
        ['slug' => 'exactas-comunicacion'],
        ['name' => 'Secretaría de Comunicación', 'type' => 'academic']
    );

    $this->author = User::factory()->create();
    $this->reviewer = User::factory()->create();
    $this->service = new PostService();
});

it('creates a post in draft status by default', function () {
    $data = [
        'title' => 'Conferencia de Inteligencia Artificial',
        'content' => 'Se invita a toda la comunidad académica a la conferencia...',
        'type' => PostType::TEXT->value,
        'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
        'display_duration_sec' => 15,
        'is_public' => true,
    ];

    $post = $this->service->create($data, $this->author, $this->unit->id);

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->title)->toBe('Conferencia de Inteligencia Artificial')
        ->and($post->status)->toBe(PostStatus::DRAFT)
        ->and($post->user_id)->toBe($this->author->id)
        ->and($post->unit_id)->toBe($this->unit->id)
        ->and($post->display_duration_sec)->toBe(15)
        ->and($post->is_public)->toBeTrue();
});

it('creates a post in pending status when submit_now is requested', function () {
    $data = [
        'title' => 'Llamado a Concurso Docente',
        'type' => PostType::TEXT->value,
        'starts_at' => now()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        'submit_now' => true,
    ];

    $post = $this->service->create($data, $this->author, $this->unit->id);

    expect($post->status)->toBe(PostStatus::PENDING);
});

it('fails to create a post if required fields are missing', function () {
    $data = [
        'title' => '',
    ];

    $this->service->create($data, $this->author, $this->unit->id);
})->throws(ValidationException::class);

it('allows the author to update a draft post', function () {
    $post = Post::factory()->draft()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unit->id,
        'title' => 'Título Inicial',
    ]);

    $updated = $this->service->update($post, [
        'title' => 'Título Actualizado',
    ], $this->author);

    expect($updated->title)->toBe('Título Actualizado')
        ->and($updated->status)->toBe(PostStatus::DRAFT);
});

it('submits a draft post for approval', function () {
    $post = Post::factory()->draft()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unit->id,
    ]);

    $submitted = $this->service->submitForApproval($post, $this->author);

    expect($submitted->status)->toBe(PostStatus::PENDING);
});

it('approves a pending post by a reviewer', function () {
    $post = Post::factory()->pending()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unit->id,
    ]);

    $approved = $this->service->approve($post, $this->reviewer);

    expect($approved->status)->toBe(PostStatus::APPROVED)
        ->and($approved->approved_by)->toBe($this->reviewer->id)
        ->and($approved->approved_at)->not->toBeNull()
        ->and($approved->rejection_reason)->toBeNull();
});

it('rejects a pending post with a mandatory reason', function () {
    $post = Post::factory()->pending()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unit->id,
    ]);

    $rejected = $this->service->reject($post, $this->reviewer, 'La imagen supera las dimensiones permitidas.');

    expect($rejected->status)->toBe(PostStatus::REJECTED)
        ->and($rejected->approved_by)->toBe($this->reviewer->id)
        ->and($rejected->rejection_reason)->toBe('La imagen supera las dimensiones permitidas.');
});

it('prevents non-authors from editing posts', function () {
    $otherUser = User::factory()->create();
    $post = Post::factory()->draft()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unit->id,
    ]);

    $this->service->update($post, ['title' => 'Hack'], $otherUser);
})->throws(InvalidArgumentException::class);

it('prevents editing an already approved post', function () {
    $post = Post::factory()->approved()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unit->id,
    ]);

    $this->service->update($post, ['title' => 'Cambio posterior'], $this->author);
})->throws(InvalidArgumentException::class);

