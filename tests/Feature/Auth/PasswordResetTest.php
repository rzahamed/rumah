<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Anti-enumeration on the password-reset request page: registered,
 * unregistered, and registered-but-unauthorized emails all produce the
 * identical success notification, while reset mail goes ONLY to real,
 * panel-authorized accounts.
 */
class PasswordResetTest extends AdminTestCase
{
    public function test_request_page_renders_on_admin_host(): void
    {
        $this->get($this->adminHost.'/password-reset/request')->assertOk();
    }

    public function test_registered_and_unregistered_emails_get_identical_responses(): void
    {
        Notification::fake();

        $admin = $this->admin();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $admin->email])
            ->call('request')
            ->assertNotified(__('passwords.sent'));

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'nobody@example.com'])
            ->call('request')
            ->assertNotified(__('passwords.sent'));

        Notification::assertSentTo($admin, ResetPasswordNotification::class);
    }

    public function test_unregistered_email_sends_no_mail(): void
    {
        Notification::fake();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'nobody@example.com'])
            ->call('request')
            ->assertNotified(__('passwords.sent'));

        Notification::assertNothingSent();
    }

    public function test_registered_but_unauthorized_account_gets_success_but_no_mail(): void
    {
        Notification::fake();

        // Active but no access_admin: canAccessPanel() fails, the broker's
        // send callback skips mailing, the response stays the success one.
        $user = User::factory()->create();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request')
            ->assertNotified(__('passwords.sent'));

        Notification::assertNotSentTo($user, ResetPasswordNotification::class);
    }
}
