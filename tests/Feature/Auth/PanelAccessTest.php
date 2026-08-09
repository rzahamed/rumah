<?php

namespace Tests\Feature\Auth;

use App\Models\User;

/**
 * canAccessPanel() enforcement through Filament's middleware on real HTTP
 * requests: BOTH an active status AND access_admin (directly or via the
 * active-super-admin Gate override) are required — and each failure mode is
 * a 403, never a silent pass.
 */
class PanelAccessTest extends AdminTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get($this->adminHost.'/');

        $response->assertStatus(302);
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    public function test_active_super_admin_can_access_panel(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get($this->adminHost.'/')->assertOk();
    }

    public function test_active_admin_can_access_panel(): void
    {
        $this->actingAs($this->admin());

        $this->get($this->adminHost.'/')->assertOk();
    }

    public function test_active_editor_can_access_panel(): void
    {
        $this->actingAs($this->editor());

        $this->get($this->adminHost.'/')->assertOk();
    }

    public function test_inactive_admin_is_forbidden(): void
    {
        $user = User::factory()->inactive()->create();
        $user->assignRole('admin');

        $this->actingAs($user);

        $this->get($this->adminHost.'/')->assertForbidden();
    }

    public function test_invited_user_is_forbidden(): void
    {
        $user = User::factory()->invited()->create();
        $user->assignRole('admin');

        $this->actingAs($user);

        $this->get($this->adminHost.'/')->assertForbidden();
    }

    public function test_active_user_without_access_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get($this->adminHost.'/')->assertForbidden();
    }

    public function test_inactive_super_admin_is_forbidden(): void
    {
        $user = User::factory()->inactive()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user);

        $this->get($this->adminHost.'/')->assertForbidden();
    }
}
