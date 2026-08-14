<?php

namespace Tests\Feature\Auth;

use App\Actions\InviteUser;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * The full invitation lifecycle against PostgreSQL: hashing, expiry,
 * single-use tokens, authorization, duplicate handling, resend renewal,
 * indistinguishable 404s, password validation, and rate limiting.
 */
class InvitationFlowTest extends AdminTestCase
{
    /**
     * Invite via the real action and extract the plaintext token from the
     * queued notification's own mail URL — the only place it exists.
     *
     * @return array{0: User, 1: string}
     */
    private function inviteAndCaptureToken(string $role = 'editor'): array
    {
        Notification::fake();

        $invited = app(InviteUser::class)->invite(
            $this->admin(),
            'Invitee Example',
            'invitee@example.com',
            $role,
        );

        $token = '';

        Notification::assertSentTo(
            $invited,
            UserInvitation::class,
            function (UserInvitation $notification) use ($invited, &$token): bool {
                $url = $notification->toMail($invited)->actionUrl;
                $token = Str::afterLast($url, '/');

                return $token !== '';
            },
        );

        return [$invited->fresh(), $token];
    }

    public function test_invite_creates_invited_user_with_hashed_token_and_expiry(): void
    {
        [$invited, $token] = $this->inviteAndCaptureToken('editor');

        $this->assertSame(UserStatus::Invited, $invited->status);
        $this->assertNull($invited->email_verified_at);
        $this->assertSame(hash('sha256', $token), $invited->invitation_token_hash);
        $this->assertTrue($invited->invitation_expires_at->isFuture());
        $this->assertEqualsWithDelta(
            now()->addDays(config('platform.invitation_expiry_days'))->getTimestamp(),
            $invited->invitation_expires_at->getTimestamp(),
            5,
        );
        $this->assertSame(['editor'], $invited->getRoleNames()->all());
    }

    public function test_invite_normalizes_email(): void
    {
        Notification::fake();

        $invited = app(InviteUser::class)->invite($this->admin(), 'X', '  MiXeD@Example.COM ', 'editor');

        $this->assertSame('mixed@example.com', $invited->email);
    }

    public function test_duplicate_email_is_an_email_validation_error(): void
    {
        Notification::fake();
        $existing = User::factory()->create();

        try {
            app(InviteUser::class)->invite($this->admin(), 'X', $existing->email, 'editor');
            $this->fail('Expected ValidationException for duplicate email.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
    }

    public function test_editor_cannot_invite(): void
    {
        Notification::fake();

        $this->expectException(AuthorizationException::class);

        app(InviteUser::class)->invite($this->editor(), 'X', 'new@example.com', 'editor');
    }

    public function test_inactive_admin_cannot_invite_despite_role_permissions(): void
    {
        Notification::fake();
        $inactive = User::factory()->inactive()->create();
        $inactive->assignRole('admin');

        $this->expectException(AuthorizationException::class);

        app(InviteUser::class)->invite($inactive, 'X', 'new@example.com', 'editor');
    }

    public function test_assigning_roles_requires_manage_roles_permission(): void
    {
        Notification::fake();
        $creator = User::factory()->create();
        $creator->givePermissionTo('users.create');

        $this->expectException(AuthorizationException::class);

        app(InviteUser::class)->invite($creator, 'X', 'new@example.com', 'editor');
    }

    public function test_unknown_role_is_a_roles_validation_error(): void
    {
        Notification::fake();

        try {
            app(InviteUser::class)->invite($this->admin(), 'X', 'new@example.com', 'nonexistent');
            $this->fail('Expected ValidationException for unknown role.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
    }

    public function test_malformed_role_values_are_rejected(): void
    {
        Notification::fake();

        try {
            app(InviteUser::class)->invite($this->admin(), 'X', 'new@example.com', '');
            $this->fail('Expected ValidationException for malformed role value.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
    }

    public function test_accept_page_renders_for_valid_token(): void
    {
        [$invited, $token] = $this->inviteAndCaptureToken();

        $response = $this->get($this->adminHost.'/invitation/'.$token);

        $response->assertOk();
        $response->assertSee($invited->email);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_unknown_token_is_404(): void
    {
        $this->get($this->adminHost.'/invitation/'.Str::random(64))->assertNotFound();
    }

    public function test_malformed_token_is_404_at_routing(): void
    {
        $this->get($this->adminHost.'/invitation/short')->assertNotFound();
    }

    public function test_expired_token_is_404(): void
    {
        [$invited, $token] = $this->inviteAndCaptureToken();

        $invited->forceFill(['invitation_expires_at' => now()->subMinute()])->save();

        $this->get($this->adminHost.'/invitation/'.$token)->assertNotFound();
    }

    public function test_accept_activates_account_and_clears_token(): void
    {
        [$invited, $token] = $this->inviteAndCaptureToken();
        $oldRememberToken = $invited->remember_token;

        $response = $this->post($this->adminHost.'/invitation/'.$token, [
            'password' => 'brand-new-passw0rd',
            'password_confirmation' => 'brand-new-passw0rd',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));

        $invited->refresh();

        $this->assertSame(UserStatus::Active, $invited->status);
        $this->assertNotNull($invited->email_verified_at);
        $this->assertNull($invited->invitation_token_hash);
        $this->assertNull($invited->invitation_expires_at);
        $this->assertNotSame($oldRememberToken, $invited->remember_token);
        $this->assertTrue(Hash::check('brand-new-passw0rd', $invited->password));
    }

    public function test_token_reuse_after_acceptance_is_404(): void
    {
        [, $token] = $this->inviteAndCaptureToken();

        $this->post($this->adminHost.'/invitation/'.$token, [
            'password' => 'brand-new-passw0rd',
            'password_confirmation' => 'brand-new-passw0rd',
        ])->assertRedirect();

        $this->get($this->adminHost.'/invitation/'.$token)->assertNotFound();
        $this->post($this->adminHost.'/invitation/'.$token, [
            'password' => 'another-passw0rd-9',
            'password_confirmation' => 'another-passw0rd-9',
        ])->assertNotFound();
    }

    public function test_weak_password_is_rejected_and_account_stays_invited(): void
    {
        [$invited, $token] = $this->inviteAndCaptureToken();

        $response = $this->from($this->adminHost.'/invitation/'.$token)
            ->post($this->adminHost.'/invitation/'.$token, [
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertSessionHasErrors('password');

        $this->assertSame(UserStatus::Invited, $invited->fresh()->status);
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        [, $token] = $this->inviteAndCaptureToken();

        $this->from($this->adminHost.'/invitation/'.$token)
            ->post($this->adminHost.'/invitation/'.$token, [
                'password' => 'brand-new-passw0rd',
                'password_confirmation' => 'different-passw0rd',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_resend_renews_token_and_invalidates_previous_link(): void
    {
        Notification::fake();
        [$invited, $oldToken] = $this->inviteAndCaptureToken();
        $oldHash = $invited->invitation_token_hash;

        app(InviteUser::class)->resend($this->admin(), $invited);

        $invited->refresh();

        $this->assertNotSame($oldHash, $invited->invitation_token_hash);
        $this->get($this->adminHost.'/invitation/'.$oldToken)->assertNotFound();
    }

    public function test_resend_refuses_non_invited_users(): void
    {
        Notification::fake();
        $active = User::factory()->create();

        $this->expectException(LogicException::class);

        app(InviteUser::class)->resend($this->admin(), $active);
    }

    public function test_invitation_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get($this->adminHost.'/invitation/'.Str::random(64))->assertNotFound();
        }

        $this->get($this->adminHost.'/invitation/'.Str::random(64))->assertStatus(429);
    }
}
