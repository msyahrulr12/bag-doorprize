<?php

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
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
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

        'core_t24_sftp' => [
            'driver' => 'sftp',
            'host' => env('CORE_T24_SFTP_HOST'),

            // Settings for basic authentication...
            'username' => env('CORE_T24_SFTP_USERNAME'),
            'password' => env('CORE_T24_SFTP_PASSWORD'),

            // Settings for SSH key-based authentication with encryption password...
            // 'privateKey' => env('CORE_T24_SFTP_PRIVATE_KEY'),
            // 'passphrase' => env('CORE_T24_SFTP_PASSPHRASE'),

            // Settings for file / directory permissions...
            'visibility' => env('CORE_T24_SFTP_VISIBILITY', 'private'), // `private` = 0600, `public` = 0644
            'directory_visibility' => env('CORE_T24_SFTP_DIR_VISIBILITY', 'private'), // `private` = 0700, `public` = 0755

            // Optional SFTP Settings...
            // 'hostFingerprint' => env('SFTP_HOST_FINGERPRINT'),
            // 'maxTries' => 4,
            // 'passphrase' => env('SFTP_PASSPHRASE'),
            'port' => (int) env('CORE_T24_SFTP_PORT', 22),
            'root' => env('CORE_T24_SFTP_ROOT', ''),
            // 'timeout' => 45,
            // 'useAgent' => true,
        ],

        'tmp' => [
            'driver' => 'local',
            'root' => sys_get_temp_dir(), // Uses the system's temporary directory (e.g., /tmp)
            'visibility' => 'private',
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
