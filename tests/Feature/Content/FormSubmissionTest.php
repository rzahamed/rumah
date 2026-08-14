<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\FormSubmissionResource\Pages\ViewFormSubmission;
use App\Models\Form;
use App\Models\FormSubmission;
use Livewire\Livewire;
use Tests\Feature\Auth\AdminTestCase;
use Tests\Support\InteractsWithContactForm;

/**
 * The public submission endpoint (default + localized) and the protected
 * admin surface for collected data.
 */
class FormSubmissionTest extends AdminTestCase
{
    use InteractsWithContactForm;

    public function test_valid_submission_is_stored_with_declared_fields_only(): void
    {
        $form = $this->genericContactForm();

        $response = $this->from($this->publicHost.'/')
            ->post($this->publicHost.'/forms/contact', [
                'name' => 'Visitor',
                'email' => 'visitor@example.com',
                'message' => 'Hello there.',
                'unexpected' => 'DROP ME',
            ]);

        $response->assertRedirect($this->publicHost.'/');
        $response->assertSessionHas('status', __('content.forms.submitted'));

        $submission = FormSubmission::query()->sole();

        $this->assertSame($form->getKey(), $submission->form_id);
        $this->assertSame(
            ['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello there.'],
            $submission->payload,
        );
        $this->assertArrayNotHasKey('unexpected', $submission->payload);
    }

    public function test_localized_route_stores_submission_and_flashes_arabic_message(): void
    {
        $this->genericContactForm();

        $response = $this->from($this->publicHost.'/ar')
            ->post($this->publicHost.'/ar/forms/contact', [
                'name' => 'زائر',
                'email' => 'visitor@example.com',
                'message' => 'مرحباً.',
            ]);

        $response->assertRedirect($this->publicHost.'/ar');
        $response->assertSessionHas('status', __('content.forms.submitted', [], 'ar'));

        $this->assertSame(1, FormSubmission::query()->count());
    }

