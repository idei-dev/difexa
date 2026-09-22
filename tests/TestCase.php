<?php

namespace Tests;

use AllowDynamicProperties;
use App\Models\User;
use App\Services\Post\PostListingService;
use App\Services\Post\PostService;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Traits\UsimTestHelpers;

/**
 * @property User $user
 * @property User $author
 * @property User $authorA
 * @property User $authorB
 * @property User $reviewer
 * @property UsimUnit $unit
 * @property UsimUnit $mainUnit
 * @property UsimUnit $unitQuimica
 * @property UsimUnit $unitFisica
 * @property UsimUnit $unitIdei
 * @property UsimUnit $commUnit
 * @property UsimUnit $otherUnit
 * @property UsimUnit $unitA
 * @property UsimUnit $unitB
 * @property UsimUnit $mathUnit
 * @property UsimUnit $physicsUnit
 * @property \App\Models\Device $smartTv1
 * @property \App\Models\Device $smartTv2
 * @property \App\Models\Device $institutionalTv
 * @property \App\Models\Device $regularDevice
 * @property Role $smartTvRole
 * @property Role $otherRole
 * @property Role $role
 * @property Permission $permission
 * @property PostListingService|PostService $service
 * @property \App\Services\Post\KioskPostResolver $resolver
 */
#[AllowDynamicProperties]
abstract class TestCase extends BaseTestCase
{
    use UsimTestHelpers;
    use RefreshDatabase;
}
