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

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | YouTube Data API v3 allows ~10,000 quota units/day.
    | A video upload costs ~1,600 units → ~6 uploads/day.
    |
    */

    'daily_upload_limit' => (int) env('YOUTUBE_DAILY_UPLOAD_LIMIT', 6),

    /*
    |--------------------------------------------------------------------------
    | Video Sharing
    |--------------------------------------------------------------------------
    |
    | Duration (in hours) for temporary shareable video links.
    |
    */

    'video_share_hours' => (int) env('VIDEO_SHARE_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Upload Driver
    |--------------------------------------------------------------------------
    |
    | Controls how videos are uploaded from the browser.
    | "s3" uses the existing S3 multipart upload via Uppy.
    | "drive" uses Google Drive resumable uploads for better mobile reliability.
    |
    */

    'upload_driver' => env('UPLOAD_DRIVER', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Google Drive
    |--------------------------------------------------------------------------
    |
    | Google Drive is used as an intermediary for video uploads. Files are
    | uploaded directly from the browser to Drive, then downloaded by the
    | server for encoding and YouTube upload. Requires the drive.file scope
    | on the same OAuth token used for YouTube.
    |
    */

    'drive' => [
        'root_folder_name' => env('DRIVE_ROOT_FOLDER_NAME', 'Grandes del Futbol'),
        'chunk_size_mb' => (int) env('DRIVE_UPLOAD_CHUNK_SIZE_MB', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Retention
    |--------------------------------------------------------------------------
    |
    | Controls how long video files are kept in S3 before cleanup.
    | The 720p reels source is kept in S3 for fast reel generation,
    | then deleted (Drive retains the permanent copy).
    |
    */

    'storage' => [
        's3_reels_source_days' => (int) env('S3_REELS_SOURCE_DAYS', 30),
    ],

];
