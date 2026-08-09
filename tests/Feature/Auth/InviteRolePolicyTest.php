<?php

namespace Tests\Feature\Auth;

use App\Actions\InviteUser;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Actor-dependent super_admin visibility and assignment in the INVITE flow:
 * UI exclusion, translated form-rule rejection, and the authoritative
 * domain guard — the edit flow is deliberately untouched.
 */
class InviteRolePolicyTest extends AdminTestCase
{
    public function test_super_admin_sees_and_can_assign_super_admin(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin());

        $page = $this->get($this->adminHost.'/users/create');
        $page->assertOk();
        $page->assertSee(__('users.roles.super_admin.label'));
        $page->assertSee(__('users.roles.super_admin.description'));
        $page->assertSee(__('users.roles_info.heading'));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Second Super',
                'email' => 'second-super@example.com',
                'roles' => ['super_admin'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invited = User::query()->where('email', 'second-super@example.com')->sole();
        $this->assertTrue($invited->hasRole('super_admin'));
    }

    public function test_admin_does_not_see_super_admin_role_or_explanation(): void
    {
        $this->actingAs($this->admin());

        $page = $this->get($this->adminHost.'/users/create');
        $page->assertOk();
        $page->assertDontSee('super_admin');
        $page->assertDontSee(__('users.roles.super_admin.label'));
        $page->assertDontSee(__('users.roles.super_admin.description'));
    }

    public function test_admin_still_sees_available_role_explanations(): void
    {
        $this->actingAs($this->admin());

        $page = $this->get($this->adminHost.'/users/create');
        $page->assertOk();
        $page->assertSee(__('users.roles_info.heading'));
        $page->assertSee(__('users.roles.admin.label'));
        $page->assertSee(__('users.roles.editor.description'));
    }

    public function test_forged_livewire_payload_is_rejected_as_a_roles_field_error(): void
    {
        Notification::fake();
        $this->actingAs($this->admin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Sneaky Invite',
                'email' => 'sneaky@example.com',
                'roles' => ['super_admin'],
            ])
            ->call('create')
            ->assertHasFormErrors(['roles']);

        $this->assertSame(0, User::query()->where('email', 'sneaky@example.com')->count());
        Notification::assertNothingSent();
    }

    public function test_forged_direct_action_call_is_rejected_authoritatively(): void
    {
        Notification::fake();

        try {
            app(InviteUser::class)->invite($this->admin(), 'Sneaky', 'sneaky@example.com', ['super_admin']);
            $this->fail('Expected AuthorizationException for super_admin invite by admin.');
        } catch (AuthorizationException) {
            // Expected: the domain guard is authoritative.
        }

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
        Notification::assertNothingSent();
    }

    public function test_admin_can_still_invite_an_editor(): void
    {
        Notification::fake();
        $this->actingAs($this->admin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Normal Invite',
                'email' => 'editor-invite@example.com',
                'roles' => ['editor'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(User::query()->where('email', 'editor-invite@example.com')->sole()->hasRole('editor'));
    }

    public function test_role_translation_keys_exist_in_both_locales(): void
    {
        foreach (['en', 'ar'] as $locale) {
            foreach (['super_admin', 'admin', 'editor'] as $role) {
                $this->assertTrue(
                    Lang::has("users.roles.{$role}.label", $locale),
                    "missing users.roles.{$role}.label [{$locale}]",
                );
                $this->assertTrue(
                    Lang::has("users.roles.{$role}.description", $locale),
                    "missing users.roles.{$role}.description [{$locale}]",
                );
            }

            $this->assertTrue(
                Lang::has('users.guards.super_admin_invite', $locale),
                "missing users.guards.super_admin_invite [{$locale}]",
            );
            $this->assertTrue(
                Lang::has('users.roles_info.heading', $locale),
                "missing users.roles_info.heading [{$locale}]",
            );
        }
    }
}
