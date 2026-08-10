<?php

namespace Tests\Feature\Settings;

use App\Filament\Pages\ManageSiteSettings;
use App\Models\SiteSettings;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Auth\AdminTestCase;

/**
 * Authorization, size limits, serialization hygiene, and the
 * database-enforced singleton for the super-admin-only site settings.
 */
class SiteSettingsTest extends AdminTestCase
{
    public function test_super_admin_can_view_and_update_settings(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'custom_head_start' => '<meta name="hs" content="1">',
                'custom_head_end' => '<style>.he{}</style>',
                'custom_body_start' => '<div id="bs"></div>',
                'custom_body_end' => '<script>var be=1;</script>',
                'cal_booking_url' => 'https://cal.com/example-team/consultation',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = SiteSettings::query()->sole();

        $this->assertTrue($settings->singleton);
        $this->assertSame('<meta name="hs" content="1">', $settings->custom_head_start);
        $this->assertSame('<script>var be=1;</script>', $settings->custom_body_end);
        $this->assertSame('https://cal.com/example-team/consultation', $settings->cal_booking_url);
    }

    public function test_admin_and_editor_cannot_access_the_page(): void
    {
        $this->actingAs($this->admin());
        $this->get(ManageSiteSettings::getUrl())->assertForbidden();

        $this->actingAs($this->editor());
        $this->get(ManageSiteSettings::getUrl())->assertForbidden();
    }

    public function test_inactive_super_admin_is_denied(): void
    {
        $inactive = User::factory()->inactive()->create();
        $inactive->assignRole('super_admin');

        $this->actingAs($inactive);

        $this->get(ManageSiteSettings::getUrl())->assertForbidden();
    }

    public function test_forged_save_request_by_admin_is_denied(): void
    {
        // Bypassing navigation and mount entirely: calling the write path
        // directly must still 403 — the visible UI is never the authority.
        $this->actingAs($this->admin());

        try {
            (new ManageSiteSettings)->save();

            $this->fail('Expected a 403 HttpException.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(0, SiteSettings::query()->count());
    }

    public function test_snippet_size_limit_is_enforced_in_the_form(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'custom_head_start' => str_repeat('a', SiteSettings::SNIPPET_MAX_LENGTH + 1),
            ])
            ->call('save')
            ->assertHasFormErrors(['custom_head_start']);
    }

    public function test_persistence_guards_reject_oversized_and_invalid_values(): void
    {
        $settings = SiteSettings::instance();

        try {
            $settings->fill(['custom_head_start' => str_repeat('a', SiteSettings::SNIPPET_MAX_LENGTH + 1)])->save();
            $this->fail('Expected the oversized-snippet exception.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('custom_head_start', $exception->getMessage());
        }

        try {
            $settings->refresh()->forceFill(['custom_body_end' => ['not', 'a', 'string']])->save();
            $this->fail('Expected the non-string-snippet exception.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('custom_body_end', $exception->getMessage());
        }

        try {
            $settings->refresh()->fill(['cal_booking_url' => 'https://evil.example.com/booking'])->save();
            $this->fail('Expected the Cal URL exception.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('cal_booking_url', $exception->getMessage());
        }

        // Nothing invalid was persisted.
        $fresh = SiteSettings::query()->sole();
        $this->assertNull($fresh->custom_head_start);
        $this->assertNull($fresh->custom_body_end);
        $this->assertNull($fresh->cal_booking_url);
    }

    public function test_snippets_are_hidden_from_serialization(): void
    {
        $settings = SiteSettings::instance();
        $settings->fill(['custom_head_start' => '<script>secret()</script>'])->save();

        $serialized = $settings->fresh()->toArray();

        foreach (SiteSettings::CUSTOM_CODE_POSITIONS as $position) {
            $this->assertArrayNotHasKey($position, $serialized);
        }

        $this->assertStringNotContainsString('secret()', json_encode($settings->fresh()));
    }

    public function test_database_enforces_a_single_row(): void
    {
        SiteSettings::instance();

        // PostgreSQL marks the surrounding transaction as failed after a
        // constraint violation; nested DB::transaction() calls create
        // savepoints so the RefreshDatabase test transaction survives.
        try {
            DB::transaction(
                fn () => SiteSettings::query()->forceCreate(['singleton' => true])
            );
            $this->fail('Expected the unique-violation exception.');
        } catch (QueryException) {
            // Expected: unique index on singleton.
        }

        try {
            DB::transaction(
                fn () => SiteSettings::query()->forceCreate(['singleton' => false])
            );
            $this->fail('Expected the check-constraint exception.');
        } catch (QueryException) {
            // Expected: CHECK (singleton = TRUE).
        }

        $this->assertSame(1, SiteSettings::query()->count());
    }
}
