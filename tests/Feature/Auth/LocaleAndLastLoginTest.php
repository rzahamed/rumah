<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * The authenticated locale endpoint, its middleware effect, RTL rendering
 * on the public host, and last-login tracking via the Login event listener.
 */
class LocaleAndLastLoginTest extends AdminTestCase
{
    public function test_guest_locale_post_redirects_to_panel_login(): void
    {
        $response = $this->post($this->adminHost.'/locale', ['locale' => 'ar']);

        $response->assertStatus(302);
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    public function test_authenticated_user_can_persist_supported_locale(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post($this->adminHost.'/locale', ['locale' => 'ar'])
            ->assertStatus(302);

        $this->assertSame('ar', $admin->fresh()->preferred_admin_locale);
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $admin = $this->admin();
        $originalPreferredLocale = $admin->preferred_admin_locale;

        $this->actingAs($admin)
            ->from($this->adminHost.'/')
            ->post($this->adminHost.'/locale', ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertSame($originalPreferredLocale, $admin->fresh()->preferred_admin_locale);
    }

    public function test_preferred_locale_is_applied_on_admin_requests(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['preferred_admin_locale' => 'ar'])->save();

        $this->actingAs($admin)->get($this->adminHost.'/')->assertOk();

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_public_arabic_page_renders_rtl(): void
    {
        $response = $this->get($this->publicHost.'/ar');

        $response->assertOk();
        $response->assertSee('dir="rtl"', escape: false);
    }

    public function test_public_default_page_renders_ltr(): void
    {
        $response = $this->get($this->publicHost.'/');

        $response->assertOk();
        $response->assertSee('dir="ltr"', escape: false);
    }

    public function test_login_event_records_last_login_timestamp(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->last_login_at);

        event(new Login('web', $user, false));

        $this->assertNotNull($user->fresh()->last_login_at);
    }
}
