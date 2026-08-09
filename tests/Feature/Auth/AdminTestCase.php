<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base for the PostgreSQL-backed admin/auth suite: migrates and seeds the
 * dedicated test database (guarded by TestDatabaseGuard), provides the host
 * constants and role-bearing user helpers every test needs, and sets the
 * explicitly resolved admin panel as current so Filament Livewire pages can
 * be tested directly.
 */
abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $publicHost = 'http://basecms.test';

    protected string $adminHost = 'http://admin.basecms.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function activeUserWithRole(string $role): User
    {
        $user = User::factory()->create();

        $user->assignRole($role);

        return $user;
    }

    protected function superAdmin(): User
    {
        return $this->activeUserWithRole('super_admin');
    }

    protected function admin(): User
    {
        return $this->activeUserWithRole('admin');
    }

    protected function editor(): User
    {
        return $this->activeUserWithRole('editor');
    }
}
