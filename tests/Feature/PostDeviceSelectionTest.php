<?php

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Device;
use App\Models\Post;
use App\Models\User;
use App\Services\Device\DeviceService;
use App\Services\Post\KioskPostResolver;
use App\UI\Components\Modals\EditPostDialog;
use App\UI\Screens\Member\PostManager;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mainUnit = UsimUnit::firstOrCreate(
        ['slug' => 'main'],
        ['name' => 'Sede Central', 'type' => 'academic']
    );

    $this->unitQuimica = UsimUnit::firstOrCreate(
        ['slug' => 'quimica'],
        ['name' => 'Departamento de Química', 'type' => 'academic']
    );

    $this->unitFisica = UsimUnit::firstOrCreate(
        ['slug' => 'fisica'],
        ['name' => 'Departamento de Física', 'type' => 'academic']
    );

    $this->author = User::factory()->create();
    $this->author->usimUnits()->attach($this->unitQuimica->id);

    setPermissionsTeamId($this->unitQuimica->id);
    $permission = Permission::firstOrCreate([
        'name' => 'member.post_manager.access',
        'guard_name' => 'web',
    ]);
    $this->author->givePermissionTo($permission);

    // Smart-TV role for devices
    $this->smartTvRole = UsimRole::firstOrCreate([
        'name' => 'smart_tv',
        'guard_name' => 'device',
    ]);

    // Other role
    $this->otherRole = UsimRole::firstOrCreate([
        'name' => 'printer',
        'guard_name' => 'device',
    ]);
});

it('retrieves smart-tv devices for the active unit and institutional/public devices', function () {
    /** @var DeviceService $deviceService */
    $deviceService = app(DeviceService::class);

    // Device 1: Smart TV in current unit (Quimica)
    $devQuimica = $deviceService->createDevice([
        'name' => 'Smart TV Laboratorio Química',
        'unit_id' => $this->unitQuimica->id,
        'roles' => ['smart_tv'],
    ]);

    // Device 2: Smart TV in Main (Institutional / Public)
    $devMain = $deviceService->createDevice([
        'name' => 'Smart TV Hall Central',
        'unit_id' => $this->mainUnit->id,
        'roles' => ['smart_tv'],
    ]);

    // Device 3: Smart TV with no unit assignment (Public)
    $devUnassigned = $deviceService->createDevice([
        'name' => 'Smart TV Entrada General',
        'roles' => ['smart_tv'],
    ]);

    // Device 4: Smart TV in another unit (Fisica) - Should NOT be included
    $devFisica = $deviceService->createDevice([
        'name' => 'Smart TV Aula Magna Física',
        'unit_id' => $this->unitFisica->id,
        'roles' => ['smart_tv'],
    ]);

    // Device 5: Device in current unit (Quimica) but without smart-tv role - Should NOT be included
    $devNonSmart = $deviceService->createDevice([
        'name' => 'Impresora Química',
        'unit_id' => $this->unitQuimica->id,
        'roles' => ['printer'],
    ]);

    $devices = $deviceService->getSmartTvDevicesForUnit($this->unitQuimica->id);
    $deviceIds = $devices->pluck('id')->all();

    expect($deviceIds)->toContain($devQuimica->id)
        ->and($deviceIds)->toContain($devMain->id)
        ->and($deviceIds)->toContain($devUnassigned->id)
        ->and($deviceIds)->not->toContain($devFisica->id)
        ->and($deviceIds)->not->toContain($devNonSmart->id);
});

it('renders smart-tv checkboxes in EditPostDialog', function () {
    /** @var DeviceService $deviceService */
    $deviceService = app(DeviceService::class);

    $devQuimica = $deviceService->createDevice([
        'name' => 'Smart TV Pasillo Química',
        'unit_id' => $this->unitQuimica->id,
        'roles' => ['smart_tv'],
    ]);

    $dialog = new EditPostDialog();
    $ui = $dialog->getUI(unitId: $this->unitQuimica->id);

    $foundCheckbox = false;
    foreach ($ui as $component) {
        if (($component['type'] ?? null) === 'checkbox' && ($component['name'] ?? null) === 'post_devices') {
            $foundCheckbox = true;
            $options = $component['options'] ?? [];
            $values = array_column($options, 'value');
            expect($values)->toContain((string) $devQuimica->id);
        }
    }

    expect($foundCheckbox)->toBeTrue();
});

