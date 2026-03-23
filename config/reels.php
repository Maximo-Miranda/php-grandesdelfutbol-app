<?php

return [
    'watermark_enabled' => env('REEL_WATERMARK_ENABLED', true),
    'watermark_path' => env('REEL_WATERMARK_PATH', storage_path('app/reel-assets/watermark.png')),
    'watermark_opacity' => (float) env('REEL_WATERMARK_OPACITY', 0.9),
    'watermark_padding' => (int) env('REEL_WATERMARK_PADDING', 20),
];
