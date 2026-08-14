<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\FormResource;
use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Filament\Resources\FormResource\Pages\ListForms;
use App\Models\Form;
use App\Models\FormSubmission;
use Filament\Forms\Components\Repeater;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Auth\AdminTestCase;
use Tests\Support\InteractsWithContactForm;

/**
 * The fixed single-record Contact Form module.
 *
 * The product exposes exactly ONE form. The generic engine underneath still
 * accepts other slugs (see FormModuleTest), so the restriction lives in the
 * admin surface: the resource is scoped to the canonical slug, offers no
 * create and no delete route, and refuses to open any other form.
 *
 * Structural restrictions are asserted for a SUPER ADMIN wherever possible,
 * because active super admins bypass FormPolicy entirely via Gate::before —
 * a policy-only restriction would not hold for them.
 */
class ContactFormModuleTest extends AdminTestCase
{
    use InteractsWithContactForm;

    /**
     * A simple two-field definition, so these tests are not coupled to the
     * board copy the migration ships.
     *
     * @return list<array<string, mixed>>
     */
    private function simpleDefinition(): array
    {
        return [
            ['name' => 'full_name', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Full Name', 'ar' => 'الاسم الكامل']],
            ['name' => 'message', 'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Message', 'ar' => 'الرسالة']],
        ];
    }

    /**
     * Repeater items are keyed by generated UUIDs, so the only stable way to
     * assert on one field's error path is to read the key back out of the
     * live component state.
     *
     * @return list<string>
     */
    private function fieldItemKeys(Testable $component): array
    {
        return array_map('strval', array_keys((array) $component->get('data.fields')));
    }

    // ---- Provisioning ----------------------------------------------------

    public function test_the_runtime_contact_slug_resolves_to_the_canonical_provisioned_key(): void
    {
        // The migration hardcodes 'contact' on purpose; this proves the
        // RUNTIME lookup used by /contact agrees with what was provisioned.
        $this->assertSame('contact', (string) config('platform.contact_form_slug'));

        $form = $this->canonicalContactForm();

        $this->assertTrue($form->is_active);
        $this->assertNotEmpty($form->fieldNames());
        $this->assertSame(1, Form::query()->where('slug', 'contact')->count());
    }

    /**
     * The migration must be safe to re-run. insertOrIgnore compiles to
     * "on conflict do nothing" on PostgreSQL, so a redeploy against a
     * database an administrator has already edited must add nothing and
     * change nothing — which is also why the migration's down() is a
     * deliberate no-op.
     */
    public function test_re_running_the_provisioning_migration_never_overwrites_administrator_edits(): void
    {
        $contact = $this->canonicalContactForm([
            'name' => 'Renamed By Administrator',
            'is_active' => false,
            'fields' => $this->simpleDefinition(),
        ]);

        $migration = require database_path('migrations/2026_08_12_110000_provision_canonical_contact_form.php');
        $migration->up();

        $this->assertSame(1, Form::query()->where('slug', 'contact')->count());
        $this->assertSame(1, Form::query()->count());

        $fresh = $contact->fresh();

        $this->assertSame($contact->getKey(), $fresh->getKey());
        $this->assertSame('Renamed By Administrator', $fresh->name);
        $this->assertFalse($fresh->is_active);
        $this->assertSame(['full_name', 'message'], $fresh->fieldNames());
    }

    // ---- Canonical scoping ----------------------------------------------

    public function test_the_module_lists_only_the_canonical_contact_form(): void
    {
        $contact = $this->canonicalContactForm();
        $other = Form::factory()->create(['slug' => 'internal-newsletter']);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListForms::class)
            ->assertCanSeeTableRecords([$contact])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_another_forms_edit_url_is_unreachable_through_this_resource(): void
    {
        $contact = $this->canonicalContactForm();
        $other = Form::factory()->create(['slug' => 'internal-newsletter']);

        $this->actingAs($this->superAdmin());

        // Scoped route binding resolves nothing, so this 404s rather than
        // opening a record the module does not own.
        $this->get(FormResource::getUrl('edit', ['record' => $other]))->assertNotFound();

        // …while the canonical record stays editable.
        $this->get(FormResource::getUrl('edit', ['record' => $contact]))->assertOk();
    }

