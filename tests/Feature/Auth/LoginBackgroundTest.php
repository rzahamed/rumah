<?php

namespace Tests\Feature\Auth;

/**
 * The auth-page iridescent background: present on the simple-layout auth
 * pages, absent from the authenticated panel, with reduced-motion and
 * no-WebGL fallbacks in place.
 */
class LoginBackgroundTest extends AdminTestCase
{
    public function test_login_page_renders_background_canvas_script_and_static_fallback(): void
    {
        $response = $this->get($this->adminHost.'/login');

        $response->assertOk();
        $response->assertSee('admin-auth-bg-canvas', escape: false);
        $response->assertSee('js/admin-auth-bg.js', escape: false);
        // Static no-WebGL fallback gradient.
        $response->assertSee('linear-gradient(160deg', escape: false);
    }

    public function test_password_reset_request_page_renders_the_background(): void
    {
        // Login, password-reset request, and password-reset reset all use
        // the same simple layout — the ONLY template rendering the
        // SIMPLE_LAYOUT_START hook.
        $response = $this->get($this->adminHost.'/password-reset/request');

        $response->assertOk();
        $response->assertSee('admin-auth-bg-canvas', escape: false);
    }

    public function test_authenticated_panel_does_not_render_the_background(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get($this->adminHost.'/');

        $response->assertOk();
        $response->assertDontSee('admin-auth-bg-canvas');
    }

    public function test_background_script_handles_reduced_motion(): void
    {
        $script = public_path('js/admin-auth-bg.js');

        $this->assertFileExists($script);

        $contents = (string) file_get_contents($script);

        $this->assertStringContainsString('prefers-reduced-motion', $contents);
        $this->assertStringContainsString('renderStaticFrame', $contents);
    }
}
