<?php

namespace Tests\Feature\Auth;

use App\Actions\CreateSuperAdmin;
use App\Enums\UserStatus;
use App\Models\User;
use LogicException;
use Spatie\Permission\Models\Role;

/**
 * users:create-super-admin — the only way the FIRST super administrator is
 * created; refuses to run twice; and the underlying action leaves no orphan
 * user behind when the guarded create is refused.
 */
class CreateSuperAdminCommandTest extends AdminTestCase
{
    public function test_creates_an_active_verified_super_admin(): void
    {
        $this->artisan('users:create-super-admin')
            ->expectsQuestion('Name', 'Root Admin')
            ->expectsQuestion('Email', 'root@example.com')
            ->expectsQuestion('Password', 'first-passw0rd-123')
            ->expectsQuestion('Confirm password', 'first-passw0rd-123')
            ->assertSuccessful();

        $user = User::query()->where('email', 'root@example.com')->sole();

        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('super_admin'));
    }

    public function test_refuses_when_a_super_admin_already_exists(): void
    {
        $this->superAdmin();

        $this->artisan('users:create-super-admin')->assertFailed();
    }

    public function test_password_mismatch_creates_no_user(): void
    {
        $this->artisan('users:create-super-admin')
            ->expectsQuestion('Name', 'Root Admin')
            ->expectsQuestion('Email', 'root@example.com')
            ->expectsQuestion('Password', 'first-passw0rd-123')
            ->expectsQuestion('Confirm password', 'different-passw0rd')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'root@example.com']);
    }

    public function test_action_refuses_second_super_admin_and_leaves_no_orphan(): void
    {
        $this->superAdmin();
        $role = Role::findByName('super_admin', 'web');
        $usersBefore = User::query()->count();

        try {
            app(CreateSuperAdmin::class)('Another', 'another@example.com', 'another-passw0rd-1', $role);
            $this->fail('Expected LogicException for a second super administrator.');
        } catch (LogicException) {
            // Expected.
        }

        $this->assertSame($usersBefore, User::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'another@example.com']);
    }
}
