<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The Gate override and UserPolicy, evaluated exactly as Filament evaluates
 * them: the active-only super-admin bypass, the users.* permission map, and
 * the protective denials (self-delete, last active super admin, bulk paths).
 */
class AuthorizationTest extends AdminTestCase
{
    public function test_active_super_admin_passes_any_ability(): void
    {
        $super = $this->superAdmin();

        $this->assertTrue($super->can('users.delete'));
        $this->assertTrue($super->can('access_admin'));
        $this->assertTrue($super->can('anything.at.all'));
    }

    public function test_inactive_super_admin_is_denied(): void
    {
        $user = User::factory()->inactive()->create();
        $user->assignRole('super_admin');

        $this->assertFalse($user->can('users.delete'));
        $this->assertFalse($user->can('access_admin'));
    }

    public function test_invited_super_admin_is_denied(): void
    {
        $user = User::factory()->invited()->create();
        $user->assignRole('super_admin');

        $this->assertFalse($user->can('users.view'));
    }

    public function test_admin_role_holds_all_user_permissions(): void
    {
        $admin = $this->admin();

        foreach (['users.view', 'users.create', 'users.update', 'users.delete', 'users.manage_roles'] as $permission) {
            $this->assertTrue($admin->can($permission), "admin should hold {$permission}");
        }
    }

    public function test_editor_cannot_manage_users(): void
    {
        $editor = $this->editor();

        $this->assertTrue($editor->can('access_admin'));
        $this->assertFalse($editor->can('users.view'));
        $this->assertFalse($editor->can('users.delete'));
    }

    public function test_policy_denies_self_deletion(): void
    {
        $admin = $this->admin();

        $this->assertTrue(Gate::forUser($admin)->denies('delete', $admin));
    }

    public function test_policy_denies_deleting_last_active_super_admin(): void
    {
        $admin = $this->admin();
        $lastSuper = $this->superAdmin();

        $this->assertTrue(Gate::forUser($admin)->denies('delete', $lastSuper));
    }

    /**
     * Deliberately inverted: super administrators are now invisible AND
     * unmanageable to non-super actors, so "another active super admin
     * remains" no longer unlocks deletion for an admin. Only another active
     * super admin may do it, via the Gate::before override.
     */
    public function test_policy_denies_admin_deleting_any_super_admin(): void
    {
        $admin = $this->admin();
        $superA = $this->superAdmin();
        $otherSuper = $this->superAdmin();

        $this->assertTrue(Gate::forUser($admin)->denies('delete', $superA));

        // The same target IS deletable by another active super admin.
        $this->assertTrue(Gate::forUser($otherSuper)->allows('delete', $superA));
    }

    public function test_policy_denies_bulk_and_structural_abilities_even_for_admin(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->denies('deleteAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->denies('forceDelete', $target));
        $this->assertTrue(Gate::forUser($admin)->denies('restore', $target));
        $this->assertTrue(Gate::forUser($admin)->denies('reorder', User::class));
        $this->assertTrue(Gate::forUser($admin)->denies('replicate', $target));
    }

    public function test_inactive_admin_is_denied_despite_role_permissions(): void
    {
        $user = User::factory()->inactive()->create();
        $user->assignRole('admin');

        $this->assertTrue(Gate::forUser($user)->denies('viewAny', User::class));
        $this->assertTrue(Gate::forUser($user)->denies('manageRoles', User::class));
    }

    public function test_manage_roles_requires_permission(): void
    {
        $admin = $this->admin();
        $editor = $this->editor();
        $target = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('manageRoles', $target));
        $this->assertTrue(Gate::forUser($editor)->denies('manageRoles', $target));
    }
}
