<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\FormSubmissionResource;
use App\Filament\Resources\PostResource;
use App\Filament\Resources\TeamMemberResource;
use App\Filament\Resources\UserResource;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\AdminTestCase;

/**
 * CP-9 F — Arabic completeness of the admin panel.
 *
 * The panel really does run in Arabic (SetAdminLocale applies the signed-in
 * user's preferred_admin_locale, and a topbar switcher changes it), so any
 * project-controlled English string is a defect rather than a cosmetic gap.
 *
 * Two distinct failure modes are covered:
 *
 *  - Laravel ships NO Arabic validation set, so before lang/ar/validation.php
 *    existed every message fell back to English. These tests assert the
 *    Arabic text itself, and additionally assert the English is GONE —
 *    a key that silently reverts to the fallback locale would otherwise
 *    still look "translated" to a naive contains-check.
 *
 *  - Filament derives model labels from the class name when a resource does
 *    not override them, which is English in every locale.
 *
 * Filament's own strings are deliberately NOT covered here: all ten packages
 * ship resources/lang/ar and resolve correctly, so asserting them would be
 * testing the framework.
 */
class AdminArabicTest extends AdminTestCase
{
    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function validationMessages(): array
    {
        return [
            'required' => ['validation.required', 'حقل الاسم مطلوب.', ['attribute' => 'الاسم']],
            'email' => ['validation.email', 'يجب أن يكون حقل البريد بريداً إلكترونياً صحيحاً.', ['attribute' => 'البريد']],
            'unique' => ['validation.unique', 'الرابط مستخدم من قبل.', ['attribute' => 'الرابط']],
        ];
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    #[DataProvider('validationMessages')]
    public function test_core_validation_messages_resolve_to_arabic(string $key, string $expected, array $replace): void
    {
        app()->setLocale('ar');

        $message = trans($key, $replace);

        $this->assertSame($expected, $message);
        // Not merely "is Arabic": prove the English fallback is not what
        // came back, and that the key itself resolved.
        $this->assertStringNotContainsString('The ', $message);
        $this->assertNotSame($key, $message, 'the translation key did not resolve');
    }

    public function test_arabic_validation_messages_still_substitute_placeholders(): void
    {
        app()->setLocale('ar');

        $message = trans('validation.max.string', ['attribute' => 'العنوان', 'max' => 255]);

        $this->assertSame('يجب ألا يزيد حقل العنوان عن 255 حرفاً.', $message);
        // No placeholder may survive substitution.
        $this->assertStringNotContainsString(':attribute', $message);
        $this->assertStringNotContainsString(':max', $message);

        // A message carrying three distinct placeholders, to prove the
        // Arabic wording kept all of them addressable by name.
        $conditional = trans('validation.required_if', [
            'attribute' => 'المدينة',
            'other' => 'الدولة',
            'value' => 'السعودية',
        ]);

        $this->assertSame('حقل المدينة مطلوب عندما يكون الدولة هو السعودية.', $conditional);
        $this->assertDoesNotMatchRegularExpression('/:[a-zA-Z_]+/', $conditional);
    }

    public function test_english_validation_still_resolves_from_the_framework(): void
    {
        // There is deliberately no lang/en/validation.php shadowing the
        // framework's own set; adding one would create a second copy to
        // keep in sync.
        app()->setLocale('en');

        $this->assertSame(
            'The full name field is required.',
            trans('validation.required', ['attribute' => 'full name']),
        );
    }

    /** @return array<string, array{class-string, string, string, string, string}> */
    public static function resourceLabels(): array
    {
        return [
            'posts' => [PostResource::class, 'Post', 'Posts', 'مقال', 'المقالات'],
            'categories' => [CategoryResource::class, 'Category', 'Categories', 'تصنيف', 'التصنيفات'],
            'team' => [TeamMemberResource::class, 'Team member', 'Team members', 'عضو الفريق', 'أعضاء الفريق'],
            'users' => [UserResource::class, 'User', 'Users', 'مستخدم', 'المستخدمون'],
            'submissions' => [FormSubmissionResource::class, 'Contact request', 'Contact requests', 'طلب تواصل', 'طلبات التواصل'],
        ];
    }

    /**
     * @param  class-string  $resource
     */
    #[DataProvider('resourceLabels')]
    public function test_resource_labels_translate_in_both_locales(
        string $resource,
        string $enSingular,
        string $enPlural,
        string $arSingular,
        string $arPlural,
    ): void {
        app()->setLocale('en');

        $this->assertSame($enSingular, $resource::getModelLabel());
        $this->assertSame($enPlural, $resource::getPluralModelLabel());

        app()->setLocale('ar');

        $this->assertSame($arSingular, $resource::getModelLabel());
        $this->assertSame($arPlural, $resource::getPluralModelLabel());
    }

    /**
     * Smaller diagnostic: the key itself carries the right value. Proving
     * the SWITCHER renders it is the rendered-page test below.
     */
    public function test_the_locale_switcher_key_names_english_in_arabic(): void
    {
        app()->setLocale('ar');

        $this->assertSame('الإنجليزية', __('users.locales.en'));
        $this->assertSame('العربية', __('users.locales.ar'));

        // The English panel keeps the endonym.
        app()->setLocale('en');

        $this->assertSame('English', __('users.locales.en'));
    }

    public function test_the_arabic_admin_panel_renders_rtl_translated_navigation_and_switcher(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['preferred_admin_locale' => 'ar'])->save();

        $html = $this->actingAs($admin)->get($this->adminHost.'/')->assertOk()->getContent();

        // Filament's own ar/layout.php supplies the direction.
        $this->assertStringContainsString('dir="rtl"', $html);

        // Project-controlled navigation groups, in Arabic.
        $this->assertStringContainsString(__('nav.groups.content', [], 'ar'), $html);
        $this->assertStringContainsString(__('nav.groups.administration', [], 'ar'), $html);

        // The REAL topbar switcher, not just the key: the button offers the
        // other locale, so an Arabic panel must render "الإنجليزية" and must
        // not render the English endonym as a button label.
        $this->assertStringContainsString('الإنجليزية', $html);
        $this->assertStringNotContainsString('>English<', $html);
    }
}
