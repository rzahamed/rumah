<?php

namespace Tests\Feature\Content;

use App\Enums\UserStatus;
use App\Filament\Resources\FormSubmissionResource;
use App\Filament\Resources\FormSubmissionResource\Pages\ListFormSubmissions;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\SubmissionNotificationRecipient;
use App\Models\User;
use App\Notifications\NewFormSubmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Feature\Auth\AdminTestCase;
use Tests\Support\InteractsWithContactForm;

/**
 * Notification recipients and delivery.
 *
 * The security boundary under test: a non-super actor must never see,
 * count, select, or destroy-by-accident a super-admin recipient, and every
 * submitted id is re-validated server-side against that actor's own
 * eligible query.
 */
class SubmissionNotificationTest extends AdminTestCase
{
    use InteractsWithContactForm;

    private function eligible(string $role = 'admin'): User
    {
        return $this->activeUserWithRole($role);
    }

    private function submitPublicForm(): void
    {
        $this->genericContactForm();

        $this->from($this->publicHost)
            ->post($this->publicHost.'/forms/contact', [
                'name' => 'Visitor',
                'email' => 'visitor@example.com',
                'message' => 'Hello there.',
            ])
            ->assertSessionHasNoErrors();
    }

    // ---- Queue contract --------------------------------------------------

    public function test_the_notification_is_queued_and_dispatched_after_commit(): void
    {
        $submission = FormSubmission::factory()->create();
        $notification = new NewFormSubmission($submission);

        // Routing tests alone would not prove either property.
        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertTrue(
            $notification->afterCommit,
            'a rolled-back submission must never be announced',
        );
    }

    // ---- Eligibility and visibility --------------------------------------

    public function test_a_super_admin_sees_ordinary_users_and_other_super_admins(): void
    {
        $super = $this->superAdmin();
        $otherSuper = $this->superAdmin();
        $admin = $this->eligible('admin');

        $ids = SubmissionNotificationRecipient::eligibleQuery($super)->pluck('id')->all();

        $this->assertContains($otherSuper->getKey(), $ids);
        $this->assertContains($admin->getKey(), $ids);
    }

    public function test_a_non_super_actor_cannot_see_or_count_super_admins(): void
    {
        $super = $this->superAdmin();
        $admin = $this->eligible('admin');
        $colleague = $this->eligible('admin');

        $ids = SubmissionNotificationRecipient::eligibleQuery($admin)->pluck('id')->all();

        $this->assertNotContains($super->getKey(), $ids);
        $this->assertContains($colleague->getKey(), $ids);
        $this->assertSame(
            count($ids),
            SubmissionNotificationRecipient::eligibleQuery($admin)->count(),
        );
    }

    public function test_inactive_and_invited_users_are_not_eligible(): void
    {
        $admin = $this->eligible('admin');

        $inactive = User::factory()->inactive()->create();
        $inactive->assignRole('admin');

        $invited = User::factory()->invited()->create();
        $invited->assignRole('admin');

        $ids = SubmissionNotificationRecipient::eligibleQuery($admin)->pluck('id')->all();

        $this->assertNotContains($inactive->getKey(), $ids);
        $this->assertNotContains($invited->getKey(), $ids);
    }

    public function test_a_user_without_panel_access_is_not_eligible(): void
    {
        $admin = $this->eligible('admin');
        $outsider = User::factory()->create();

        $ids = SubmissionNotificationRecipient::eligibleQuery($admin)->pluck('id')->all();

        $this->assertNotContains($outsider->getKey(), $ids);
    }

    // ---- Saving ----------------------------------------------------------

    public function test_recipients_persist_without_duplicates(): void
    {
        $admin = $this->eligible('admin');
        $colleague = $this->eligible('admin');

        SubmissionNotificationRecipient::sync(
            [$colleague->getKey(), $colleague->getKey()],
            $admin,
        );

        $this->assertSame(1, SubmissionNotificationRecipient::query()->count());
        $this->assertSame(
            [$colleague->getKey()],
            SubmissionNotificationRecipient::selectedIdsFor($admin),
        );
    }

    public function test_a_forged_super_admin_id_is_rejected_and_nothing_is_stored(): void
    {
        $super = $this->superAdmin();
        $admin = $this->eligible('admin');
        $colleague = $this->eligible('admin');

        $this->assertThrows(
            fn () => SubmissionNotificationRecipient::sync(
                [$colleague->getKey(), $super->getKey()],
                $admin,
            ),
            ValidationException::class,
        );

        // Atomic: the legitimate id in the same payload is not stored either.
        $this->assertSame(0, SubmissionNotificationRecipient::query()->count());
    }

    public function test_malformed_and_ineligible_ids_are_rejected(): void
    {
        $admin = $this->eligible('admin');

        $inactive = User::factory()->inactive()->create();
        $inactive->assignRole('admin');

        foreach ([['not-a-number'], [null], [1.5], [$inactive->getKey()], [999999]] as $payload) {
            $this->assertThrows(
                fn () => SubmissionNotificationRecipient::sync($payload, $admin),
                ValidationException::class,
            );
        }

        $this->assertSame(0, SubmissionNotificationRecipient::query()->count());
    }

