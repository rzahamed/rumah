<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\FormSubmissionResource\Pages\ViewFormSubmission;
use App\Models\Form;
use App\Models\FormSubmission;
use Livewire\Livewire;
use Tests\Feature\Auth\AdminTestCase;

/**
 * The public submission endpoint (default + localized) and the protected
 * admin surface for collected data.
 */
class FormSubmissionTest extends AdminTestCase
{
    private function contactForm(): Form
    {
        return Form::factory()->create(['slug' => 'contact']);
    }

    public function test_valid_submission_is_stored_with_declared_fields_only(): void
    {
        $form = $this->contactForm();

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
        $this->contactForm();

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
        $this->contactForm();

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
        $this->contactForm();

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
}