it('saves post with selected smart-tv devices', function () {
    /** @var TestCase $this */
    $this->actingAs($this->author);

    /** @var DeviceService $deviceService */
    $deviceService = app(DeviceService::class);

    $dev1 = $deviceService->createDevice([
        'name' => 'Smart TV Lab 1',
        'unit_id' => $this->unitQuimica->id,
        'roles' => ['smart_tv'],
    ]);

    $dev2 = $deviceService->createDevice([
        'name' => 'Smart TV Hall Central',
        'unit_id' => $this->mainUnit->id,
        'roles' => ['smart_tv'],
    ]);

    $uiResponse = getScreenJson($this, PostManager::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'save_post',
        'parameters' => [
            'post_title' => 'Taller de Síntesis Orgánica',
            'post_content' => 'Taller teórico-práctico en laboratorio.',
            'post_type' => 'text',
            'post_starts_at' => now()->toDateTimeString(),
            'post_ends_at' => now()->addDays(5)->toDateTimeString(),
            'post_display_duration_sec' => 15,
            'post_devices' => [(string) $dev1->id, (string) $dev2->id],
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');

    $post = Post::where('title', 'Taller de Síntesis Orgánica')->first();
    expect($post)->not->toBeNull();

    $assignedDeviceIds = $post->devices()->pluck('devices.id')->all();
    expect($assignedDeviceIds)->toContain($dev1->id)
        ->and($assignedDeviceIds)->toContain($dev2->id);
});

it('automatically changes post type to image when user uploads an image', function () {
    /** @var TestCase $this */
    $this->actingAs($this->author);

    \Illuminate\Support\Facades\Storage::fake('local');

    $tempId = (string) \Illuminate\Support\Str::uuid();
    $tempPath = "temp/{$tempId}.jpg";
    \Illuminate\Support\Facades\Storage::disk('local')->put($tempPath, 'fake-image-content');

    // Simulate temporary upload of an image
    DB::table('temporary_uploads')->insert([
        'id' => $tempId,
        'user_id' => $this->author->id,
        'component_id' => 'post_uploader',
        'original_filename' => 'banner.jpg',
        'stored_filename' => "{$tempId}.jpg",
        'path' => $tempPath,
        'mime_type' => 'image/jpeg',
        'size' => 204800,
        'type' => 'image',
        'expires_at' => now()->addHour(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $uiResponse = getScreenJson($this, PostManager::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    // User submits post with post_type = 'text', but attached an image
    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'save_post',
        'parameters' => [
            'post_title' => 'Conferencia Anual de Química',
            'post_type' => 'text', // user originally had 'text'
            'post_uploader' => $tempId,
            'post_starts_at' => now()->toDateTimeString(),
            'post_ends_at' => now()->addDays(3)->toDateTimeString(),
        ],
    ]);

    $response->assertOk();

    $post = Post::where('title', 'Conferencia Anual de Química')->first();
    expect($post)->not->toBeNull()
        ->and($post->type)->toBe(PostType::IMAGE);
});

it('automatically changes post type to image when external image url is provided', function () {
    /** @var TestCase $this */
    $this->actingAs($this->author);

    $uiResponse = getScreenJson($this, PostManager::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    // User submits post with post_type = 'text', but provided an external image URL
    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'save_post',
        'parameters' => [
            'post_title' => 'Seminario Web de Química',
            'post_type' => 'text',
            'post_media_url' => 'https://example.com/flyer-seminario.jpg',
            'post_starts_at' => now()->toDateTimeString(),
            'post_ends_at' => now()->addDays(3)->toDateTimeString(),
        ],
    ]);

    $response->assertOk();

    $post = Post::where('title', 'Seminario Web de Química')->first();
    expect($post)->not->toBeNull()
        ->and($post->type)->toBe(PostType::IMAGE);
});

it('resolves targeted posts on specific device through KioskPostResolver', function () {
    /** @var DeviceService $deviceService */
    $deviceService = app(DeviceService::class);

    $device = $deviceService->createDevice([
        'name' => 'Totem Exclusivo Biblioteca',
        'unit_id' => $this->unitQuimica->id,
        'roles' => ['smart_tv'],
    ]);

    // Create an active post belonging to a different unit, but targeted specifically to this device
    $post = Post::factory()->approved()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unitFisica->id,
        'title' => 'Charla Interdepartamental',
        'is_public' => false,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDays(2),
    ]);
    $post->devices()->attach($device->id);

    $resolver = new KioskPostResolver();
    $mediaItems = $resolver->resolveForDevice($device);

    $titles = array_column($mediaItems, 'title');
    expect($titles)->toContain('Charla Interdepartamental');
});
