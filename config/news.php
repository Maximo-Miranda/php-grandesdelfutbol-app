<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'model' => env('NEWS_AI_MODEL', 'gemini-2.5-flash-lite'),
        'daily_limit' => env('NEWS_AI_DAILY_LIMIT', 1200),
        'per_minute_limit' => env('NEWS_AI_PER_MINUTE_LIMIT', 200),
        'min_content_length' => env('NEWS_AI_MIN_CONTENT_LENGTH', 1500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scorebat API
    |--------------------------------------------------------------------------
    */

    'scorebat' => [
        'api_url' => env('SCOREBAT_API_URL', 'https://www.scorebat.com/video-api/v3/'),
        'api_token' => env('SCOREBAT_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feed Defaults
    |--------------------------------------------------------------------------
    */

    'feed' => [
        'per_page' => 15,
        'ad_frequency' => 5,
        'max_article_age_days' => 30,
        'public_feed_days' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Story Clustering
    |--------------------------------------------------------------------------
    */

    'clustering' => [
        'time_window_hours' => 6,
        'min_title_similarity' => 0.7,
    ],

];
