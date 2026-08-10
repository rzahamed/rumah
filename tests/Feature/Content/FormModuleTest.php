<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\FormSubmission;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Tests\Feature\Auth\AdminTestCase;

/**
 * Form definitions: validation-rule derivation, the admin builder's
 * server-side rules (distinct machine names), and the collected-data delete
 * guard backed by the restrictive foreign key.
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

    public function test_admin_can_create_a_form(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateForm::class)
            ->fillForm([
                'name' => 'Contact',
                'slug' => 'contact',
                'is_active' => true,
                'fields' => [
                    [
                        'name' => 'email',
                        'type' => 'email',
                        'required' => true,
                        'label' => ['en' => 'Email', 'ar' => 'البريد'],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Form::query()->where('slug', 'contact')->count());
    }

    public function test_duplicate_field_machine_names_are_rejected(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateForm::class)
            ->fillForm([
                'name' => 'Broken',
                'slug' => 'broken',
                'fields' => [
                    ['name' => 'email', 'type' => 'email', 'required' => true, 'label' => ['en' => 'Email']],
                    ['name' => 'email', 'type' => 'text', 'required' => false, 'label' => ['en' => 'Email again']],
                ],
            ])
            ->call('create')
            ->assertHasErrors();

        $this->assertSame(0, Form::query()->where('slug', 'broken')->count());
    }

    public function test_form_with_submissions_cannot_be_deleted(): void
    {
        $form = Form::factory()->create();
        FormSubmission::factory()->create(['form_id' => $form->getKey()]);

        $this->actingAs($this->admin());

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->callAction('delete')
            ->assertNotified(__('content.forms.delete_blocked'));

        $this->assertNotNull(Form::query()->find($form->getKey()));
    }

    public function test_form_without_submissions_can_be_deleted(): void
    {
        $form = Form::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->callAction('delete');

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

    public function test_admin_can_create_a_select_field_with_options(): void
    {
        $this->actingAs($this->admin());

        $undoRepeaterFake = Repeater::fake();

        try {
            Livewire::test(CreateForm::class)
                ->fillForm([
                    'name' => 'Inquiry',
                    'slug' => 'inquiry',
                    'is_active' => true,
                    'fields' => [
                        [
                            'name' => 'topic',
                            'type' => 'select',
                            'required' => true,
                            'label' => ['en' => 'Topic', 'ar' => 'الموضوع'],
                            'options' => [
                                ['value' => 'billing', 'label' => ['en' => 'Billing', 'ar' => 'الفوترة']],
                            ],
                        ],
                    ],
                ])
                ->call('create')
                ->assertHasNoFormErrors();
        } finally {
            $undoRepeaterFake();
        }

        $form = Form::query()->where('slug', 'inquiry')->sole();

        $this->assertSame('select', $form->fields[0]['type']);
        $this->assertSame('billing', $form->fields[0]['options'][0]['value']);
        $this->assertSame('الفوترة', $form->fields[0]['options'][0]['label']['ar']);
    }

    public function test_malformed_and_duplicate_option_values_are_rejected(): void
    {
        $this->actingAs($this->admin());

        $undoRepeaterFake = Repeater::fake();

        try {
            Livewire::test(CreateForm::class)
                ->fillForm([
                    'name' => 'Broken',
                    'slug' => 'broken-options',
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
                ->call('create')
                ->assertHasErrors();

            Livewire::test(CreateForm::class)
                ->fillForm([
                    'name' => 'Broken Two',
                    'slug' => 'broken-options-2',
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
                ->call('create')
                ->assertHasErrors();
        } finally {
            $undoRepeaterFake();
        }

        $this->assertSame(0, Form::query()->whereIn('slug', ['broken-options', 'broken-options-2'])->count());
    }

    public function test_switching_away_from_select_strips_stale_options(): void
    {
        $form = Form::factory()->create([
            'fields' => [[
                'name' => 'topic',
                'type' => 'select',
                'required' => true,
                'label' => ['en' => 'Topic'],
                'options' => [['value' => 'billing', 'label' => ['en' => 'Billing']]],
            ]],
        ]);

        $this->actingAs($this->admin());

        $undoRepeaterFake = Repeater::fake();

        try {
            Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
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

        $this->assertArrayNotHasKey('options', $form->fresh()->fields[0]);
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