    public function test_a_non_super_actor_cannot_remove_a_hidden_super_admin_recipient(): void
    {
        $super = $this->superAdmin();
        $admin = $this->eligible('admin');
        $colleague = $this->eligible('admin');

        SubmissionNotificationRecipient::sync([$super->getKey(), $colleague->getKey()], $super);
        $this->assertSame(2, SubmissionNotificationRecipient::query()->count());

        $this->assertSame([$colleague->getKey()], SubmissionNotificationRecipient::selectedIdsFor($admin));

        // Saving an EMPTY visible list must not delete the hidden row as a
        // side effect of being unable to see it.
        SubmissionNotificationRecipient::sync([], $admin);

        $this->assertSame(1, SubmissionNotificationRecipient::query()->count());
        $this->assertTrue(
            SubmissionNotificationRecipient::query()->where('user_id', $super->getKey())->exists(),
        );
    }

    public function test_a_super_admin_can_remove_another_super_admin_recipient(): void
    {
        $super = $this->superAdmin();
        $otherSuper = $this->superAdmin();

        SubmissionNotificationRecipient::sync([$otherSuper->getKey()], $super);
        $this->assertSame(1, SubmissionNotificationRecipient::query()->count());

        SubmissionNotificationRecipient::sync([], $super);

        $this->assertSame(0, SubmissionNotificationRecipient::query()->count());
    }

    // ---- Authorization ---------------------------------------------------

    public function test_only_actors_with_the_permission_may_configure_recipients(): void
    {
        $admin = $this->eligible('admin');
        $editor = $this->editor();

        $viewer = User::factory()->create();
        $viewer->assignRole('editor');
        $viewer->givePermissionTo(['access_admin', 'submissions.view']);

        $this->assertTrue(Gate::forUser($admin)->allows('manageNotifications', FormSubmission::class));
        $this->assertTrue(Gate::forUser($editor)->denies('manageNotifications', FormSubmission::class));
        // Reading submissions is NOT the same privilege as choosing who is
        // emailed collected personal data.
        $this->assertTrue(Gate::forUser($viewer)->denies('manageNotifications', FormSubmission::class));
    }

    public function test_a_forged_configure_call_is_refused_and_changes_nothing(): void
    {
        $admin = $this->eligible('admin');
        $keep = $this->eligible('admin');
        SubmissionNotificationRecipient::sync([$keep->getKey()], $admin);

        $viewer = User::factory()->create();
        $viewer->assignRole('editor');
        $viewer->givePermissionTo(['access_admin', 'submissions.view']);

        $this->actingAs($viewer);

        $component = Livewire::test(ListFormSubmissions::class);
        $component->assertActionHidden('configureNotifications');

        $refused = false;

        try {
            $component->callAction('configureNotifications', ['recipients' => [$viewer->getKey()]]);
        } catch (ExpectationFailedException) {
            $refused = true;
        }

        $this->assertTrue($refused, 'A hidden unauthorized action must not be callable.');

        // The stored list is byte-for-byte what it was.
        $this->assertSame(1, SubmissionNotificationRecipient::query()->count());
        $this->assertSame(
            [$keep->getKey()],
            SubmissionNotificationRecipient::selectedIdsFor($admin),
        );
    }

    public function test_an_authorized_actor_saves_recipients_through_the_action(): void
    {
        $admin = $this->eligible('admin');
        $colleague = $this->eligible('admin');

        $this->actingAs($admin);

        Livewire::test(ListFormSubmissions::class)
            ->callAction('configureNotifications', ['recipients' => [$colleague->getKey()]])
            ->assertHasNoActionErrors();

        $this->assertSame(
            [$colleague->getKey()],
            SubmissionNotificationRecipient::selectedIdsFor($admin),
        );
    }

    public function test_a_forged_hidden_id_through_the_action_is_rejected_and_rows_are_unchanged(): void
    {
        $super = $this->superAdmin();
        $admin = $this->eligible('admin');
        $keep = $this->eligible('admin');

        SubmissionNotificationRecipient::sync([$keep->getKey()], $admin);

        $this->actingAs($admin);

        // The UI never offered this id; submitting it anyway must fail the
        // whole save rather than silently drop it.
        Livewire::test(ListFormSubmissions::class)
            ->callAction('configureNotifications', [
                'recipients' => [$keep->getKey(), $super->getKey()],
            ])
            ->assertHasActionErrors();

        $this->assertSame(1, SubmissionNotificationRecipient::query()->count());
        $this->assertSame(
            [$keep->getKey()],
            SubmissionNotificationRecipient::selectedIdsFor($admin),
        );
    }

    // ---- Delivery --------------------------------------------------------

    public function test_one_notification_is_queued_per_configured_recipient(): void
    {
        Notification::fake();

        $admin = $this->eligible('admin');
        $colleague = $this->eligible('admin');

        SubmissionNotificationRecipient::sync([$admin->getKey(), $colleague->getKey()], $admin);

        $this->submitPublicForm();

        Notification::assertSentTo($admin, NewFormSubmission::class);
        Notification::assertSentTo($colleague, NewFormSubmission::class);
        Notification::assertCount(2);
    }

