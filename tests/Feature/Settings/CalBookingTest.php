<?php

namespace Tests\Feature\Settings;

use App\Filament\Pages\ManageSiteSettings;
use App\Models\SiteSettings;
use App\Rules\ValidCalUrl;
use Livewire\Livewire;
use Tests\Feature\Auth\AdminTestCase;

/**
 * The Cal.com booking URL allowlist: HTTPS-only, approved hosts (exact or
 * subdomain), safe paths, and the settings-form wiring. Public Contact-page
 * embed rendering is asserted with the Contact checkpoint — no public page
 * consumes this setting yet.
 */
class CalBookingTest extends AdminTestCase
{
    public function test_rule_accepts_approved_hosts_and_paths(): void
    {
        foreach ([
            'https://cal.com/example-team/consultation',
            'https://app.cal.com/example-team/intro-call',
            'https://cal.com/a/b/c-d_e.f',
        ] as $url) {
            $this->assertTrue(ValidCalUrl::passes($url), "Should accept: {$url}");
        }
    }

    public function test_rule_supports_configured_custom_cal_domains(): void
    {
        config(['platform.cal_allowed_hosts' => ['book.example.com']]);

        $this->assertTrue(ValidCalUrl::passes('https://book.example.com/team/intro'));
        // The allowlist is authoritative — cal.com itself is no longer
        // approved once replaced by a custom list.
        $this->assertFalse(ValidCalUrl::passes('https://cal.com/team/intro'));
    }

    public function test_rule_rejects_unsafe_urls(): void
    {
        foreach ([
            'http://cal.com/team/intro',                       // not HTTPS
            'https://evil.example.com/team',                   // foreign host
            'https://cal.com.evil.example.com/team',           // suffix trick
            'https://xcal.com/team',                           // lookalike host
            'javascript:alert(1)',                             // scheme abuse
            'https://cal.com',                                 // missing path
            'https://cal.com/',                                // empty path
            'https://cal.com/team?embed=1',                    // query string
            'https://cal.com/team#frag',                       // fragment
            'https://user:pass@cal.com/team',                  // credentials
            'https://cal.com:8443/team',                       // explicit port
            'https://cal.com/team intro',                      // unsafe path
            'https://cal.com/'.str_repeat('a', 300),           // over-length
            '',                                                // empty
        ] as $url) {
            $this->assertFalse(ValidCalUrl::passes($url), "Should reject: {$url}");
        }
    }

    public function test_settings_form_validates_the_booking_url(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageSiteSettings::class)
            ->fillForm(['cal_booking_url' => 'https://evil.example.com/booking'])
            ->call('save')
            ->assertHasFormErrors(['cal_booking_url']);

        $this->assertNull(SiteSettings::query()->first()?->cal_booking_url);

        Livewire::test(ManageSiteSettings::class)
            ->fillForm(['cal_booking_url' => 'https://cal.com/example-team/consultation'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'https://cal.com/example-team/consultation',
            SiteSettings::query()->sole()->cal_booking_url,
        );
    }

    public function test_empty_booking_url_is_allowed(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageSiteSettings::class)
            ->fillForm(['cal_booking_url' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(SiteSettings::query()->sole()->cal_booking_url);
    }
}
