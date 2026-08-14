<?php

use League\Flysystem\GoogleCloudStorage\UniformBucketLevelAccessVisibility;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // Signed-only private delivery, on its OWN prefix. Without an
            // explicit url, FilesystemServiceProvider::serveFiles() defaults
            // this disk to /storage and registers GET storage/{path}
            // (route name storage.local) on every host — which then captures
            // every PUBLIC disk URL and aborts 403 at the signature check in
            // ServeFile, because public URLs are correctly unsigned.
            // /storage is reserved for the public disk's symlink.
            //
            // Derived from APP_URL, never from request Host data.
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/private-storage',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
        | Google Cloud Storage (spatie/laravel-google-cloud-storage). Clients
        | are constructed ONLY when a disk is resolved — these definitions
        | alone never contact GCP. Empty GOOGLE_CLOUD_KEY_FILE means
        | Application Default Credentials; a key-file path is used only when
        | explicitly configured. Buckets use Uniform Bucket-Level Access:
        | public exposure comes from bucket IAM, never per-object ACLs.
        | throw/report are TRUE: a failed write must never leave the
        | database pointing at a file that was never stored.
        */

        'gcs_private' => [
            'driver' => 'gcs',
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'key_file_path' => env('GOOGLE_CLOUD_KEY_FILE') ?: null,
            'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
            'path_prefix' => trim((string) env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', 'dev'), '/'),
            'visibility' => 'private',
            'visibility_handler' => UniformBucketLevelAccessVisibility::class,
            'throw' => true,
            'report' => true,
        ],

        'gcs_public_website' => [
            'driver' => 'gcs',
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'key_file_path' => env('GOOGLE_CLOUD_KEY_FILE') ?: null,
            'bucket' => env('PUBLIC_ASSET_BUCKET'),
            'path_prefix' => trim((string) env('PUBLIC_ASSET_PREFIX', 'public-website'), '/'),
            'visibility' => 'public',
            'visibility_handler' => UniformBucketLevelAccessVisibility::class,
            // The adapter appends "path_prefix/stored-path" itself, so this
            // base must contain ONLY base URL + bucket. An explicit
            // PUBLIC_ASSET_URL (base-through-bucket, e.g. a CDN host bound
            // to the bucket) wins; otherwise derived from
            // PUBLIC_ASSET_BASE_URL + bucket — and only when a bucket is
            // configured, so placeholders yield null, never a malformed URL.
            'storage_api_uri' => env('PUBLIC_ASSET_URL')
                ? rtrim((string) env('PUBLIC_ASSET_URL'), '/')
                : (env('PUBLIC_ASSET_BUCKET')
                    ? rtrim((string) env('PUBLIC_ASSET_BASE_URL', 'https://storage.googleapis.com'), '/')
                        .'/'.trim((string) env('PUBLIC_ASSET_BUCKET'), '/')
                    : null),
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