    public function test_invalid_submission_is_rejected_with_field_errors(): void
    {
        $this->genericContactForm();

        $this->from($this->publicHost.'/')
            ->post($this->publicHost.'/forms/contact', [
                'name' => 'Visitor',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors(['email', 'message']);

        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_unknown_and_inactive_forms_404_identically(): void
    {
        Form::factory()->inactive()->create(['slug' => 'retired']);

        $this->post($this->publicHost.'/forms/nonexistent', [])->assertNotFound();
        $this->post($this->publicHost.'/forms/retired', [])->assertNotFound();
    }

    public function test_submission_endpoint_is_not_available_on_admin_host(): void
    {
        $this->genericContactForm();

        $this->post($this->adminHost.'/forms/contact', [])->assertNotFound();
    }

    public function test_submission_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post($this->publicHost.'/forms/nonexistent', [])->assertNotFound();
        }

        $this->post($this->publicHost.'/forms/nonexistent', [])->assertStatus(429);
    }

    public function test_admin_can_view_a_submission(): void
    {
        $submission = FormSubmission::factory()->create();

        $this->actingAs($this->admin());

        $this->get($this->adminHost.'/form-submissions/'.$submission->getKey())->assertOk();
    }

    public function test_admin_can_delete_a_single_submission(): void
    {
        $submission = FormSubmission::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(ViewFormSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('delete');

        $this->assertNull(FormSubmission::query()->find($submission->getKey()));
    }

    public function test_editor_cannot_view_submissions(): void
    {
        $submission = FormSubmission::factory()->create();

        $this->actingAs($this->editor());

        $this->get($this->adminHost.'/form-submissions')->assertForbidden();
        $this->get($this->adminHost.'/form-submissions/'.$submission->getKey())->assertForbidden();
    }

    public function test_no_create_or_edit_routes_exist_for_submissions(): void
    {
        $submission = FormSubmission::factory()->create();

        $this->actingAs($this->superAdmin());

        // Structural: these URIs resolve to nothing — not 403, absent.
        $this->get($this->adminHost.'/form-submissions/create')->assertNotFound();
        $this->get($this->adminHost.'/form-submissions/'.$submission->getKey().'/edit')->assertNotFound();
    }

    public function test_checkbox_markup_contract_stores_true_and_drops_undeclared_input(): void
    {
        // Markup contract: our public checkbox markup explicitly uses
        // value="1", so a ticked box posts "1" — the payload stores a real
        // boolean true.
        Form::factory()->newsletter()->create(['slug' => 'newsletter']);

        $response = $this->from($this->publicHost.'/')
            ->post($this->publicHost.'/forms/newsletter', [
                'email' => 'reader@example.com',
                'consent' => '1',
                'undeclared' => 'DROP ME',
            ]);

        $response->assertSessionHasNoErrors();

        $submission = FormSubmission::query()->sole();

        $this->assertSame('reader@example.com', $submission->payload['email']);
        $this->assertTrue($submission->payload['consent']);
        $this->assertArrayNotHasKey('undeclared', $submission->payload);
    }

    public function test_valid_select_value_is_stored_and_absent_optional_checkbox_records_false(): void
    {
        Form::factory()->create([
            'slug' => 'mixed',
            'fields' => [
                [
                    'name' => 'topic',
                    'type' => 'select',
                    'required' => true,
                    'label' => ['en' => 'Topic'],
                    'options' => [['value' => 'billing', 'label' => ['en' => 'Billing']]],
                ],
                ['name' => 'updates', 'type' => 'checkbox', 'required' => false, 'label' => ['en' => 'Updates']],
            ],
        ]);

        // Markup contract: an unticked checkbox posts nothing at all — the
        // stored payload still records the answer, as explicit false.
        $this->from($this->publicHost.'/')
            ->post($this->publicHost.'/forms/mixed', ['topic' => 'billing'])
            ->assertSessionHasNoErrors();

        $payload = FormSubmission::query()->sole()->payload;

        $this->assertSame('billing', $payload['topic']);
        $this->assertFalse($payload['updates']);
    }

    public function test_forged_select_value_is_rejected_and_not_persisted(): void
    {
        Form::factory()->inquiry()->create(['slug' => 'inquiry']);

        $response = $this->from($this->publicHost.'/')
            ->post($this->publicHost.'/forms/inquiry', [
                'full_name' => 'Visitor',
                'email' => 'visitor@example.com',
                'preferred_contact_method' => 'phone_call',
                'topic' => 'forged_value',
            ]);

        $response->assertSessionHasErrors(['topic']);
        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_missing_required_consent_is_rejected_server_side(): void
    {
        Form::factory()->newsletter()->create(['slug' => 'newsletter']);

        $response = $this->from($this->publicHost.'/')
            ->post($this->publicHost.'/forms/newsletter', [
                'email' => 'reader@example.com',
                // Consent deliberately absent — client JS is never the
                // authority; the 'accepted' rule is.
            ]);

        $response->assertSessionHasErrors(['consent']);
        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_tampered_options_definition_still_rejects_invalid_entries(): void
    {
        // Written directly to the database, bypassing the admin builder:
        // one syntactically invalid entry alongside one valid entry.
        Form::factory()->create([
            'slug' => 'tampered',
            'fields' => [[
                'name' => 'topic',
                'type' => 'select',
                'required' => true,
                'label' => ['en' => 'Topic'],
                'options' => [
                    ['value' => 'Not A Machine Value', 'label' => ['en' => 'Bad']],
                    ['value' => 'valid_topic', 'label' => ['en' => 'Valid']],
                ],
            ]],
        ]);

        $this->from($this->publicHost.'/')
            ->post($this->publicHost.'/forms/tampered', ['topic' => 'Not A Machine Value'])
            ->assertSessionHasErrors(['topic']);

        $this->assertSame(0, FormSubmission::query()->count());

        $this->from($this->publicHost.'/')
            ->post($this->publicHost.'/forms/tampered', ['topic' => 'valid_topic'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, FormSubmission::query()->count());
    }

    public function test_localized_route_accepts_inquiry_submission(): void
    {
        Form::factory()->inquiry()->create(['slug' => 'inquiry']);

        $response = $this->from($this->publicHost.'/ar')
            ->post($this->publicHost.'/ar/forms/inquiry', [
                'full_name' => 'زائر',
                'email' => 'visitor@example.com',
                'preferred_contact_method' => 'whatsapp',
                'topic' => 'support',
            ]);

        $response->assertRedirect($this->publicHost.'/ar');
        $response->assertSessionHas('status', __('content.forms.submitted', [], 'ar'));

        $this->assertSame('whatsapp', FormSubmission::query()->sole()->payload['preferred_contact_method']);
    }
}
