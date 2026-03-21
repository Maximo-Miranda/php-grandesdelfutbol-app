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

];
