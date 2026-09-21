<?php

namespace App\Contracts;

use App\Models\Device;

interface KioskPostResolverContract
{
    /**
     * Resolve active, approved media items for a specific kiosk device.
     *
     * @param Device $device
     * @return list<array{id: string, kind: string, url: string, mime: string, title: string, duration_ms: int}>
     */
    public function resolveForDevice(Device $device): array;

    /**
     * Resolve active, approved media items for a specific academic unit.
     *
     * @param int $unitId
     * @return list<array{id: string, kind: string, url: string, mime: string, title: string, duration_ms: int}>
     */
    public function resolveForUnit(int $unitId): array;

    /**
     * Resolve public active media items available for all public/lobby kiosks.
     *
     * @return list<array{id: string, kind: string, url: string, mime: string, title: string, duration_ms: int}>
     */
    public function resolvePublic(): array;
}

