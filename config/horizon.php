<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds (seconds)
    |--------------------------------------------------------------------------
    |
    | Fires LongWaitDetected event when a job waits longer than this.
    |
    */

    'waits' => [
        'redis:default' => 60,
        'redis:notifications' => 60,
        'redis:video-processing' => 600,
        'redis:reels' => 300,
        'redis:news-fetching' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times (minutes)
    |--------------------------------------------------------------------------
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'silenced_tags' => [],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Snapshots every 5 min = 24 snapshots = 2 hours of history.
    | Increase for more history at the cost of Redis memory.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Horizon Master Memory Limit (MB)
    |--------------------------------------------------------------------------
    */

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Server: 12 GB RAM / 6 vCPU / 100 GB NVMe
    |
    | Timeout chain: job timeout < supervisor timeout < retry_after (3900s)
    |
    | supervisor-default:  General jobs + notifications (auto-scales 2-4)
    | supervisor-video:    FFmpeg encoding + YouTube upload (fixed 1 proc)
    | supervisor-reels:    Reel clip generation (auto-scales 1-3)
    |
    */

    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default', 'notifications'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],

        'supervisor-video' => [
            'connection' => 'redis',
            'queue' => ['video-processing'],
            'balance' => false,
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 2,
            'timeout' => 3600,
            'nice' => 0,
        ],

        'supervisor-reels' => [
            'connection' => 'redis',
            'queue' => ['reels'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'size',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'maxTime' => 3600,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 2,
            'timeout' => 900,
            'nice' => 0,
        ],

        'supervisor-news' => [
            'connection' => 'redis',
            'queue' => ['news-fetching'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'size',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'maxTime' => 3600,
            'maxJobs' => 500,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'minProcesses' => 2,
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],

            'supervisor-video' => [
                'maxProcesses' => 1,
            ],

            'supervisor-reels' => [
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],

            'supervisor-news' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-default' => [
                'maxProcesses' => 2,
            ],

            'supervisor-video' => [
                'maxProcesses' => 1,
            ],

            'supervisor-reels' => [
                'maxProcesses' => 1,
            ],

            'supervisor-news' => [
                'maxProcesses' => 1,
            ],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        '.env',
    ],
];
