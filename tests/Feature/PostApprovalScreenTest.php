<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\UI\Screens\Communication\PostApprovalScreen;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
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

    setPermissionsTeamId($this->commUnit->id);
    $permission = Permission::firstOrCreate([
        'name' => 'communication.post_approval.access',
        'guard_name' => 'web',
    ]);
    $this->reviewer->givePermissionTo($permission);
});

it('denies access to users without communication membership', function () {
    /** @var TestCase $this */
    $this->actingAs($this->author);

    $uiResponse = getScreenJson($this, PostApprovalScreen::class);
    $uiResponse->assertOk();
    expect($uiResponse->json('abort.code'))->toBe(403);
});

it('loads post approval screen for communication members', function () {
    /** @var TestCase $this */
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
    /** @var TestCase $this */
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
    /** @var TestCase $this */
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

it('updates review panel when clicking a table row', function () {
    /** @var TestCase $this */
    $this->actingAs($this->reviewer);

    $post = Post::factory()->pending()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->otherUnit->id,
        'title' => 'Curso de Inteligencia Artificial',
        'content' => 'Contenido detallado del curso.',
    ]);

    $uiResponse = getScreenJson($this, PostApprovalScreen::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'posts_table_row_clicked',
        'parameters' => [
            'model_id' => $post->id,
        ],
    ]);

    $response->assertOk();
    $diff = $response->json();

    $foundTitle = false;
    $foundContent = false;
    $foundApproveBtn = false;
    foreach ($diff as $item) {
        if (is_array($item)) {
            if (($item['name'] ?? null) === 'panel_post_title' && ($item['text'] ?? null) === 'Curso de Inteligencia Artificial') {
                $foundTitle = true;
            }
            if (($item['name'] ?? null) === 'review_post_content' && ($item['value'] ?? null) === 'Contenido detallado del curso.') {
                $foundContent = true;
            }
            if (($item['name'] ?? null) === 'btn_panel_approve') {
                $foundApproveBtn = true;
            }
        }
    }

    expect($foundTitle)->toBeTrue()
        ->and($foundContent)->toBeTrue()
        ->and($foundApproveBtn)->toBeTrue();
});

it('filters posts table when changing filter_by_status', function () {
    /** @var TestCase $this */
    $this->actingAs($this->reviewer);

    Post::factory()->pending()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->otherUnit->id,
        'title' => 'Post Pendiente',
    ]);

    Post::factory()->approved()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->otherUnit->id,
        'title' => 'Post Aprobado',
    ]);

    $uiResponse = getScreenJson($this, PostApprovalScreen::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'change',
        'action' => 'filter_by_status',
        'parameters' => [
            'value' => PostStatus::APPROVED->value,
        ],
    ]);

    $response->assertOk();
});
