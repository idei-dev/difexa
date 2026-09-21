<?php

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Device;
use App\Models\Post;
use App\Models\User;
use App\Services\Post\KioskPostResolver;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->author = User::factory()->create();

    $this->mathUnit = UsimUnit::firstOrCreate(
        ['slug' => 'exactas-matematica'],
        ['name' => 'Departamento de Matemática', 'type' => 'academic']
    );

    $this->physicsUnit = UsimUnit::firstOrCreate(
        ['slug' => 'exactas-fisica'],
        ['name' => 'Departamento de Física', 'type' => 'academic']
    );

    $this->resolver = new KioskPostResolver();
});

it('resolves active approved posts for a unit-paired kiosk device', function () {
    $device = Device::create([
        'name' => 'Kiosk Aula Magna Matemática',
    ]);
    $device->usimUnits()->attach($this->mathUnit->id);

    // Active post for Math
    Post::factory()->approved()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->mathUnit->id,
        'title' => 'Seminario de Álgebra',
        'type' => PostType::IMAGE,
        'media_url' => 'https://example.com/math.jpg',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDays(2),
        'display_duration_sec' => 12,
    ]);

    // Active post for Physics (should NOT appear on Math device)
    Post::factory()->approved()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->physicsUnit->id,
        'title' => 'Coloquio de Mecánica Cuántica',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDays(2),
    ]);

    // Inactive (draft) post for Math (should NOT appear)
    Post::factory()->draft()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->mathUnit->id,
        'title' => 'Borrador no aprobado',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDays(2),
    ]);

    $items = $this->resolver->resolveForDevice($device);

    expect($items)->toHaveCount(1)
        ->and($items[0]['title'])->toBe('Seminario de Álgebra')
        ->and($items[0]['url'])->toBe('https://example.com/math.jpg')
        ->and($items[0]['duration_ms'])->toBe(12000);
});

it('resolves public posts on public devices', function () {
    $publicDevice = Device::create([
        'name' => 'Kiosk Entrada Principal',
    ]);

    Post::factory()->approved()->publicPost()->create([
        'user_id' => $this->author->id,
        'unit_id' => $this->mathUnit->id,
        'title' => 'Inscripciones Abiertas 2026',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDays(10),
    ]);

    $items = $this->resolver->resolveForDevice($publicDevice);

    expect($items)->toHaveCount(1)
        ->and($items[0]['title'])->toBe('Inscripciones Abiertas 2026');
});

it('returns fallback items when no active posts are found', function () {
    $device = Device::create(['name' => 'Kiosk Vacío']);

    $items = $this->resolver->resolveForDevice($device);

    expect($items)->toHaveCount(1)
        ->and($items[0]['id'])->toBe('fallback_welcome');
});

