<?php

return [

    /*
    |--------------------------------------------------------------------------
    | YouTube OAuth Credentials
    |--------------------------------------------------------------------------
    |
    | OAuth2 credentials from Google Cloud Console for the YouTube Data API v3.
    | Service accounts do NOT work for uploads — OAuth2 with refresh token is required.
    |
    */

    'client_id' => env('YOUTUBE_CLIENT_ID', ''),

    'client_secret' => env('YOUTUBE_CLIENT_SECRET', ''),

    'redirect_uri' => env('YOUTUBE_REDIRECT_URI', ''),

    /*
    |--------------------------------------------------------------------------
    | Upload Defaults
    |--------------------------------------------------------------------------
    |
    | Default privacy status and category for uploaded videos.
    | Category 17 = Sports in the YouTube API.
    |
    */

    'default_privacy' => env('YOUTUBE_DEFAULT_PRIVACY', 'public'),

    'category_id' => env('YOUTUBE_CATEGORY_ID', '17'),

];
