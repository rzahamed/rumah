<?php

namespace Tests\Feature\Content;

use App\Enums\SubmissionStatus;
use App\Filament\Resources\FormSubmissionResource;
use App\Filament\Resources\FormSubmissionResource\Pages\ListFormSubmissions;
use App\Filament\Resources\FormSubmissionResource\Pages\ViewFormSubmission;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Feature\Auth\AdminTestCase;

/**
 * Submission triage: the status workflow, its narrowly scoped authorization,
 * and the read-only record page.
 *
 * The payload itself remains permanently uneditable — the only mutation
 * anywhere in this resource is the status transition, behind its own
 * `updateStatus` ability rather than the generic `update` one.
 */
class SubmissionTriageTest extends AdminTestCase
{
    /** @param  array<string, mixed>  $payload */
    private function submission(array $payload = ['name' => 'Triage Person']): FormSubmission
    {
        $form = Form::factory()->create(['slug' => 'triage-'.uniqid()]);

        return FormSubmission::factory()->create([
            'form_id' => $form->getKey(),
            'payload' => $payload,
        ]);
    }

    /**
     * Drive the page's status action the way the UI does, asserting the
     * action actually completed — otherwise a silently rejected action would
     * leave later assertions passing against an unchanged value.
     */
    private function setStatus(FormSubmission $submission, SubmissionStatus $status): void
    {
        Livewire::test(ViewFormSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('updateStatus', ['status' => $status->value])
            ->assertHasNoActionErrors();
    }

    // ---- Migration default ----------------------------------------------

    public function test_submissions_default_to_new_with_no_review_metadata(): void
    {
        $submission = $this->submission();

        $this->assertSame(SubmissionStatus::New, $submission->fresh()->status);
        $this->assertNull($submission->fresh()->reviewed_at);
        $this->assertNull($submission->fresh()->reviewed_by);
    }

    public function test_the_database_rejects_a_status_outside_the_enum(): void
    {
        $submission = $this->submission();

        // The CHECK constraint is authoritative even for a raw update that
        // bypasses Eloquent entirely.
        $this->assertThrows(
            fn () => DB::table('form_submissions')
                ->where('id', $submission->getKey())
                ->update(['status' => 'not_a_status']),
            QueryException::class,
        );
    }

    // ---- Transitions -----------------------------------------------------

    public function test_entering_reviewed_stamps_the_actor_and_time(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');

        $admin = $this->admin();
        $this->actingAs($admin);

        $submission = $this->submission();
        $this->setStatus($submission, SubmissionStatus::Reviewed);

        $fresh = $submission->fresh();

        $this->assertSame(SubmissionStatus::Reviewed, $fresh->status);
        $this->assertSame('2026-08-13 10:00:00', $fresh->reviewed_at->toDateTimeString());
        $this->assertSame($admin->getKey(), $fresh->reviewed_by);
    }

    public function test_reviewed_to_archived_retains_the_reviewer_and_timestamp(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');

        $admin = $this->admin();
        $this->actingAs($admin);

        $submission = $this->submission();
        $this->setStatus($submission, SubmissionStatus::Reviewed);

        Carbon::setTestNow('2026-08-13 12:00:00');
        $this->setStatus($submission->fresh(), SubmissionStatus::Archived);

        $fresh = $submission->fresh();

        $this->assertSame(SubmissionStatus::Archived, $fresh->status);
        // Unchanged: the review genuinely happened at 10:00.
        $this->assertSame('2026-08-13 10:00:00', $fresh->reviewed_at->toDateTimeString());
        $this->assertSame($admin->getKey(), $fresh->reviewed_by);
    }

    public function test_new_to_archived_leaves_review_metadata_null(): void
    {
        $this->actingAs($this->admin());

        $submission = $this->submission();
        $this->setStatus($submission, SubmissionStatus::Archived);

        $fresh = $submission->fresh();

        $this->assertSame(SubmissionStatus::Archived, $fresh->status);
        $this->assertNull($fresh->reviewed_at);
        $this->assertNull($fresh->reviewed_by);
    }

    public function test_returning_to_new_clears_review_metadata(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');
        $this->actingAs($this->admin());

        $submission = $this->submission();
        $this->setStatus($submission, SubmissionStatus::Reviewed);
        $this->setStatus($submission->fresh(), SubmissionStatus::New);

        $fresh = $submission->fresh();

        $this->assertSame(SubmissionStatus::New, $fresh->status);
        $this->assertNull($fresh->reviewed_at);
        $this->assertNull($fresh->reviewed_by);
    }

    public function test_archived_to_reviewed_restamps_with_the_current_actor(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');

        $first = $this->admin();
        $this->actingAs($first);

        $submission = $this->submission();
        $this->setStatus($submission, SubmissionStatus::Reviewed);
        $this->setStatus($submission->fresh(), SubmissionStatus::Archived);

        // A different administrator revisits it later.
        Carbon::setTestNow('2026-08-14 09:30:00');
        $second = $this->admin();
        $this->actingAs($second);

        $this->setStatus($submission->fresh(), SubmissionStatus::Reviewed);

        $fresh = $submission->fresh();

        $this->assertSame(SubmissionStatus::Reviewed, $fresh->status);
        $this->assertSame('2026-08-14 09:30:00', $fresh->reviewed_at->toDateTimeString());
        $this->assertSame($second->getKey(), $fresh->reviewed_by);
    }

    /**
     * Re-applying Reviewed is deliberately NOT a no-op: it records the most
     * recent review, including a different reviewer. "Who last checked this,
     * and when" is the useful reading of the field.
     */
    public function test_reapplying_reviewed_restamps_with_the_current_actor_and_time(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');
        $first = $this->admin();
        $this->actingAs($first);

        $submission = $this->submission();
        $this->setStatus($submission, SubmissionStatus::Reviewed);

        Carbon::setTestNow('2026-08-13 11:00:00');
        $second = $this->admin();
        $this->actingAs($second);

        $this->setStatus($submission->fresh(), SubmissionStatus::Reviewed);

        $fresh = $submission->fresh();

        $this->assertSame(SubmissionStatus::Reviewed, $fresh->status);
        $this->assertSame('2026-08-13 11:00:00', $fresh->reviewed_at->toDateTimeString());
        $this->assertSame($second->getKey(), $fresh->reviewed_by);
        $this->assertNotSame($first->getKey(), $fresh->reviewed_by);
    }

    // ---- Authorization ---------------------------------------------------

    public function test_viewing_a_submission_never_marks_it_reviewed(): void
    {
        $this->actingAs($this->admin());
        $submission = $this->submission();

        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))->assertOk();

