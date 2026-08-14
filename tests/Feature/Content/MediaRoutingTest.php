<?php

namespace Tests\Feature\Content;

use App\Models\TeamMember;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Auth\AdminTestCase;

/**
 * Media URL routing.
 *
 * Guards the CP-8 regression where the PRIVATE local disk claimed /storage:
 * with no explicit url, FilesystemServiceProvider::serveFiles() defaulted it
 * to /storage and registered GET storage/{path} (storage.local) on every
 * host. That route then captured every public-disk image URL and aborted 403
 * at ServeFile's signature check, because public URLs are correctly
 * unsigned. /storage is now reserved for the public disk's symlink and the
 * private disk serves from /private-storage.
 *
 * Nothing here touches the filesystem symlink: public/storage is a
 * deployment artifact created by `php artisan storage:link`, not something a
 * test may assume, create or assert. These cases prove URL GENERATION and
 * ROUTE OWNERSHIP only, so they behave identically on any machine.
 */
class MediaRoutingTest extends AdminTestCase
{
    private function routeUriByName(string $name): ?string
    {
        $route = Route::getRoutes()->getByName($name);

        return $route?->uri();
    }

    public function test_the_private_local_disk_serves_from_its_own_prefix(): void
    {
        $this->assertSame('private-storage/{path}', $this->routeUriByName('storage.local'));
        $this->assertSame('private-storage/{path}', $this->routeUriByName('storage.local.upload'));

        // The configured URL must also carry the prefix, since that is what
        // any future signed private URL is built from.
        $this->assertStringEndsWith('/private-storage', (string) config('filesystems.disks.local.url'));

        // Signed private delivery stays enabled — the audit found no current
        // consumer, but the capability is deliberately preserved.
        $this->assertTrue(config('filesystems.disks.local.serve'));
    }

    public function test_no_route_claims_the_public_storage_prefix(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertNotSame(
                'storage/{path}',
                $route->uri(),
                'the public /storage prefix must belong to the symlink alone, not a served disk',
            );
        }

        $this->assertNull($this->routeUriByName('storage.public'));
    }

    public function test_a_missing_public_storage_path_is_not_found_rather_than_forbidden(): void
    {
        // The regression signature was 403 on EVERY /storage path, present or
        // absent, because the private disk's signature check ran first.
        $this->get($this->publicHost.'/storage/team/does-not-exist.jpg')->assertNotFound();
    }

    public function test_an_unsigned_private_storage_request_is_rejected(): void
    {
        // ServeFile aborts before touching the filesystem when the signature
        // is absent, so this holds whether or not the path exists. Outside
        // production the framework's chosen status is 403.
        $this->assertFalse($this->app->environment('production'), 'suite must not run in production');

        $this->get($this->publicHost.'/private-storage/anything.txt')->assertForbidden();
    }

    public function test_team_image_accessors_use_the_configured_public_media_disk(): void
    {
        $member = TeamMember::factory()->create([
            'photo_path' => 'team/photo-fixture.jpg',
            'licence_image_path' => 'team/licences/licence-fixture.png',
        ]);

        $disk = Storage::disk((string) config('platform.media_disk'));

        $this->assertSame($disk->url('team/photo-fixture.jpg'), $member->photoUrl());
        $this->assertSame($disk->url('team/licences/licence-fixture.png'), $member->licenceImageUrl());

        foreach ([$member->photoUrl(), $member->licenceImageUrl()] as $url) {
            $this->assertStringStartsWith('/storage/', (string) parse_url((string) $url, PHP_URL_PATH));
            // Public assets are deliberately unsigned.
            $this->assertNull(parse_url((string) $url, PHP_URL_QUERY));
        }
    }
}
