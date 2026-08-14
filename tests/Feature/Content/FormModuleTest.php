<?php

namespace Tests\Feature\Content;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Auth\AdminTestCase;

/**
 * The GENERIC form engine: validation-rule derivation from a stored
 * definition, the read-time option sanitizer, and the database-level guard
 * protecting collected submissions.
 *
 * Deliberately free of admin-UI coverage — that now lives in
 * ContactFormModuleTest, which exercises the single fixed Contact module the
 * panel exposes. The engine underneath still supports arbitrary slugs, and
 * that is what these tests pin down, using non-canonical slugs throughout so
 * they never collide with the provisioned 'contact' row.
 */
class FormModuleTest extends AdminTestCase
{
    public function test_validation_rules_derive_from_definition(): void
    {
        $form = Form::factory()->make([
            'fields' => [
                ['name' => 'full_name', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Full name']],
                ['name' => 'email', 'type' => 'email', 'required' => true, 'label' => ['en' => 'Email']],
                ['name' => 'age', 'type' => 'number', 'required' => false, 'label' => ['en' => 'Age']],
                ['name' => 'notes', 'type' => 'textarea', 'required' => false, 'label' => ['en' => 'Notes']],
            ],
        ]);

        $rules = $form->validationRules();

        $this->assertSame(['required', 'string', 'max:255'], $rules['full_name']);
        $this->assertSame(['required', 'string', 'email', 'max:255'], $rules['email']);
        $this->assertSame(['nullable', 'numeric'], $rules['age']);
        $this->assertSame(['nullable', 'string', 'max:5000'], $rules['notes']);
    }

    public function test_unknown_field_type_falls_back_to_bounded_text(): void
    {
        $form = Form::factory()->make([
            'fields' => [
                ['name' => 'odd', 'type' => 'tampered', 'required' => true, 'label' => ['en' => 'Odd']],
            ],
        ]);

        $this->assertSame(['required', 'string', 'max:255'], $form->validationRules()['odd']);
    }

    /**
     * The engine still accepts additional form definitions at the model
     * layer — the newsletter and any future internal form depend on it.
     * Only the ADMIN surface is restricted to Contact, so this is proven
     * with a non-reserved slug.
     */
    public function test_a_non_canonical_form_can_be_created_at_the_model_layer(): void
    {
        $form = Form::query()->create([
            'name' => 'Inquiry',
            'slug' => 'inquiry',
            'is_active' => true,
            'fields' => [
                ['name' => 'topic', 'type' => 'select', 'required' => true, 'label' => ['en' => 'Topic'], 'options' => [
                    ['value' => 'billing', 'label' => ['en' => 'Billing']],
                ]],
                ['name' => 'details', 'type' => 'textarea', 'required' => false, 'label' => ['en' => 'Details']],
            ],
        ]);

        $fresh = $form->fresh();

        $this->assertSame('inquiry', $fresh->slug);
        $this->assertTrue($fresh->is_active);
        $this->assertSame(['topic', 'details'], $fresh->fieldNames());
        $this->assertSame(['required', 'string', 'in:"billing"'], array_map(
            'strval',
            $fresh->validationRules()['topic'],
        ));

        // The canonical row is untouched alongside it.
        $this->assertSame(1, Form::query()->where('slug', 'contact')->count());
    }

    /**
     * The panel offers no delete action at all, but the real guarantee is
     * the restrictive foreign key: collected submissions cannot be orphaned
     * even by code that bypasses the UI entirely.
     *
     * The delete runs in a NESTED transaction so PostgreSQL rolls back to a
     * savepoint. Without it the constraint violation would abort the whole
     * RefreshDatabase transaction, and every later query in this test would
     * fail for the wrong reason — the assertions below are what prove the
     * connection survived.
     */
    public function test_a_form_with_submissions_cannot_be_deleted_at_the_database_level(): void
    {
        $form = Form::factory()->create(['slug' => 'retained']);
        FormSubmission::factory()->create(['form_id' => $form->getKey()]);

        $this->assertThrows(
            fn () => DB::transaction(fn () => $form->delete()),
            QueryException::class,
        );

        $this->assertNotNull(Form::query()->find($form->getKey()));
        $this->assertSame(1, FormSubmission::query()->where('form_id', $form->getKey())->count());
    }

    public function test_a_form_without_submissions_can_be_deleted_at_the_model_layer(): void
    {
        $form = Form::factory()->create(['slug' => 'disposable']);

        $form->delete();

        $this->assertNull(Form::query()->find($form->getKey()));
    }

    public function test_select_rules_accept_only_declared_option_values(): void
    {
        $form = Form::factory()->make([
            'fields' => [
                [
                    'name' => 'topic',
                    'type' => 'select',
                    'required' => true,
                    'label' => ['en' => 'Topic'],
                    'options' => [
                        ['value' => 'billing', 'label' => ['en' => 'Billing']],
                        ['value' => 'support', 'label' => ['en' => 'Support']],
                    ],
                ],
            ],
        ]);

        $rules = $form->validationRules()['topic'];

        $this->assertSame('required', $rules[0]);
        $this->assertSame('string', $rules[1]);
        $this->assertSame('in:"billing","support"', (string) $rules[2]);
    }

    public function test_tampered_select_options_sanitize_to_valid_values_only(): void
    {
        $filler = collect(range(1, 25))
            ->map(fn (int $i): array => ['value' => 'opt_'.$i, 'label' => ['en' => 'Opt '.$i]])
            ->all();

        $form = Form::factory()->make([
            'fields' => [
                [
                    'name' => 'topic',
                    'type' => 'select',
                    'required' => true,
                    'label' => ['en' => 'Topic'],
                    'options' => [
                        ['value' => 'Bad Value!', 'label' => ['en' => 'Injected']],
                        ['value' => 'UPPER', 'label' => ['en' => 'Injected']],
                        ['value' => ['array'], 'label' => ['en' => 'Injected']],
                        'junk',
                        ['value' => 'valid_one', 'label' => ['en' => 'Valid']],
                        ['value' => 'valid_one', 'label' => ['en' => 'Duplicate']],
                        ...$filler,
                    ],
                ],
            ],
        ]);

        $rule = (string) $form->validationRules()['topic'][2];

        // Malformed values discarded, duplicates removed, list capped at 20.
        $this->assertStringStartsWith('in:"valid_one","opt_1"', $rule);
        $this->assertStringNotContainsString('Bad Value!', $rule);
        $this->assertStringNotContainsString('UPPER', $rule);
        $this->assertSame(20, (int) (substr_count($rule, '"') / 2));
        $this->assertStringNotContainsString('"opt_20"', $rule);
    }

    public function test_checkbox_rules_enforce_consent_when_required(): void
    {
        $form = Form::factory()->make([
            'fields' => [
                ['name' => 'consent', 'type' => 'checkbox', 'required' => true, 'label' => ['en' => 'Consent']],
                ['name' => 'updates', 'type' => 'checkbox', 'required' => false, 'label' => ['en' => 'Updates']],
            ],
        ]);

        $rules = $form->validationRules();

        $this->assertSame(['accepted'], $rules['consent']);
        $this->assertSame(['nullable', 'boolean'], $rules['updates']);
    }

    public function test_select_options_helper_resolves_locales_and_guards_non_select_fields(): void
    {
        $form = Form::factory()->make([
            'fields' => [
                [
                    'name' => 'topic',
                    'type' => 'select',
                    'required' => true,
                    'label' => ['en' => 'Topic'],
                    'options' => [
                        ['value' => 'billing', 'label' => ['en' => 'Billing', 'ar' => 'الفوترة']],
                        // Whitespace-only label falls back to the value.
                        ['value' => 'others', 'label' => ['en' => '   ']],
                    ],
                ],
                [
                    'name' => 'note',
                    'type' => 'text',
                    'required' => false,
                    'label' => ['en' => 'Note'],
                    // Tampered non-select field carrying stale options must
                    // never leak choices through the helper.
                    'options' => [['value' => 'leak', 'label' => ['en' => 'Leak']]],
                ],
            ],
        ]);

        $this->assertSame(
            [
                ['value' => 'billing', 'label' => 'الفوترة'],
                ['value' => 'others', 'label' => 'others'],
            ],
            $form->selectOptions('topic', 'ar'),
        );
        $this->assertSame('Billing', $form->optionLabel('topic', 'billing', 'en'));
        $this->assertNull($form->optionLabel('topic', 'forged', 'en'));
        $this->assertSame([], $form->selectOptions('note'));
    }
}