    // ---- Structural absence of create and delete -------------------------

    public function test_no_create_route_or_action_exists_even_for_a_super_admin(): void
    {
        $this->assertArrayNotHasKey('create', FormResource::getPages());
        $this->assertFalse(FormResource::canCreate());

        $this->actingAs($this->superAdmin());

        // Structural: the URI resolves to nothing — not 403, absent.
        $this->get($this->adminHost.'/forms/create')->assertNotFound();

        Livewire::test(ListForms::class)->assertActionDoesNotExist('create');
    }

    public function test_no_delete_action_exists_even_for_a_super_admin(): void
    {
        $contact = $this->canonicalContactForm();

        $this->assertFalse(FormResource::canDeleteAny());
        $this->assertFalse(FormResource::canDelete($contact));

        $this->actingAs($this->superAdmin());

        Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->assertActionDoesNotExist('delete');

        Livewire::test(ListForms::class)
            // Third argument: the signature is (actions, checkActionUsing,
            // record) — the record must not be passed positionally second.
            ->assertTableActionDoesNotExist('delete', null, $contact)
            ->assertTableBulkActionDoesNotExist('delete');
    }

    // ---- Authorization ---------------------------------------------------

    public function test_an_admin_can_open_and_save_the_contact_form(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        $this->actingAs($this->admin());

        $this->get(FormResource::getUrl('index'))->assertOk();
        $this->get(FormResource::getUrl('edit', ['record' => $contact]))->assertOk();

        Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm(['name' => 'Consultation Request'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Consultation Request', $contact->fresh()->name);
    }

    public function test_an_editor_cannot_reach_the_contact_form_module(): void
    {
        $contact = $this->canonicalContactForm();

        $this->actingAs($this->editor());

        $this->get(FormResource::getUrl('index'))->assertForbidden();
        $this->get(FormResource::getUrl('edit', ['record' => $contact]))->assertForbidden();
    }

    // ---- Slug immutability ----------------------------------------------

    public function test_the_slug_cannot_be_changed_through_a_crafted_update(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        $this->actingAs($this->superAdmin());

        // Path 1 — the ordinary form-filling API.
        Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm(['slug' => 'hijacked-by-fill'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('contact', $contact->fresh()->slug);

        // Path 2 — a crafted write straight to the component's form state
        // path, which is what a hand-rolled Livewire request would target.
        // The field is disabled AND dehydrated(false), so neither validation
        // nor persistence has any route to the model.
        $component = Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->set('data.slug', 'hijacked-by-state')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('contact', $contact->fresh()->slug);
        // The crafted value stayed in browser state and never became data.
        $this->assertSame('hijacked-by-state', $component->get('data.slug'));
        $this->assertSame(0, Form::query()->where('slug', 'like', 'hijacked%')->count());
    }

    // ---- Field machine keys ---------------------------------------------

    /**
     * fillForm() calls disableSchemaStateUpdateHooksForTesting, so the
     * on-blur normalizer never runs in any of these tests. That is
     * deliberate: it exercises exactly the path where correctness must come
     * from validation and the save-time normalizer rather than a UI event.
     */
    public function test_field_keys_are_normalized_to_snake_case_on_save(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        $this->actingAs($this->superAdmin());

        Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm([
                'fields' => [
                    ['name' => 'Full Name', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Full Name']],
                    ['name' => 'MessageBody', 'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Message Body']],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['full_name', 'message_body'], $contact->fresh()->fieldNames());
    }

    public function test_a_leading_digit_key_is_rejected_with_the_format_message(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        $this->actingAs($this->superAdmin());

        $component = Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm([
                'fields' => [
                    ['name' => '123 Name', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Name']],
                ],
            ]);

        [$itemKey] = $this->fieldItemKeys($component);

        $component->call('save')->assertHasFormErrors(["fields.{$itemKey}.name"]);

        $this->assertContains(
            __('content.forms.invalid_field_name'),
            $component->instance()->getErrorBag()->get("data.fields.{$itemKey}.name"),
        );

        // Normalization produces '123_name', which still has no leading
        // letter — rejected rather than silently rewritten into something
        // the administrator never typed.
        $this->assertSame('123_name', FormResource::normalizeFieldName('123 Name'));
        $this->assertSame(['full_name', 'message'], $contact->fresh()->fieldNames());
    }

    public function test_a_key_with_no_machine_characters_is_rejected_with_the_format_message(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        $this->actingAs($this->superAdmin());

        $component = Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm([
                'fields' => [
                    ['name' => 'الاسم الكامل', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Name']],
                ],
            ]);

        [$itemKey] = $this->fieldItemKeys($component);

        $component->call('save')->assertHasFormErrors(["fields.{$itemKey}.name"]);

        $this->assertContains(
            __('content.forms.invalid_field_name'),
            $component->instance()->getErrorBag()->get("data.fields.{$itemKey}.name"),
        );

        // The ORIGINAL is returned when normalization would empty the value,
        // so the administrator sees a format error instead of their input
        // vanishing.
        $this->assertSame('الاسم الكامل', FormResource::normalizeFieldName('الاسم الكامل'));
        $this->assertSame(['full_name', 'message'], $contact->fresh()->fieldNames());
    }

    /**
     * The exact shape of the original defect: raw input that is WITHIN the
     * limit but whose stored key is not. A raw maxLength(64) would have
     * accepted this and persisted a 90-character key.
     */
    public function test_a_key_whose_normalized_value_exceeds_64_characters_is_rejected(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        // 60 raw characters; Str::snake inserts an underscore before each of
        // the 30 capitals, so the stored key would be 90.
        $raw = str_repeat('aB', 30);

        $this->assertSame(60, strlen($raw));
        $this->assertSame(90, strlen(FormResource::normalizeFieldName($raw)));

        $this->actingAs($this->superAdmin());

        $component = Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm([
                'fields' => [
                    ['name' => $raw, 'type' => 'text', 'required' => true, 'label' => ['en' => 'Long']],
                ],
            ]);

        [$itemKey] = $this->fieldItemKeys($component);

        $component->call('save')->assertHasFormErrors(["fields.{$itemKey}.name"]);

        $this->assertContains(
            __('content.forms.invalid_field_name'),
            $component->instance()->getErrorBag()->get("data.fields.{$itemKey}.name"),
        );

        $this->assertSame(['full_name', 'message'], $contact->fresh()->fieldNames());
    }

    public function test_a_key_of_exactly_64_normalized_characters_is_accepted(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        // The boundary is inclusive: 64 is the longest permitted key.
        $key = 'a'.str_repeat('b', 63);

        $this->assertSame(64, strlen($key));
        $this->assertSame($key, FormResource::normalizeFieldName($key));

        $this->actingAs($this->superAdmin());

        Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm([
                'fields' => [
                    ['name' => $key, 'type' => 'text', 'required' => true, 'label' => ['en' => 'Boundary']],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$key], $contact->fresh()->fieldNames());
    }

    public function test_identical_raw_field_keys_are_rejected(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        $this->actingAs($this->superAdmin());

        Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm([
                'fields' => [
                    ['name' => 'email', 'type' => 'email', 'required' => true, 'label' => ['en' => 'Email']],
                    ['name' => 'email', 'type' => 'text', 'required' => false, 'label' => ['en' => 'Email again']],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame(['full_name', 'message'], $contact->fresh()->fieldNames());
    }

    public function test_field_keys_that_collide_only_after_normalization_are_rejected(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        $this->actingAs($this->superAdmin());

        // distinct() alone would PASS these two — they differ as raw input.
        // The repeater-level rule compares the normalized keys, which both
        // reduce to 'full_name'.
        $component = Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm([
                'fields' => [
                    ['name' => 'Full Name', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Full Name']],
                    ['name' => 'full_name', 'type' => 'text', 'required' => false, 'label' => ['en' => 'Duplicate']],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['fields']);

        $this->assertContains(
            __('content.forms.duplicate_field_name'),
            $component->instance()->getErrorBag()->get('data.fields'),
        );

        $this->assertSame(['full_name', 'message'], $contact->fresh()->fieldNames());
    }

    // ---- Submission-aware key locking ------------------------------------

    public function test_field_keys_stay_editable_while_no_submissions_exist(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        $this->actingAs($this->superAdmin());

        $component = Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()]);

        $this->assertFalse(FormResource::isLockedFieldName($component->instance(), 'full_name'));

        [$itemKey] = $this->fieldItemKeys($component);
        $component->assertFormFieldEnabled("fields.{$itemKey}.name");
    }

    public function test_existing_field_keys_are_locked_once_submissions_exist(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);
        FormSubmission::factory()->create(['form_id' => $contact->getKey()]);

        $this->actingAs($this->superAdmin());

        $component = Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()]);

        // A key that already carries collected payloads is frozen…
        $this->assertTrue(FormResource::isLockedFieldName($component->instance(), 'full_name'));
        // …but a key that is not part of the saved definition is not, so a
        // NEW field can still be added.
        $this->assertFalse(FormResource::isLockedFieldName($component->instance(), 'newly_added'));

        [$itemKey] = $this->fieldItemKeys($component);
        $component->assertFormFieldDisabled("fields.{$itemKey}.name");
    }

    public function test_locked_field_keys_are_still_saved_and_never_dropped(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);
        FormSubmission::factory()->create(['form_id' => $contact->getKey()]);

        $this->actingAs($this->superAdmin());

        // Saving an unrelated attribute must not silently strip the disabled
        // keys from the stored definition — disabled fields are only
        // persisted because they are explicitly dehydrated.
        Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm(['name' => 'Consultation Request'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['full_name', 'message'], $contact->fresh()->fieldNames());
        $this->assertSame('Consultation Request', $contact->fresh()->name);
    }

    public function test_a_new_field_can_be_added_while_existing_keys_are_locked(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);
        FormSubmission::factory()->create(['form_id' => $contact->getKey()]);

        $this->actingAs($this->superAdmin());

        // Locking must freeze the EXISTING keys without freezing the form:
        // the administrator can still extend the definition.
        Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm([
                'fields' => [
                    ...$this->simpleDefinition(),
                    ['name' => 'new_field', 'type' => 'text', 'required' => false, 'label' => ['en' => 'New Field']],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            ['full_name', 'message', 'new_field'],
            $contact->fresh()->fieldNames(),
        );
    }

    public function test_historical_submission_payloads_survive_a_label_only_edit(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        $payload = ['full_name' => 'Visitor', 'message' => 'Original details.'];

        $submission = FormSubmission::factory()->create([
            'form_id' => $contact->getKey(),
            'payload' => $payload,
        ]);

        $this->actingAs($this->superAdmin());

        // Only the visitor-facing labels change; the machine keys are locked
        // and must carry through untouched.
        Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
            ->fillForm([
                'fields' => [
                    ['name' => 'full_name', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Your Full Name', 'ar' => 'اسمك الكامل']],
                    ['name' => 'message', 'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Your Message', 'ar' => 'رسالتك']],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Collected data is untouched, key for key and value for value.
        // PostgreSQL jsonb does not preserve object-key insertion order, so
        // sort the top-level keys before a strict comparison.
        $stored = $submission->fresh()->payload;
        $expected = $payload;
        ksort($stored);
        ksort($expected);

        $this->assertSame($expected, $stored);

        $fresh = $contact->fresh();

        $this->assertSame(['full_name', 'message'], $fresh->fieldNames());
        $this->assertSame('Your Full Name', $fresh->fields[0]['label']['en']);
        $this->assertSame('Your Message', $fresh->fields[1]['label']['en']);
    }

    // ---- Select options --------------------------------------------------

    public function test_a_select_field_with_options_is_saved(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        $this->actingAs($this->superAdmin());

        $undoRepeaterFake = Repeater::fake();

        try {
            Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
                ->fillForm([
                    'fields' => [
                        [
                            'name' => 'service_type',
                            'type' => 'select',
                            'required' => true,
                            'label' => ['en' => 'Service Type', 'ar' => 'نوع الخدمة'],
                            'options' => [
                                ['value' => 'general_inquiry', 'label' => ['en' => 'General Inquiry', 'ar' => 'استفسار عام']],
                            ],
                        ],
                    ],
                ])
                ->call('save')
                ->assertHasNoFormErrors();
        } finally {
            $undoRepeaterFake();
        }

        $fields = $contact->fresh()->fields;

        $this->assertSame('select', $fields[0]['type']);
        $this->assertSame('general_inquiry', $fields[0]['options'][0]['value']);
        $this->assertSame('استفسار عام', $fields[0]['options'][0]['label']['ar']);
    }

    public function test_malformed_and_duplicate_option_values_are_rejected(): void
    {
        $contact = $this->canonicalContactForm(['fields' => $this->simpleDefinition()]);

        $this->actingAs($this->superAdmin());

        $undoRepeaterFake = Repeater::fake();

        try {
            Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
                ->fillForm([
                    'fields' => [[
                        'name' => 'topic',
                        'type' => 'select',
                        'required' => true,
                        'label' => ['en' => 'Topic'],
                        'options' => [
                            ['value' => 'Bad Value!', 'label' => ['en' => 'Bad']],
                        ],
                    ]],
                ])
                ->call('save')
                ->assertHasFormErrors();

            Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
                ->fillForm([
                    'fields' => [[
                        'name' => 'topic',
                        'type' => 'select',
                        'required' => true,
                        'label' => ['en' => 'Topic'],
                        'options' => [
                            ['value' => 'dup', 'label' => ['en' => 'One']],
                            ['value' => 'dup', 'label' => ['en' => 'Two']],
                        ],
                    ]],
                ])
                ->call('save')
                ->assertHasFormErrors();
        } finally {
            $undoRepeaterFake();
        }

        $this->assertSame(['full_name', 'message'], $contact->fresh()->fieldNames());
    }

    public function test_switching_away_from_select_strips_stale_options(): void
    {
        $contact = $this->canonicalContactForm([
            'fields' => [[
                'name' => 'topic',
                'type' => 'select',
                'required' => true,
                'label' => ['en' => 'Topic'],
                'options' => [['value' => 'billing', 'label' => ['en' => 'Billing']]],
            ]],
        ]);

        $this->actingAs($this->superAdmin());

        $undoRepeaterFake = Repeater::fake();

        try {
            Livewire::test(EditForm::class, ['record' => $contact->getRouteKey()])
                ->fillForm([
                    'fields' => [[
                        'name' => 'topic',
                        'type' => 'text',
                        'required' => true,
                        'label' => ['en' => 'Topic'],
                        // Stale options riding along with the type change —
                        // the save hook must strip them.
                        'options' => [['value' => 'billing', 'label' => ['en' => 'Billing']]],
                    ]],
                ])
                ->call('save')
                ->assertHasNoFormErrors();
        } finally {
            $undoRepeaterFake();
        }

        $this->assertArrayNotHasKey('options', $contact->fresh()->fields[0]);
    }
}
