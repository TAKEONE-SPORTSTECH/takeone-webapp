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

    /*
    |--------------------------------------------------------------------------
    | Hand file delivery back to the web server
    |--------------------------------------------------------------------------
    |
    | Every file is served through FileController so that access is decided in
    | code. That check is cheap; streaming the BYTES through PHP is not, and on
    | an image-heavy page it ties up a worker per picture.
    |
    | With mod_xsendfile, PHP does the authorisation and then names the file in
    | an X-Sendfile header — Apache sends it, with its own sendfile(2) path,
    | caching and range support, and the PHP worker is free immediately.
    |
    | OFF by default, and it must stay off until the module is actually enabled:
    | if Apache does not understand the header it passes it to the browser and
    | the response body is EMPTY, so every file silently breaks. Turn it on with
    | FILE_XSENDFILE=true only after:
    |
    |     sudo apt-get install libapache2-mod-xsendfile
    |     sudo a2enmod xsendfile
    |     # in the vhost:  XSendFile On
    |     #                XSendFilePath /var/www/takeone/storage/app
    |     sudo systemctl reload apache2
    |
    */

    'x_sendfile' => (bool) env('FILE_XSENDFILE', false),

    'disks' => [

        /*
        |----------------------------------------------------------------------
        | ONE storage root
        |----------------------------------------------------------------------
        |
        | There is no public/private split any more. Both names resolve to the
        | same directory, and NOTHING under it is reachable from the web: the
        | `public/storage` symlink is gone and every file is served by
        | App\Http\Controllers\FileController, which asks App\Support\FileAccess
        | who is looking.
        |
        | The split used to BE the access-control decision — a file's folder
        | decided whether the world could read it, and a file written to the
        | wrong one was public with no way to take it back. Access is decided in
        | code now, so the two disks only have to agree on where bytes live.
        |
        | Both are kept as names so the ~100 existing `disk('public')` call
        | sites keep working; they are the same disk.
        */

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'visibility' => 'private',
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

    /*
     * Deliberately EMPTY.
     *
     * `storage:link` used to publish storage/app/public into the web root, and
     * that symlink WAS the access-control decision — anything behind it was
     * readable by anyone holding the URL. Files are served by FileController
     * now, which asks FileAccess who is looking, so re-creating this link would
     * quietly re-expose every file it covers.
     */
    'links' => [],

];
