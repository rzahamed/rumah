<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\FormSubmission;
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
}