        $this->assertSame(SubmissionStatus::New, $submission->fresh()->status);
        $this->assertNull($submission->fresh()->reviewed_at);
    }

    public function test_update_status_is_authorized_while_generic_update_stays_denied(): void
    {
        $admin = $this->admin();
        $submission = $this->submission();

        $this->assertTrue(Gate::forUser($admin)->allows('updateStatus', $submission));
        // Reusing the generic ability would authorize editing the payload.
        $this->assertTrue(Gate::forUser($admin)->denies('update', $submission));
        $this->assertTrue(Gate::forUser($admin)->denies('create', FormSubmission::class));
    }

    public function test_an_actor_without_submissions_update_cannot_change_status(): void
    {
        // Can read collected data, but not triage it: separate privileges.
        $viewer = User::factory()->create();
        $viewer->assignRole('editor');
        $viewer->givePermissionTo(['access_admin', 'submissions.view']);

        $submission = $this->submission();

        $this->assertTrue(Gate::forUser($viewer)->denies('updateStatus', $submission));

        $this->actingAs($viewer);

        Livewire::test(ViewFormSubmission::class, ['record' => $submission->getRouteKey()])
            ->assertActionHidden('updateStatus');

        $this->assertSame(SubmissionStatus::New, $submission->fresh()->status);
    }

    public function test_a_forged_status_action_call_is_refused_and_changes_nothing(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('editor');
        $viewer->givePermissionTo(['access_admin', 'submissions.view']);

        $submission = $this->submission(['name' => 'Untouched Person', 'email' => 'untouched@example.com']);
        $originalPayload = $submission->payload;

        $this->actingAs($viewer);

        /*
         * TWO independent defences; this asserts the observable result of
         * both:
         *
         *  1. Filament refuses to mount an action the actor cannot see, so
         *     the call never reaches the handler. Its test helper surfaces
         *     that as an assertion failure rather than a domain exception —
         *     established by probing the real behaviour, not assumed.
         *  2. Gate::authorize('updateStatus') inside the action covers any
         *     request that bypasses mounting; that denial is asserted above.
         */
        $component = Livewire::test(ViewFormSubmission::class, [
            'record' => $submission->getRouteKey(),
        ]);

        $component->assertActionHidden('updateStatus');

        $refused = false;

        try {
            $component->callAction('updateStatus', [
                'status' => SubmissionStatus::Reviewed->value,
            ]);
        } catch (ExpectationFailedException) {
            $refused = true;
        }

        $this->assertTrue(
            $refused,
            'A hidden unauthorized status action must not be callable.',
        );

        $fresh = $submission->fresh();

        $this->assertSame(SubmissionStatus::New, $fresh->status);
        $this->assertNull($fresh->reviewed_at);
        $this->assertNull($fresh->reviewed_by);
        $this->assertSame($originalPayload, $fresh->payload);
    }

    public function test_an_editor_cannot_open_the_submission_pages_at_all(): void
    {
        $this->actingAs($this->editor());
        $submission = $this->submission();

        $this->get(FormSubmissionResource::getUrl('index'))->assertForbidden();
        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))->assertForbidden();
    }

    public function test_no_create_or_edit_route_exists(): void
    {
        $this->assertArrayNotHasKey('create', FormSubmissionResource::getPages());
        $this->assertArrayNotHasKey('edit', FormSubmissionResource::getPages());
        $this->assertFalse(FormSubmissionResource::canCreate());
    }

    // ---- Table -----------------------------------------------------------

    public function test_the_table_shows_contact_identity_and_supports_search(): void
    {
        $this->actingAs($this->admin());

        $wanted = $this->submission([
            'name' => 'Searchable Person',
            'email' => 'searchable@example.com',
            'phone' => '0559999999',
        ]);

        $other = $this->submission(['name' => 'Unrelated Person']);

        Livewire::test(ListFormSubmissions::class)
            ->assertCanSeeTableRecords([$wanted, $other])
            ->searchTable('Searchable')
            ->assertCanSeeTableRecords([$wanted])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_table_filters_by_status(): void
    {
        $this->actingAs($this->admin());

        $archived = $this->submission(['name' => 'Archived One']);
        $archived->applyStatus(SubmissionStatus::Archived, $this->admin());

        $untriaged = $this->submission(['name' => 'New One']);

        Livewire::test(ListFormSubmissions::class)
            ->filterTable('status', SubmissionStatus::Archived->value)
            ->assertCanSeeTableRecords([$archived])
            ->assertCanNotSeeTableRecords([$untriaged]);
    }

    public function test_the_table_sorts_by_received_date_in_both_directions(): void
    {
        $this->actingAs($this->admin());

        $older = $this->submission(['name' => 'Older Submission']);
        $older->forceFill(['created_at' => '2026-01-01 09:00:00'])->save();

        $newer = $this->submission(['name' => 'Newer Submission']);
        $newer->forceFill(['created_at' => '2026-06-01 09:00:00'])->save();

        // inOrder: without it this would only prove visibility, not order.
        Livewire::test(ListFormSubmissions::class)
            ->sortTable('created_at')
            ->assertCanSeeTableRecords([$older, $newer], inOrder: true);

        Livewire::test(ListFormSubmissions::class)
            ->sortTable('created_at', 'desc')
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_missing_optional_values_render_the_placeholder_not_an_error(): void
    {
        $this->actingAs($this->admin());

        // Only a name: no email, phone or interest anywhere in the payload.
        $sparse = $this->submission(['name' => 'Only A Name']);

        Livewire::test(ListFormSubmissions::class)
            ->assertCanSeeTableRecords([$sparse])
            ->assertSuccessful();

        $this->get(FormSubmissionResource::getUrl('view', ['record' => $sparse]))
            ->assertOk()
            ->assertSee('—');
    }

    // ---- View page -------------------------------------------------------

    public function test_the_view_page_shows_every_submitted_field(): void
    {
        $this->actingAs($this->admin());

        $form = Form::factory()->create([
            'slug' => 'view-'.uniqid(),
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Full Name']],
                ['name' => 'message', 'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Message']],
                ['name' => 'referral_source', 'type' => 'text', 'required' => false, 'label' => ['en' => 'How did you hear about us']],
            ],
        ]);

        $submission = FormSubmission::factory()->create([
            'form_id' => $form->getKey(),
            'payload' => [
                'name' => 'Complete Person',
                'message' => 'A detailed description.',
                // Maps to no summary column, but must still be displayed.
                'referral_source' => 'A colleague recommended you',
            ],
        ]);

        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSee('Complete Person')
            ->assertSee('A detailed description.')
            ->assertSee('How did you hear about us')
            ->assertSee('A colleague recommended you');
    }

    public function test_submitted_html_is_escaped_and_never_rendered_as_markup(): void
    {
        $this->actingAs($this->admin());

        $form = Form::factory()->create([
            'slug' => 'xss-'.uniqid(),
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'required' => true, 'label' => ['en' => '<b>Bold label</b>']],
                ['name' => 'message', 'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Details']],
            ],
        ]);

        $submission = FormSubmission::factory()->create([
            'form_id' => $form->getKey(),
            'payload' => [
                'name' => '<script>alert("xss")</script>',
                'message' => "Line one\nLine two <img src=x onerror=alert(1)>",
            ],
        ]);

        $html = $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->getContent();

        // Executable markup must never reach the document.
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringNotContainsString('<b>Bold label</b>', $html);

        // The text is present, escaped.
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Bold label', $html);
    }

    public function test_the_view_page_renders_in_arabic_without_missing_keys(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['preferred_admin_locale' => 'ar'])->save();
        $this->actingAs($admin);

        $submission = $this->submission(['name' => 'زائر عربي']);

        $html = $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString(__('content.submissions.contact_section', [], 'ar'), $html);
        $this->assertStringContainsString(__('content.submission_status.new', [], 'ar'), $html);
        // An unresolved key would surface as its literal dotted path.
        $this->assertStringNotContainsString('content.submissions.', $html);
        $this->assertStringNotContainsString('content.submission_status.', $html);
    }
}
