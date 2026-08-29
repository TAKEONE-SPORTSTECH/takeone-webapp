<?php

/*
|--------------------------------------------------------------------------
| Live streaming
|--------------------------------------------------------------------------
|
| The media plane is MediaMTX, running on loopback beside this application.
| Apache proxies /live-rtc and /live-hls to it (see the vhost). Nothing here is
| a secret except the TURN credential, which comes from the environment.
|
| Authorisation is NOT here: the media server asks the application about every
| publish and every read (App\Events\Support\Live\LiveAuthController).
|
*/

return [

    // MediaMTX's control API, loopback only. Used to read publisher state and to
    // find a recording when a broadcast ends.
    'api_url' => env('LIVE_API_URL', 'http://127.0.0.1:9997'),

    /*
    | Where MediaMTX writes recordings.
    |
    | Deliberately a scratch directory, NOT the media root: what lands here is
    | raw fMP4 segments from a live session, which are ingested into the media
    | layer (and thus onto whatever storage is attached) and then deleted. It is
    | a staging area, and anything left in it is either in flight or a failure.
    */
    'record_path' => env('LIVE_RECORD_PATH', storage_path('app/private/live-recordings')),

    /*
    | The longest a single broadcast can be and still be somebody's BOUT video.
    |
    | A camera left running on a mat for an afternoon is footage of the mat, even
    | when nobody ever repointed the stream — and filing three hours of fourteen
    | fights as one athlete's bout video is the wrong-athlete failure arriving
    | with nothing obviously wrong. Anything longer than this is filed under the
    | mat, which is where somebody looking for that afternoon will find it.
    |
    | Generous on purpose: a long karate final with two extensions and a video
    | review is minutes, not tens of minutes.
    */
    'bout_max_seconds' => (int) env('LIVE_BOUT_MAX_SECONDS', 20 * 60),

    /*
    | The measurement harness (App\Events\Support\Live\LabController).
    |
    | Absent — the default and the state of production — its routes do not exist.
    | Set LAB_LIVE_KEY only while measuring, and unset it afterwards.
    */
    'lab_key' => env('LAB_LIVE_KEY', ''),
    'lab_event' => env('LAB_LIVE_EVENT', ''),
    'lab_stream' => env('LAB_LIVE_STREAM', ''),

    /*
    |--------------------------------------------------------------------------
    | ICE
    |--------------------------------------------------------------------------
    |
    | STUN lets a browser discover its public address. TURN RELAYS the media when
    | a direct path cannot be made — which, until a UDP port is forwarded to this
    | server, is every broadcaster outside the venue's own network.
    |
    | With no TURN configured, publishing works from the LAN only. Watching works
    | from anywhere over LL-HLS, because that is ordinary HTTPS.
    |
    */
    'stun_url' => env('LIVE_STUN_URL', 'stun:stun.l.google.com:19302'),
    'turn_url' => env('LIVE_TURN_URL'),
    'turn_username' => env('LIVE_TURN_USERNAME'),
    'turn_credential' => env('LIVE_TURN_CREDENTIAL'),

    /*
    |--------------------------------------------------------------------------
    | Capture defaults
    |--------------------------------------------------------------------------
    |
    | What the phone is ASKED for. A camera that cannot do 1080p returns the
    | closest it can, and WebRTC's own congestion control lowers the bitrate when
    | the uplink cannot sustain it — so this is a ceiling, not a promise.
    |
    */
    'video_width' => (int) env('LIVE_VIDEO_WIDTH', 1920),
    'video_height' => (int) env('LIVE_VIDEO_HEIGHT', 1080),
    'video_fps' => (int) env('LIVE_VIDEO_FPS', 30),

    // ~4.5 Mbit/s is the practical ceiling for 1080p30 over a hall's wifi.
    'max_bitrate' => (int) env('LIVE_MAX_BITRATE', 4_500_000),

];
