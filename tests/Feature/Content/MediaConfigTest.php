<?php

namespace Tests\Feature\Content;

use League\Flysystem\GoogleCloudStorage\UniformBucketLevelAccessVisibility;
use Tests\TestCase;

/**
 * GCS disk configuration INVARIANTS, asserted without resolving the disks —
 * definitions alone must never construct a client or contact GCP. Nothing
 * here pins environment-specific values (buckets, prefixes, key paths,
 * custom CDN hostnames), so the suite stays green when real local GCP
 * values replace the placeholders. Behavioral media coverage lives in
 * TeamPhotoUploadTest on fake storage.
 */
class MediaConfigTest extends TestCase
{
    public function test_configured_media_disk_is_a_defined_disk(): void
    {
        $mediaDisk = config('platform.media_disk');

        $this->assertIsString($mediaDisk);
        $this->assertArrayHasKey($mediaDisk, config('filesystems.disks'));
    }

    public function test_configured_blog_featured_disk_is_a_defined_disk(): void
    {
        $blogDisk = config('platform.blog_featured_disk');

        $this->assertIsString($blogDisk);
        $this->assertArrayHasKey($blogDisk, config('filesystems.disks'));
        $this->assertNotSame('', config('platform.blog_featured_dir'));
    }

    public function test_gcs_disks_declare_correct_driver_and_visibility_intent(): void
    {
        $private = config('filesystems.disks.gcs_private');
        $public = config('filesystems.disks.gcs_public_website');

        $this->assertSame('gcs', $private['driver']);
        $this->assertSame('gcs', $public['driver']);

        $this->assertSame('private', $private['visibility']);
        $this->assertSame('public', $public['visibility']);
    }

    public function test_gcs_disks_use_the_uniform_access_visibility_handler(): void
    {
        foreach (['gcs_private', 'gcs_public_website'] as $name) {
            $this->assertSame(
                UniformBucketLevelAccessVisibility::class,
                config("filesystems.disks.{$name}.visibility_handler"),
                "{$name} must use the uniform bucket-level access handler",
            );
        }
    }

    public function test_gcs_disks_fail_loudly(): void
    {
        foreach (['gcs_private', 'gcs_public_website'] as $name) {
            $this->assertTrue(config("filesystems.disks.{$name}.throw"), "{$name} must throw on failure");
            $this->assertTrue(config("filesystems.disks.{$name}.report"), "{$name} must report failures");
        }
    }

    public function test_non_empty_prefixes_carry_no_surrounding_slashes(): void
    {
        foreach (['gcs_private', 'gcs_public_website'] as $name) {
            $prefix = config("filesystems.disks.{$name}.path_prefix");

            $this->assertIsString($prefix, "{$name} prefix must be a string");

            if ($prefix !== '') {
                $this->assertSame(
                    trim($prefix, '/'),
                    $prefix,
                    "{$name} prefix must carry no surrounding slashes",
                );
            }
        }
    }

    public function test_key_file_path_is_null_or_a_non_empty_string(): void
    {
        // The config normalizes an empty GOOGLE_CLOUD_KEY_FILE to null so
        // the client falls back to Application Default Credentials; a
        // configured path stays supported. An empty string must never
        // survive to the client options.
        foreach (['gcs_private', 'gcs_public_website'] as $name) {
            $keyFilePath = config("filesystems.disks.{$name}.key_file_path");

            $this->assertTrue(
                $keyFilePath === null || (is_string($keyFilePath) && $keyFilePath !== ''),
                "{$name} key_file_path must be null (ADC) or a non-empty path",
            );
        }
    }

    public function test_public_base_url_matches_bucket_configuration(): void
    {
        $disk = config('filesystems.disks.gcs_public_website');
        $bucket = $disk['bucket'];
        $uri = $disk['storage_api_uri'];
        $prefix = $disk['path_prefix'];

        if (is_string($bucket) && $bucket !== '') {
            $this->assertIsString($uri);
            $this->assertNotSame('', $uri, 'storage_api_uri must be a non-empty base URL');

            // The config rtrims both the custom and the derived form, so a
            // trailing slash (and the double slash it would create in file
            // URLs) can never appear.
            $this->assertFalse(
                str_ends_with($uri, '/'),
                'storage_api_uri must never end with a slash',
            );

            if ($prefix !== '') {
                // The adapter appends "prefix/stored-path" itself: a base
                // already ending in the prefix would duplicate it.
                $this->assertFalse(
                    str_ends_with($uri, '/'.$prefix),
                    'storage_api_uri must not already contain the path prefix',
                );
            }

            // Only the DERIVED form (no custom PUBLIC_ASSET_URL) promises
            // the bucket in the URL; a CDN/custom hostname may legitimately
            // omit the bucket name entirely.
            if ((string) env('PUBLIC_ASSET_URL') === '') {
                $this->assertSame(
                    1,
                    substr_count($uri, $bucket),
                    'derived storage_api_uri must name the bucket exactly once',
                );
            }
        } else {
            // No bucket configured: no derived URL, never a malformed one.
            $this->assertNull($uri);
        }
    }
}
