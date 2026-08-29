<?php

/*
|--------------------------------------------------------------------------
| Media — where video lives and how it is processed
|--------------------------------------------------------------------------
|
| Storage itself is NOT configured here: it is attached at runtime as one or
| more media vaults (see App\Media\MediaVaults and the Storage admin page).
| With nothing attached the platform keeps media on local disk, and that is a
| complete configuration, not a fallback.
|
| What lives here is the machinery around it — where local disk is, which
| binaries do the work, and what the encoder is asked for.
|
*/

return [

    /*
    | The local media root. PRIVATE — outside the public disk, served only by a
    | controller that authorises first. Holds everything when no vault is
    | attached, plus the derived HLS ladders and fetched copies always.
    */
    'local_root' => env('MEDIA_LOCAL_ROOT', storage_path('app/media')),

    /*
    | Serving
    |
    | `internal_prefix` is an nginx `internal:` location that maps onto the local
    | media root. When set, an authorised request is answered with an
    | X-Accel-Redirect and NGINX sends the bytes — one PHP worker per request
    | instead of one per segment, which is the difference between a hall watching
    | a match and a hall watching a spinner.
    |
    | Unset (the default) it falls back to streaming through PHP, which works and
    | does not scale. Set it once nginx has the matching location.
    */
    'internal_prefix' => env('MEDIA_INTERNAL_PREFIX'),

    /*
    | Binaries
    |
    | jellyfin-ffmpeg where it exists (it ships with NVENC and the codecs), the
    | distribution build otherwise. Resolved at runtime by App\Media\Ffmpeg so a
    | box without either says so plainly instead of failing mid-encode.
    */
    'ffmpeg' => env('MEDIA_FFMPEG', '/usr/lib/jellyfin-ffmpeg/ffmpeg'),
    'ffprobe' => env('MEDIA_FFPROBE', '/usr/lib/jellyfin-ffmpeg/ffprobe'),
    'ffmpeg_fallbacks' => ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'],
    'ffprobe_fallbacks' => ['/usr/bin/ffprobe', '/usr/local/bin/ffprobe'],

    'smbclient' => env('MEDIA_SMBCLIENT', 'smbclient'),
    'smb_timeout' => (int) env('MEDIA_SMB_TIMEOUT', 20),

    /*
    | Encoding
    |
    | The GPUs are passed through to this container, so NVENC is the default and
    | libx264 is the fallback — chosen per job by probing the encoder rather than
    | trusting a flag, because a card that is busy or missing must not hang a
    | competition's footage.
    |
    | `gpu_device` picks the card. There are two; one is left for whatever else
    | wants it.
    */
    'gpu' => [
        'enabled' => (bool) env('MEDIA_GPU', true),
        'encoder' => env('MEDIA_GPU_ENCODER', 'h264_nvenc'),
        'device' => (int) env('MEDIA_GPU_DEVICE', 0),
        'hwaccel' => env('MEDIA_GPU_HWACCEL', 'cuda'),
        'preset' => env('MEDIA_GPU_PRESET', 'p4'),
        'cpu_encoder' => 'libx264',
        'cpu_preset' => 'veryfast',
    ],

    /*
    | The HLS ladder. Six-second segments with independent segments, which is
    | what lets a phone on hall wifi switch rung without stalling.
    */
    'hls' => [
        'segment_seconds' => 6,
        'ladder' => [
            ['name' => '480p', 'height' => 480, 'bitrate' => '1000k'],
            ['name' => '720p', 'height' => 720, 'bitrate' => '2500k'],
            ['name' => '1080p', 'height' => 1080, 'bitrate' => '5000k'],
        ],
    ],

    /*
    | Ingest limits. A camera declares its clip's size when it files it, and an
    | upload may not exceed what was declared — without that, a phone can fill
    | the event server's disk.
    */
    'max_clip_bytes' => (int) env('MEDIA_MAX_CLIP_BYTES', 8 * 1024 * 1024 * 1024),
    'max_chunk_bytes' => (int) env('MEDIA_MAX_CHUNK_BYTES', 8 * 1024 * 1024),

];