    public function test_no_recipients_means_no_notifications(): void
    {
        Notification::fake();

        $this->submitPublicForm();

        Notification::assertNothingSent();
        $this->assertSame(1, FormSubmission::query()->count());
    }

    public function test_removing_a_recipient_stops_future_notifications(): void
    {
        Notification::fake();

        $admin = $this->eligible('admin');
        $removed = $this->eligible('admin');

        SubmissionNotificationRecipient::sync([$removed->getKey()], $admin);
        SubmissionNotificationRecipient::sync([], $admin);

        $this->submitPublicForm();

        Notification::assertNothingSent();
    }

    public function test_a_deactivated_recipient_is_skipped_at_send_time(): void
    {
        Notification::fake();

        $admin = $this->eligible('admin');
        $later = $this->eligible('admin');

        SubmissionNotificationRecipient::sync([$later->getKey()], $admin);

        // Eligibility is re-applied at SEND time, so nobody has to remember
        // to prune the list when an account is deactivated.
        $later->forceFill(['status' => UserStatus::Inactive])->save();

        $this->submitPublicForm();

        Notification::assertNothingSent();
    }

    public function test_a_configured_super_admin_still_receives_notifications(): void
    {
        Notification::fake();

        $super = $this->superAdmin();
        SubmissionNotificationRecipient::sync([$super->getKey()], $super);

        $this->submitPublicForm();

        // System delivery is actor-independent: hiding super admins from a
        // non-super ADMINISTRATOR must not stop them being emailed.
        Notification::assertSentTo($super, NewFormSubmission::class);
    }

    public function test_the_email_carries_the_admin_view_url_and_a_safe_summary(): void
    {
        Notification::fake();

        $admin = $this->eligible('admin');
        SubmissionNotificationRecipient::sync([$admin->getKey()], $admin);

        $this->submitPublicForm();

        $submission = FormSubmission::query()->sole();
        $expectedUrl = FormSubmissionResource::getUrl('view', ['record' => $submission], isAbsolute: true);

        Notification::assertSentTo($admin, NewFormSubmission::class, function ($notification) use ($admin, $expectedUrl): bool {
            $mail = $notification->toMail($admin);
            $rendered = implode(' ', array_merge($mail->introLines, $mail->outroLines));

            // Compared against the resource's own absolute URL rather than a
            // hard-coded host, so environment config stays authoritative.
            $this->assertSame($expectedUrl, $mail->actionUrl);
            $this->assertStringContainsString('Visitor', $rendered);
            $this->assertStringContainsString('visitor@example.com', $rendered);

            // Requester metadata has never been collected and must not be
            // invented for the email.
            $this->assertStringNotContainsString('127.0.0.1', $rendered);
            $this->assertStringNotContainsString('Mozilla', $rendered);
            // The full message body stays in the panel.
            $this->assertStringNotContainsString('Hello there.', $rendered);

            return true;
        });
    }

    public function test_each_recipient_receives_their_own_locale_including_option_labels(): void
    {
        $form = Form::factory()->create([
            'slug' => 'locale-'.uniqid(),
            'fields' => [[
                'name' => 'service_type',
                'type' => 'select',
                'required' => true,
                'label' => ['en' => 'Service Type', 'ar' => 'نوع الخدمة'],
                'options' => [[
                    'value' => 'general_inquiry',
                    'label' => ['en' => 'General Inquiry', 'ar' => 'استفسار عام'],
                ]],
            ]],
        ]);

        $submission = FormSubmission::factory()->create([
            'form_id' => $form->getKey(),
            'payload' => ['service_type' => 'general_inquiry'],
        ]);

        $english = $this->eligible('admin');
        $english->forceFill(['preferred_admin_locale' => 'en'])->save();

        $arabic = $this->eligible('admin');
        $arabic->forceFill(['preferred_admin_locale' => 'ar'])->save();

        $notification = new NewFormSubmission($submission);

        // Global locale set to the OPPOSITE of each recipient, proving the
        // explicit recipient locale is authoritative rather than ambient.
        app()->setLocale('ar');
        $englishMail = implode(' ', $notification->toMail($english)->introLines);

        app()->setLocale('en');
        $arabicMail = implode(' ', $notification->toMail($arabic)->introLines);

        $this->assertStringContainsString('General Inquiry', $englishMail);
        $this->assertStringContainsString(__('content.submissions.email.intro', [], 'en'), $englishMail);

        $this->assertStringContainsString('استفسار عام', $arabicMail);
        $this->assertStringContainsString(__('content.submissions.email.intro', [], 'ar'), $arabicMail);
    }

    public function test_a_queue_failure_does_not_break_the_public_submission(): void
    {
        // No Notification::fake(): make the real dispatch throw.
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('queue down'));

        $this->genericContactForm();

        $this->from($this->publicHost)
            ->post($this->publicHost.'/forms/contact', [
                'name' => 'Visitor',
                'email' => 'visitor@example.com',
                'message' => 'Hello there.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('content.forms.submitted'));

        // Persisted exactly once despite the failure.
        $this->assertSame(1, FormSubmission::query()->count());
    }
}
