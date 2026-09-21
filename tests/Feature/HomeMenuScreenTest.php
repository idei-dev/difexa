<?php

use App\Models\User;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;

it('returns home screen with expected core components', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $landing = $ui->component('landing_lbl');
    $landing->expect('type')->toBe('label');
    expect($landing->data()['html'] ?? '')->toContain('Difexa');

    $ui->assertNoIssues();
});

it('returns menu screen for guests with settings trigger and register option', function () {
    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);

    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();

    expect($mainMenu['type'] ?? null)->toBe('menudropdown');
    expect(menuItemsContainLabel($mainMenu['items'] ?? [], 'Home'))->toBeTrue();
    expect(menuItemsContainLabel($mainMenu['items'] ?? [], 'About'))->toBeTrue();

    expect($userMenu['type'] ?? null)->toBe('menudropdown');
    expect($userMenu['trigger']['label'] ?? null)->toBe('⚙️');
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Register'))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Logout'))->toBeFalse();

    $ui->assertNoIssues();
});

it('returns menu screen for authenticated users with user trigger and logout option', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'name' => 'Menu Tester',
    ]);

    $this->actingAs($user);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $userMenu = $ui->component('user_menu')->data();

    expect($userMenu['type'] ?? null)->toBe('menudropdown');
    expect((string) ($userMenu['trigger']['label'] ?? ''))->toContain('Menu Tester');
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Logout'))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Register'))->toBeFalse();

    $ui->assertNoIssues();
});
