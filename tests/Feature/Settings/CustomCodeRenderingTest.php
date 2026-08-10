<?php

namespace Tests\Feature\Settings;

use App\Models\SiteSettings;
use Tests\Feature\Auth\AdminTestCase;

/**
 * Custom code snippets appear at exactly four public-layout positions,
 * stay literal (never compiled as Blade/PHP), render nothing when empty,
 * and never reach any admin-host response.
 */
class CustomCodeRenderingTest extends AdminTestCase
{
    private function seedSnippets(): void
    {
        SiteSettings::instance()->fill([
            'custom_head_start' => '<!-- PROBE-HEAD-START -->',
            'custom_head_end' => '<!-- PROBE-HEAD-END -->',
            'custom_body_start' => '<!-- PROBE-BODY-START -->',
            'custom_body_end' => '<!-- PROBE-BODY-END -->',
        ])->save();
    }

    public function test_snippets_render_at_the_four_exact_positions(): void
    {
        $this->seedSnippets();

        $html = $this->get($this->publicHost.'/')->assertOk()->getContent();

        // Each snippet renders exactly once — duplicated rendering would
        // still satisfy a pure position-order check.
        foreach ([
            'PROBE-HEAD-START',
            'PROBE-HEAD-END',
            'PROBE-BODY-START',
            'PROBE-BODY-END',
        ] as $probe) {
            $this->assertSame(1, substr_count($html, $probe), "Unexpected render count for {$probe}");
        }

        $positions = [
            'head open' => strpos($html, '<head>'),
            'head start snippet' => strpos($html, '<!-- PROBE-HEAD-START -->'),
            'charset meta' => strpos($html, '<meta charset'),
            'head end snippet' => strpos($html, '<!-- PROBE-HEAD-END -->'),
            'head close' => strpos($html, '</head>'),
            'body open' => strpos($html, '<body>'),
            'body start snippet' => strpos($html, '<!-- PROBE-BODY-START -->'),
            'page content' => strpos($html, 'public frontend placeholder'),
            'body end snippet' => strpos($html, '<!-- PROBE-BODY-END -->'),
            'body close' => strpos($html, '</body>'),
        ];

        foreach ($positions as $name => $position) {
            $this->assertNotFalse($position, "Missing marker: {$name}");
        }

        $ordered = array_values($positions);

        for ($i = 1; $i < count($ordered); $i++) {
            $this->assertGreaterThan(
                $ordered[$i - 1],
                $ordered[$i],
                'Document-order violation at position index '.$i,
            );
        }
    }

    public function test_script_content_renders_verbatim(): void
    {
        SiteSettings::instance()->fill([
            'custom_body_end' => '<script>window.__probe = 1;</script>',
        ])->save();

        $html = $this->get($this->publicHost.'/')->assertOk()->getContent();

        $this->assertStringContainsString('<script>window.__probe = 1;</script>', $html);
    }

    public function test_blade_and_php_in_snippets_stay_literal(): void
    {
        SiteSettings::instance()->fill([
            'custom_body_start' => '{{ 7777 + 3333 }} @php echo 9999 * 9; @endphp',
        ])->save();

        $html = $this->get($this->publicHost.'/')->assertOk()->getContent();

        // Stored text appears exactly as stored…
        $this->assertStringContainsString('{{ 7777 + 3333 }}', $html);
        $this->assertStringContainsString('@php', $html);
        // …and was never evaluated.
        $this->assertStringNotContainsString('11110', $html);
        $this->assertStringNotContainsString('89991', $html);
    }

    public function test_empty_snippets_render_nothing(): void
    {
        // No settings row at all.
        $this->get($this->publicHost.'/')->assertOk()->assertDontSee('PROBE-', false);

        // A row whose snippet columns are all null behaves identically.
        SiteSettings::instance();

        $this->get($this->publicHost.'/')->assertOk()->assertDontSee('PROBE-', false);
    }

    public function test_snippets_never_render_on_the_admin_host(): void
    {
        $this->seedSnippets();

        // Filament login page.
        $this->get($this->adminHost.'/login')
            ->assertOk()
            ->assertDontSee('PROBE-', false);

        // Panel root (redirects toward auth — still no snippet leakage).
        $this->get($this->adminHost.'/')
            ->assertDontSee('PROBE-', false);

        // Invitation endpoint (404 for an unknown token) — snippets must
        // not appear on any admin-host response, error pages included.
        $this->get($this->adminHost.'/invitation/'.str_repeat('a', 64))
            ->assertNotFound()
            ->assertDontSee('PROBE-', false);
    }
}
