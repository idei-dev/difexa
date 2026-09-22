<?php

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use App\UI\Screens\Member\PostManager;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->unit = UsimUnit::firstOrCreate(
        ['slug' => 'exactas-informatica'],
        ['name' => 'Departamento de Computación', 'type' => 'academic']
    );

    $this->user->usimUnits()->attach($this->unit->id);

    setPermissionsTeamId($this->unit->id);
    $permission = Permission::firstOrCreate([
        'name' => 'member.post_manager.access',
        'guard_name' => 'web',
    ]);
    $this->user->givePermissionTo($permission);
});

it('loads post manager screen with expected components', function () {
    /** @var TestCase $this */
    $ui = uiScenario($this, PostManager::class, ['reset' => true]);

    $title = $ui->component('page_title');
    $createBtn = $ui->component('btn_create_post');
    $searchInput = $ui->component('search_posts');
    $table = $ui->component('posts_table');

    $title->expect('type')->toBe('label');
    $createBtn->expect('action')->toBe('open_create_post_modal');
    $searchInput->expect('type')->toBe('input');
    $table->expect('type')->toBe('table');

    $ui->assertNoIssues();
});

it('opens create post modal when clicking new post button', function () {
    /** @var TestCase $this */
    $ui = uiScenario($this, PostManager::class, ['reset' => true]);

    $response = $ui->click('btn_create_post');
    $response->assertOk();

    $dialog = findComponentByName($response->json(), 'edit_post_dialog');
    expect($dialog)->not->toBeNull()
        ->and($dialog['parent'])->toBe('modal');

    $uploader = findComponentByName($response->json(), 'post_uploader');
    expect($uploader)->not->toBeNull()
        ->and($uploader['aspect_ratio'])->toBe('16:9');
});

it('creates a new draft post via save_post event', function () {
    /** @var TestCase $this */
    $uiResponse = getScreenJson($this, PostManager::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'save_post',
        'parameters' => [
            'post_title' => 'Taller de Programación en Rust',
            'post_content' => 'Introducción a sistemas concurrentes',
            'post_type' => PostType::TEXT->value,
            'post_starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'post_ends_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'post_display_duration_sec' => 15,
            'post_is_public' => true,
            'submit_now' => false,
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');

    $post = Post::where('title', 'Taller de Programación en Rust')->first();
    expect($post)->not->toBeNull()
        ->and($post->status)->toBe(PostStatus::DRAFT)
        ->and($post->user_id)->toBe($this->user->id);
});

it('submits a post for approval via submit_for_approval event', function () {
    /** @var TestCase $this */
    $post = Post::factory()->draft()->create([
        'user_id' => $this->user->id,
        'unit_id' => $this->unit->id,
        'title' => 'Jornadas de Robótica',
    ]);

    $uiResponse = getScreenJson($this, PostManager::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_for_approval',
        'parameters' => [
            'model_id' => $post->id,
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');

    $post->refresh();
    expect($post->status)->toBe(PostStatus::PENDING);
});

it('deletes a post via delete_post event', function () {
    /** @var TestCase $this */
    $post = Post::factory()->draft()->create([
        'user_id' => $this->user->id,
        'unit_id' => $this->unit->id,
        'title' => 'Post a Eliminar',
    ]);

    $uiResponse = getScreenJson($this, PostManager::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'delete_post',
        'parameters' => [
            'model_id' => $post->id,
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');

    expect(Post::find($post->id))->toBeNull();
});
