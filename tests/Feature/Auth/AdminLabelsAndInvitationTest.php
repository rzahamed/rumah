<?php

namespace Tests\Feature\Auth;

use App\Actions\InviteUser;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Support\Facades\Notification;

/**
 * Display-label and invitation-copy corrections.
 *
 * The editor rename is presentation ONLY: the database role machine name,
 * its permissions and every policy check must be untouched.
 */
class AdminLabelsAndInvitationTest extends AdminTestCase
{
    public function test_the_editor_role_displays_as_content_editor_in_both_locales(): void
    {
        $this->assertSame('Content Editor', __('users.roles.editor.label', [], 'en'));
        $this->assertSame('محرر المحتوى', __('users.roles.editor.label', [], 'ar'));
    }

    public function test_the_editor_machine_name_and_permissions_are_unchanged(): void
    {
        $editor = $this->editor();

        // The stored role name is what every policy and permission grant
        // keys on; only the label moved.
        $this->assertSame(['editor'], $editor->getRoleNames()->all());
        $this->assertTrue($editor->can('access_admin'));
        $this->assertTrue($editor->can('posts.view'));
        // Still no access to collected data or user administration.
        $this->assertFalse($editor->can('submissions.view'));
        $this->assertFalse($editor->can('users.view'));
    }

    public function test_the_renamed_label_appears_in_the_users_table(): void
    {
        $this->editor();
        $this->actingAs($this->admin());

        $this->get($this->adminHost.'/users')
            ->assertOk()
            ->assertSee('Content Editor');
    }

    public function test_the_invitation_email_uses_manage_wording_and_the_configured_app_name(): void
    {
        Notification::fake();

        $invited = app(InviteUser::class)->invite(
            $this->admin(),
            'New Manager',
            'manager@example.com',
            'editor',
        );

        Notification::assertSentTo($invited, UserInvitation::class, function (UserInvitation $notification) use ($invited): bool {
            $mail = $notification->toMail($invited);
            $body = implode(' ', array_merge($mail->introLines, $mail->outroLines));
            // Whatever the brand is configured to be — asserted by identity,
            // not by excluding particular strings, which would contradict
            // the requirement to use the configured name.
            $appName = config('platform.brand_name');

            $this->assertSame(__('invitations.email.subject', ['app' => $appName], 'en'), $mail->subject);
            $this->assertStringContainsString('manage', $mail->subject);
            $this->assertStringContainsString($appName, $mail->subject);

            $this->assertStringContainsString('invited to manage', $body);
            $this->assertStringContainsString($appName, $body);
            $this->assertStringContainsString('Set a password using the button below', $body);

            // The superseded wording must be gone.
            $this->assertStringNotContainsString('invited to join', $body);

            // The secure activation link survives the copy change.
            $this->assertNotNull($mail->actionUrl);

            return true;
        });
    }

    public function test_the_arabic_invitation_copy_carries_the_same_meaning(): void
    {
        $subject = __('invitations.email.subject', ['app' => 'X'], 'ar');
        $intro = __('invitations.email.intro', ['app' => 'X'], 'ar');

        $this->assertStringContainsString('لإدارة', $subject);
        $this->assertStringContainsString('لإدارة', $intro);
        // The old "join" wording is gone from Arabic too.
        $this->assertStringNotContainsString('للانضمام', $intro);
    }

    public function test_users_declare_a_locale_preference_for_queued_mail(): void
    {
        // Queued notifications resolve __() on the WORKER, so without this
        // contract neither the sender's nor the recipient's locale applies.
        // This asserts the CONTRACT and its fallbacks; Laravel's own queued
        // handoff is framework behaviour, not re-tested here.
        $user = User::factory()->create();

        $this->assertInstanceOf(HasLocalePreference::class, $user);

        // No stored preference yet (a brand-new invitee): the configured
        // default applies rather than the worker's ambient locale.
        $this->assertNull($user->preferred_admin_locale);
        $this->assertSame(config('platform.default_locale'), $user->preferredLocale());

        $user->forceFill(['preferred_admin_locale' => 'ar'])->save();
        $this->assertSame('ar', $user->fresh()->preferredLocale());

        // An unsupported value is ignored rather than trusted. Set in memory
        // only: persisting it is not required to prove the fallback, and the
        // column may legitimately refuse such a value.
        $user->preferred_admin_locale = 'zz';
        $this->assertSame(config('platform.default_locale'), $user->preferredLocale());
    }
}
