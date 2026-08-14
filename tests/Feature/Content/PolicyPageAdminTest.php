<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\PolicyPageResource;
use App\Filament\Resources\PolicyPageResource\Pages\EditPolicyPage;
use App\Filament\Resources\PolicyPageResource\Pages\ListPolicyPages;
use App\Models\PolicyPage;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\AdminTestCase;

/**
 * The admin surface for policy pages: administrator-only access, list + edit
 * only, and the publication gate that stops a blank legal page going live.
 */
class PolicyPageAdminTest extends AdminTestCase
{
    private function privacy(): PolicyPage
    {
        return PolicyPage::query()->where('key', PolicyPage::PRIVACY)->sole();
    }

    public function test_an_admin_can_open_the_resource_and_update_a_policy(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ListPolicyPages::class)
            ->assertOk()
            ->assertCanSeeTableRecords(PolicyPage::query()->get());

        $policy = $this->privacy();

        Livewire::test(EditPolicyPage::class, ['record' => $policy->getRouteKey()])
            ->assertOk()
            ->fillForm([
                'title' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
                'body' => ['en' => 'English body.', 'ar' => 'النص العربي.'],
                'is_published' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $policy->refresh();

        $this->assertSame('English body.', $policy->translate('body', 'en'));
        $this->assertSame('النص العربي.', $policy->translate('body', 'ar'));
    }

    public function test_the_admin_role_holds_the_policy_permissions_and_the_editor_does_not(): void
    {
        $admin = $this->admin();
        $editor = $this->editor();

        foreach (['policies.view', 'policies.update'] as $permission) {
            $this->assertTrue($admin->can($permission), "admin should hold {$permission}");
            $this->assertFalse($editor->can($permission), "editor must not hold {$permission}");
        }

        $policy = $this->privacy();

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', PolicyPage::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $policy));
        $this->assertTrue(Gate::forUser($editor)->denies('viewAny', PolicyPage::class));
        $this->assertTrue(Gate::forUser($editor)->denies('update', $policy));
    }

    public function test_an_editor_is_denied_the_policy_resource_pages_themselves(): void
    {
        // Permission booleans are not enough: the resource pages must
        // actually refuse an editor who navigates to them.
        $policy = $this->privacy();

        $this->actingAs($this->editor());

        $this->get(PolicyPageResource::getUrl('index'))->assertForbidden();
        $this->get(PolicyPageResource::getUrl('edit', ['record' => $policy]))->assertForbidden();
    }

    public function test_create_and_delete_stay_unavailable_even_for_an_active_super_admin(): void
    {
        // An active super admin passes every policy check through the
        // Gate::before override, so the guarantee has to come from the
        // resource's structure rather than from PolicyPagePolicy alone.
        $superAdmin = $this->superAdmin();
        $policy = $this->privacy();

        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $policy), 'Gate::before should still grant update');

        $this->assertFalse(PolicyPageResource::canCreate());
        $this->assertFalse(PolicyPageResource::canDelete($policy));
        $this->assertFalse(PolicyPageResource::canDeleteAny());

        // No create route exists at all, and the edit page registers no
        // header actions, so there is no delete control to reach.
        $this->assertSame(['index', 'edit'], array_keys(PolicyPageResource::getPages()));

        $this->actingAs($superAdmin);

        Livewire::test(EditPolicyPage::class, ['record' => $policy->getRouteKey()])
            ->assertOk()
            ->assertActionDoesNotExist('delete');

        $this->assertSame(2, PolicyPage::query()->count());
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function blankBodyLocales(): array
    {
        return [
            'arabic blank' => [['en' => 'English body only.', 'ar' => '']],
            'english blank' => [['en' => '', 'ar' => 'النص العربي فقط.']],
        ];
    }

    /**
     * @param  array<string, string>  $body
     */
    #[DataProvider('blankBodyLocales')]
    public function test_publishing_is_halted_when_any_locale_body_is_blank(array $body): void
    {
        $this->actingAs($this->admin());

        $policy = $this->privacy();

        Livewire::test(EditPolicyPage::class, ['record' => $policy->getRouteKey()])
            ->fillForm([
                'title' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
                'body' => $body,
                'is_published' => true,
            ])
            ->call('save');

        $policy->refresh();

        // The save halted: nothing was written, so the page cannot go live
        // with either locale body blank.
        $this->assertFalse($policy->is_published);
        $this->assertSame('', $policy->translate('body', 'en'));
        $this->assertSame('', $policy->translate('body', 'ar'));
    }

    public function test_an_incomplete_policy_can_still_be_saved_while_unpublished(): void
    {
        $this->actingAs($this->admin());

        $policy = $this->privacy();

        Livewire::test(EditPolicyPage::class, ['record' => $policy->getRouteKey()])
            ->fillForm([
                'title' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
                'body' => ['en' => 'Draft in progress.', 'ar' => ''],
                'is_published' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $policy->refresh();

        // Drafting incrementally is allowed; only publication is gated.
        $this->assertSame('Draft in progress.', $policy->translate('body', 'en'));
        // Asserted on the RAW stored value: translate() intentionally falls
        // back to the default locale, so it would report the English draft
        // for a missing Arabic body. A published policy can never reach that
        // state, because the publication gate requires both locales.
        $this->assertTrue(blank($policy->body['ar'] ?? null));
        $this->assertFalse($policy->is_published);
    }

    public function test_the_canonical_key_cannot_be_changed_through_the_form(): void
    {
        $this->actingAs($this->admin());

        $policy = $this->privacy();

        Livewire::test(EditPolicyPage::class, ['record' => $policy->getRouteKey()])
            ->fillForm([
                'key' => 'terms-of-use',
                'title' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
                'body' => ['en' => 'Body.', 'ar' => 'نص.'],
                'is_published' => false,
            ])
            ->call('save');

        $policy->refresh();

        // The field is disabled and never dehydrated, so the routes and
        // footer links keep pointing at a stable key.
        $this->assertSame(PolicyPage::PRIVACY, $policy->key);
        $this->assertSame(2, PolicyPage::query()->count());
    }
}
