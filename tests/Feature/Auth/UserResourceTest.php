<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Notifications\UserInvitation;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
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
                'role' => 'editor',
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

    public function test_admin_cannot_see_super_admins_in_the_users_table(): void
    {
        $super = $this->superAdmin();
        $ordinary = User::factory()->create();
        $ordinary->assignRole('editor');

        $this->actingAs($this->admin());

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$ordinary])
            ->assertCanNotSeeTableRecords([$super]);
    }

    public function test_search_and_filter_cannot_reveal_a_super_admin_to_an_admin(): void
    {
        $super = $this->superAdmin();
        $super->forceFill(['name' => 'Findable Supervisor'])->save();

        $this->actingAs($this->admin());

        // Searching the exact name must still find nothing: the exclusion
        // lives in the query, so it survives search and filtering.
        Livewire::test(ListUsers::class)
            ->searchTable('Findable Supervisor')
            ->assertCanNotSeeTableRecords([$super]);

        Livewire::test(ListUsers::class)
            ->filterTable('status', UserStatus::Active->value)
            ->assertCanNotSeeTableRecords([$super]);
    }

    public function test_active_super_admin_sees_ordinary_users_and_other_super_admins(): void
    {
        $otherSuper = $this->superAdmin();
        $ordinary = User::factory()->create();
        $ordinary->assignRole('editor');

        $this->actingAs($this->superAdmin());

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$ordinary, $otherSuper]);
    }

    /**
     * A non-super actor who genuinely CAN view users — otherwise the
     * exclusion would be indistinguishable from having no access at all.
     */
    public function test_non_super_viewer_with_users_view_still_cannot_see_super_admins(): void
    {
        $super = $this->superAdmin();
        $ordinary = User::factory()->create();
        $ordinary->assignRole('editor');

        $viewer = User::factory()->create();
        $viewer->assignRole('editor');
        $viewer->givePermissionTo(['access_admin', 'users.view']);

        $this->actingAs($viewer);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$ordinary])
            ->assertCanNotSeeTableRecords([$super]);

        $this->assertTrue(Gate::forUser($viewer)->allows('view', $ordinary));
        $this->assertTrue(Gate::forUser($viewer)->denies('view', $super));
    }

    public function test_admin_cannot_open_a_super_admin_edit_route_directly(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($this->admin());

        // Record route binding runs through the scoped resource query, so
        // the record resolves to nothing rather than returning 403.
        $this->get(UserResource::getUrl('edit', ['record' => $super]))->assertNotFound();
    }

    public function test_forged_mount_of_a_super_admin_edit_page_is_refused(): void
    {
        $admin = $this->admin();
        $super = $this->superAdmin();
        $originalName = $super->name;

        $this->actingAs($admin);

        // Naming the record directly is the forgery: it never passes through
        // the table query. Binding resolves nothing, so the page cannot even
        // mount — asserted on the EXACT exception rather than a broad catch.
        $this->assertThrows(
            fn () => Livewire::test(EditUser::class, ['record' => $super->getRouteKey()]),
            ModelNotFoundException::class,
        );

        // The policy refuses independently of route binding, so an actor who
        // somehow obtained the record still cannot act on it.
        $this->assertTrue(Gate::forUser($admin)->denies('view', $super));
        $this->assertTrue(Gate::forUser($admin)->denies('update', $super));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $super));
        $this->assertTrue(Gate::forUser($admin)->denies('manageRoles', $super));

        $fresh = $super->fresh();

        $this->assertNotNull($fresh, 'the super admin must still exist');
        $this->assertSame($originalName, $fresh->name);
        $this->assertSame(['super_admin'], $fresh->getRoleNames()->all());
    }

    public function test_ordinary_user_management_is_unchanged_for_an_admin(): void
    {
        $admin = $this->admin();
        $ordinary = User::factory()->create();
        $ordinary->assignRole('editor');

        $this->assertTrue(Gate::forUser($admin)->allows('view', $ordinary));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $ordinary));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $ordinary));
        $this->assertTrue(Gate::forUser($admin)->allows('manageRoles', $ordinary));
    }

    public function test_role_field_is_a_required_single_select_not_a_multi_select(): void
    {
        $this->actingAs($this->admin());

        $component = Livewire::test(CreateUser::class);

        // The field is named 'role', not 'roles': a field named after
        // Spatie's real BelongsToMany relationship can render single-select
        // while still round-tripping array state.
        $component->assertFormFieldDoesNotExist('roles');

        // The callback form hands back the real field instance, so this
        // asserts the actual configuration rather than the rendered markup.
        $component->assertFormFieldExists('role', function (Select $field): bool {
            $this->assertFalse($field->isMultiple(), 'the role select must not be multiple');
            $this->assertTrue($field->isRequired(), 'exactly one role is required');

            return true;
        });
    }

    public function test_creating_a_user_without_a_role_is_rejected(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Roleless Person',
                'email' => 'roleless@example.com',
            ])
            ->call('create')
            ->assertHasFormErrors(['role']);

        $this->assertSame(0, User::query()->where('email', 'roleless@example.com')->count());
    }

    public function test_created_user_holds_exactly_one_role(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Single Role',
                'email' => 'single-role@example.com',
                'role' => 'admin',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::query()->where('email', 'single-role@example.com')->sole();

        $this->assertSame(['admin'], $created->getRoleNames()->all());
        $this->assertSame(1, $created->roles()->count());
    }

    public function test_editing_replaces_the_existing_role_instead_of_appending(): void
    {
        $this->actingAs($this->admin());

        $target = User::factory()->create();
        $target->assignRole('editor');

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['role' => 'admin'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $target->fresh();

        $this->assertSame(['admin'], $fresh->getRoleNames()->all());
        $this->assertSame(1, $fresh->roles()->count());
        $this->assertFalse($fresh->hasRole('editor'));
    }

    public function test_edit_form_hydrates_the_current_role_as_a_scalar(): void
    {
        $this->actingAs($this->admin());

        $target = User::factory()->create();
        $target->assignRole('editor');

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormSet(['role' => 'editor']);
    }

    public function test_edit_page_updates_name(): void
    {
        $this->actingAs($this->admin());
        // Every user holds exactly one role; the edit form hydrates from it
        // with sole(), so a roleless fixture is invalid data, not a shortcut.
        $target = User::factory()->create();
        $target->assignRole('editor');

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
            ->fillForm(['role' => 'admin'])
            ->call('save')
            ->assertHasErrors(['data.role']);

        $this->assertTrue($super->fresh()->hasRole('super_admin'));
    }

    public function test_super_admin_can_be_demoted_when_another_active_one_remains(): void
    {
        $super = $this->superAdmin();
        $this->superAdmin();
        $this->actingAs($this->superAdmin());

        Livewire::test(EditUser::class, ['record' => $super->getRouteKey()])
            ->fillForm(['role' => 'admin'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $super->fresh();

        $this->assertFalse($fresh->hasRole('super_admin'));
        $this->assertTrue($fresh->hasRole('admin'));
    }

    public function test_role_changes_without_manage_roles_are_not_applied(): void
    {
        // users.update but NOT users.manage_roles: the role field is hidden
        // and not dehydrated, so no role change can be submitted.
        $limited = User::factory()->create();
        $limited->givePermissionTo(['access_admin', 'users.view', 'users.update']);
        $this->actingAs($limited);

        $target = User::factory()->create();
        $target->assignRole('editor');

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['role' => 'admin'])
            ->call('save');

        $this->assertSame(['editor'], $target->fresh()->getRoleNames()->all());
    }

    public function test_invited_account_status_cannot_change_through_edit(): void
    {
        // The status field is disabled for invited records and therefore not
        // dehydrated: activation stays exclusive to AcceptInvitation.
        $this->actingAs($this->admin());
        $invited = User::factory()->invited()->create();
        $invited->assignRole('editor');

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
        $target->assignRole('editor');

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->callAction('delete');

        $this->assertNull(User::query()->find($target->getKey()));
    }
}
