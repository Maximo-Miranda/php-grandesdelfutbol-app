<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bunny Stream API Key
    |--------------------------------------------------------------------------
    |
    | The main account API key from Bunny.net dashboard.
    |
    */

    'api_key' => env('BUNNY_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Stream Library
    |--------------------------------------------------------------------------
    |
    | The video library ID and its API key from Bunny Stream.
    |
    */

    'stream_library_id' => env('BUNNY_STREAM_LIBRARY_ID', ''),

    'stream_api_key' => env('BUNNY_STREAM_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | CDN Hostname
    |--------------------------------------------------------------------------
    |
    | The CDN hostname for streaming (e.g. vz-abc123.b-cdn.net).
    |
    */

    'cdn_hostname' => env('BUNNY_CDN_HOSTNAME', ''),

    /*
    |--------------------------------------------------------------------------
    | Download Speed Limit
    |--------------------------------------------------------------------------
    |
    | Maximum download speed in bytes/second for video downloads.
    | Default: 12 MB/s (~96 Mbps) to leave bandwidth for web traffic.
    | Set to 0 to disable throttling.
    |
    */

    'download_speed_limit' => (int) env('BUNNY_DOWNLOAD_SPEED_LIMIT', 12 * 1024 * 1024),

];
