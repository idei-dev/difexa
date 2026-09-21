<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\UI\Screens\Communication\PostApprovalScreen;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->commUnit = UsimUnit::firstOrCreate(
        ['slug' => 'comunicacion'],
        ['name' => 'Secretaría de Comunicación', 'type' => 'academic']
    );

    $this->otherUnit = UsimUnit::firstOrCreate(
        ['slug' => 'quimica'],
        ['name' => 'Departamento de Química', 'type' => 'academic']
    );

    $this->author = User::factory()->create();
    $this->author->usimUnits()->attach($this->otherUnit->id);

    $this->reviewer = User::factory()->create();
    $this->reviewer->usimUnits()->attach($this->commUnit->id);
});

it('denies access to users without communication membership', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->author);

    $uiResponse = getScreenJson($this, PostApprovalScreen::class);
    $uiResponse->assertOk();
    expect($uiResponse->json('abort.code'))->toBe(403);
});

it('loads post approval screen for communication members', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->reviewer);

    $ui = uiScenario($this, PostApprovalScreen::class, ['reset' => true]);

    $title = $ui->component('page_title');
    $searchInput = $ui->component('search_posts');
    $table = $ui->component('posts_table');

    $title->expect('type')->toBe('label');
    $searchInput->expect('type')->toBe('input');
    $table->expect('type')->toBe('table');

    $ui->assertNoIssues();
});

it('approves a pending post via submit_approve_post event', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->reviewer);

    $post = Post::factory()->pending()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->otherUnit->id,
        'title' => 'Concurso de Becas de Investigación',
    ]);

    $uiResponse = getScreenJson($this, PostApprovalScreen::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_approve_post',
        'parameters' => [
            'post_id' => $post->id,
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');

    $post->refresh();
    expect($post->status)->toBe(PostStatus::APPROVED)
        ->and($post->approved_by)->toBe($this->reviewer->id)
        ->and($post->approved_at)->not->toBeNull();
});

it('rejects a pending post via submit_reject_post event with reason', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->reviewer);

    $post = Post::factory()->pending()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->otherUnit->id,
        'title' => 'Post con Errores de Formato',
    ]);

    $uiResponse = getScreenJson($this, PostApprovalScreen::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_reject_post',
        'parameters' => [
            'post_id' => $post->id,
            'rejection_reason' => 'Por favor ajustar las fechas de inicio a la semana próxima.',
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('warning');

    $post->refresh();
    expect($post->status)->toBe(PostStatus::REJECTED)
        ->and($post->approved_by)->toBe($this->reviewer->id)
        ->and($post->rejection_reason)->toBe('Por favor ajustar las fechas de inicio a la semana próxima.');
});
