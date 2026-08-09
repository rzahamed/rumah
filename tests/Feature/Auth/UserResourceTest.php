<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * UserResource behavior over real Livewire requests: permission-gated
 * access, the invite-on-create flow, and the save-path guards that bind
 * every actor (invited-status immutability, last-active-super-admin
 * protection, role-change authorization, delete before-hooks).
 */
class UserResourceTest extends AdminTestCase
{
    public function test_admin_can_view_users_index(): void
    {
        $this->actingAs($this->admin());

        $this->get($this->adminHost.'/users')->assertOk();
    }

    public function test_editor_cannot_view_users_index(): void
    {
        $this->actingAs($this->editor());

        $this->get($this->adminHost.'/users')->assertForbidden();
    }

    public function test_admin_can_view_invite_page(): void
    {
        $this->actingAs($this->admin());

        $this->get($this->adminHost.'/users/create')->assertOk();
    }

    public function test_create_page_invites_a_user(): void
    {
        Notification::fake();
        $this->actingAs($this->admin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Member',
                'email' => 'member@example.com',
                'roles' => ['editor'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invited = User::query()->where('email', 'member@example.com')->sole();

        $this->assertSame(UserStatus::Invited, $invited->status);
        $this->assertNotNull($invited->invitation_token_hash);
        $this->assertSame(['editor'], $invited->getRoleNames()->all());

        Notification::assertSentTo($invited, UserInvitation::class);
    }

    public function test_create_page_rejects_duplicate_email(): void
    {
        Notification::fake();
        $existing = User::factory()->create();
        $this->actingAs($this->admin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Member',
                'email' => $existing->email,
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);

        Notification::assertNothingSent();
    }

    public function test_edit_page_updates_name(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['name' => 'Renamed Person'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed Person', $target->fresh()->name);
    }

    public function test_last_active_super_admin_cannot_be_deactivated(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);

        Livewire::test(EditUser::class, ['record' => $super->getRouteKey()])
            ->fillForm(['status' => UserStatus::Inactive->value])
            ->call('save')
            ->assertHasErrors(['data.status']);

        $this->assertSame(UserStatus::Active, $super->fresh()->status);
    }

    public function test_last_active_super_admin_must_keep_super_admin_role(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);

        Livewire::test(EditUser::class, ['record' => $super->getRouteKey()])
            ->fillForm(['roles' => ['admin']])
            ->call('save')
            ->assertHasErrors(['data.roles']);

        $this->assertTrue($super->fresh()->hasRole('super_admin'));
    }

    public function test_super_admin_can_be_demoted_when_another_active_one_remains(): void
    {
        $super = $this->superAdmin();
        $this->superAdmin();
        $this->actingAs($this->superAdmin());

        Livewire::test(EditUser::class, ['record' => $super->getRouteKey()])
            ->fillForm(['roles' => ['admin']])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $super->fresh();

        $this->assertFalse($fresh->hasRole('super_admin'));
        $this->assertTrue($fresh->hasRole('admin'));
    }

    public function test_role_changes_without_manage_roles_are_not_applied(): void
    {
        // users.update but NOT users.manage_roles: the roles field is hidden
        // and not dehydrated, so no role change can be submitted.
        $limited = User::factory()->create();
        $limited->givePermissionTo(['access_admin', 'users.view', 'users.update']);
        $this->actingAs($limited);

        $target = User::factory()->create();
        $target->assignRole('editor');

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => ['admin']])
            ->call('save');

        $this->assertSame(['editor'], $target->fresh()->getRoleNames()->all());
    }

    public function test_invited_account_status_cannot_change_through_edit(): void
    {
        // The status field is disabled for invited records and therefore not
        // dehydrated: activation stays exclusive to AcceptInvitation.
        $this->actingAs($this->admin());
        $invited = User::factory()->invited()->create();

        Livewire::test(EditUser::class, ['record' => $invited->getRouteKey()])
            ->fillForm(['name' => 'Still Invited'])
            ->call('save');

        $this->assertSame(UserStatus::Invited, $invited->fresh()->status);
    }

    public function test_delete_action_refuses_self_deletion(): void
    {
        // A SECOND active super admin exists, so the target is not the last
        // one — the guard that fires must be the self-delete guard.
        $super = $this->superAdmin();
        $this->superAdmin();
        $this->actingAs($super);

        Livewire::test(EditUser::class, ['record' => $super->getRouteKey()])
            ->callAction('delete')
            ->assertNotified(__('users.guards.self_delete'));

        $this->assertNotNull(User::query()->find($super->getKey()));
    }

    public function test_delete_action_refuses_last_active_super_admin(): void
    {
        // The ONLY active super admin deletes themself: the policy is
        // bypassed by Gate::before, both hook guards apply, and the
        // last-super guard (checked first) must be the one that fires.
        $lastSuper = $this->superAdmin();
        $this->actingAs($lastSuper);

        Livewire::test(EditUser::class, ['record' => $lastSuper->getRouteKey()])
            ->callAction('delete')
            ->assertNotified(__('users.guards.last_super_admin_delete'));

        $this->assertNotNull(User::query()->find($lastSuper->getKey()));
    }

    public function test_delete_succeeds_for_ordinary_target(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->callAction('delete');

        $this->assertNull(User::query()->find($target->getKey()));
    }
}
