<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Asserts the fail-closed host architecture:
 *  - public routes exist ONLY on the PUBLIC_APP_URL host,
 *  - the Filament panel exists ONLY on the ADMIN_DOMAIN host at its root,
 *  - neither leaks onto the other host or onto an unrelated host,
 *  - the admin panel is marked noindex; the public site is not.
 *
 * Host values come from phpunit <env> (basecms.test / admin.basecms.test).
 */
class HostIsolationTest extends TestCase
{
    private string $public = 'http://basecms.test';

    private string $admin = 'http://admin.basecms.test';

    private string $foreign = 'http://unrelated.test';

    public function test_public_root_serves_on_public_host(): void
    {
        $this->get($this->public.'/')->assertOk();
    }

    public function test_default_locale_prefix_redirects_permanently_to_root(): void
    {
        $response = $this->get($this->public.'/en');

        $response->assertStatus(301);
        // Route::redirect emits a root-relative Location ('/'), which browsers resolve.
        $this->assertSame('/', $response->headers->get('Location'));
    }

    public function test_arabic_locale_serves_localized_page(): void
    {
        $this->get($this->public.'/ar')->assertOk();
    }

    public function test_unsupported_locale_returns_404_on_public_host(): void
    {
        $this->get($this->public.'/fr')->assertNotFound();
    }

    public function test_admin_login_serves_on_admin_host(): void
    {
        $this->get($this->admin.'/login')->assertOk();
    }

    public function test_admin_root_redirects_guest_to_login(): void
    {
        $response = $this->get($this->admin.'/');

        $response->assertStatus(302);
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    public function test_admin_login_is_absent_on_public_host(): void
    {
        $this->get($this->public.'/login')->assertNotFound();
    }

    public function test_public_locale_route_is_absent_on_admin_host(): void
    {
        $this->get($this->admin.'/ar')->assertNotFound();
    }

    public function test_unrelated_host_returns_404_for_public_and_admin_paths(): void
    {
        $this->get($this->foreign.'/')->assertNotFound();
        $this->get($this->foreign.'/login')->assertNotFound();
    }

    public function test_noindex_header_present_on_admin_host(): void
    {
        $this->get($this->admin.'/login')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_noindex_header_absent_on_public_host(): void
    {
        $response = $this->get($this->public.'/');

        $this->assertNull($response->headers->get('X-Robots-Tag'));
    }
}
