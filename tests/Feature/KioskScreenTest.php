<?php

use App\Enums\PostType;
use App\Models\Device;
use App\Models\Post;
use App\Models\User;
use App\Services\Device\DeviceService;
use App\UI\Screens\Device\KioskScreen;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $permission = Permission::firstOrCreate([
        'name' => 'device.kiosk_screen.access',
        'guard_name' => 'device',
    ]);

    $role = Role::firstOrCreate([
        'name' => 'smart_tv',
        'guard_name' => 'device',
    ]);
    $role->givePermissionTo($permission);

    $this->unitIdei = UsimUnit::firstOrCreate(
        ['slug' => 'idei'],
        ['name' => 'Instituto de Informática', 'type' => 'institute']
    );

    $this->unitQuimica = UsimUnit::firstOrCreate(
        ['slug' => 'quimica'],
        ['name' => 'Departamento de Química', 'type' => 'academic']
    );

    $this->author = User::factory()->create();
});

it('redirects unauthenticated requests to device pairing screen', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = getScreenJson($this, KioskScreen::class);
    $uiResponse->assertOk();
    expect($uiResponse->json('redirect'))->toBeString()->toContain('/device/device-pairing-screen');
});

it('renders dynamic post carousel for authenticated unit device', function () {
    /** @var \Tests\TestCase $this */
    /** @var DeviceService $deviceService */
    $deviceService = app(DeviceService::class);
    $device = $deviceService->createDevice([
        'name' => 'Totem Hall Idei',
        'unit_id' => $this->unitIdei->id,
        'roles' => ['smart_tv'],
    ]);

    $post = Post::factory()->approved()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unitIdei->id,
        'title' => 'Conferencia Internacional de Computación 2026',
        'type' => PostType::IMAGE,
        'media_url' => 'https://example.com/poster-idei.jpg',
        'display_duration_sec' => 12,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDays(5),
    ]);

    $this->actingAs($device, 'device');

    $ui = uiScenario($this, KioskScreen::class, ['reset' => true]);

    $carousel = $ui->component('device_carousel');
    $carousel->expect('type')->toBe('carousel');

    $ui->assertNoIssues();

    $uiResponse = getScreenJson($this, KioskScreen::class);
    $uiResponse->assertOk();
    $data = $uiResponse->json();
    $carouselComponent = findComponentByName($data, 'device_carousel');

    expect($carouselComponent)->not->toBeNull()
        ->and($carouselComponent['current_media']['title'] ?? null)->toBe('Conferencia Internacional de Computación 2026')
        ->and($carouselComponent['current_media']['url'] ?? null)->toBe('https://example.com/poster-idei.jpg')
        ->and($carouselComponent['current_media']['duration_ms'] ?? null)->toBe(12000);
});

it('cycles through posts on carousel_tick event', function () {
    /** @var \Tests\TestCase $this */
    /** @var DeviceService $deviceService */
    $deviceService = app(DeviceService::class);
    $device = $deviceService->createDevice([
        'name' => 'Totem Hall Idei',
        'unit_id' => $this->unitIdei->id,
        'roles' => ['smart_tv'],
    ]);

    $post1 = Post::factory()->approved()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unitIdei->id,
        'title' => 'Post 1 - Informatica',
        'type' => PostType::IMAGE,
        'media_url' => 'https://example.com/slide1.jpg',
        'display_duration_sec' => 5,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(2),
    ]);

    $post2 = Post::factory()->approved()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unitIdei->id,
        'title' => 'Post 2 - Informatica',
        'type' => PostType::IMAGE,
        'media_url' => 'https://example.com/slide2.jpg',
        'display_duration_sec' => 10,
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->addDays(2),
    ]);

    $this->actingAs($device, 'device');

    $ui = uiScenario($this, KioskScreen::class, ['reset' => true]);

    $response = $ui->action('device_carousel', 'carousel_tick', [
        'carousel_name' => 'device_carousel',
    ]);

    $response->assertOk();

    $ui->component('device_carousel')->expect('current_index')->toBe(1);
});

it('displays fallback media item when no active posts are present', function () {
    /** @var \Tests\TestCase $this */
    /** @var DeviceService $deviceService */
    $deviceService = app(DeviceService::class);
    $device = $deviceService->createDevice([
        'name' => 'Totem Hall Idei',
        'unit_id' => $this->unitIdei->id,
        'roles' => ['smart_tv'],
    ]);

    $this->actingAs($device, 'device');

    $uiResponse = getScreenJson($this, KioskScreen::class);
    $uiResponse->assertOk();
    $data = $uiResponse->json();
    $carouselComponent = findComponentByName($data, 'device_carousel');

    expect($carouselComponent)->not->toBeNull()
        ->and($carouselComponent['current_media']['id'] ?? null)->toBe('fallback_welcome');
});

it('isolates unit posts ensuring kiosks only display their own and public posts', function () {
    /** @var \Tests\TestCase $this */
    /** @var DeviceService $deviceService */
    $deviceService = app(DeviceService::class);
    $device = $deviceService->createDevice([
        'name' => 'Totem Idei',
        'unit_id' => $this->unitIdei->id,
        'roles' => ['smart_tv'],
    ]);

    // Unit Idei Post (Private to unit)
    $postIdei = Post::factory()->approved()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unitIdei->id,
        'title' => 'Noticia Exclusiva Idei',
        'is_public' => false,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDays(1),
    ]);

    // Unit Quimica Post (Private to Quimica)
    $postQuimica = Post::factory()->approved()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unitQuimica->id,
        'title' => 'Noticia Privada Quimica',
        'is_public' => false,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDays(1),
    ]);

    // Public Post from Quimica
    $postPublic = Post::factory()->approved()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->unitQuimica->id,
        'title' => 'Feria de Ciencias Publica',
        'is_public' => true,
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->addDays(1),
    ]);

    $this->actingAs($device, 'device');

    $uiResponse = getScreenJson($this, KioskScreen::class);
    $uiResponse->assertOk();
    $data = $uiResponse->json();
    $carouselComponent = findComponentByName($data, 'device_carousel');

    expect($carouselComponent)->not->toBeNull()
        ->and($carouselComponent['current_media']['title'] ?? null)->toBe('Noticia Exclusiva Idei');
});

