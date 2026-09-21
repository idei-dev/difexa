<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Kiosk ' . fake()->city(),
            'pairing_pin' => (string) fake()->numberBetween(1000, 9999),
            'device_token' => fake()->uuid(),
            'specs' => [
                'aspect_ratio' => '16:9',
                'has_sound' => true,
                'resolution' => '1920x1080',
            ],
        ];
    }
}

