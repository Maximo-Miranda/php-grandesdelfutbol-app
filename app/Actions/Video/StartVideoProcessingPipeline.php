<?php

namespace App\Actions\Video;

use App\Jobs\ProcessUploadedVideo;
use App\Models\MatchVideoUpload;

class StartVideoProcessingPipeline
{
    public function __construct(private DispatchYouTubeUpload $dispatchYouTubeUpload) {}

    /**
     * Kick off the video pipeline: transfer the original to S3 and publish it
     * to YouTube in parallel (separate queues so neither blocks the other).
     */
    public function __invoke(MatchVideoUpload $videoUpload): void
    {
        ProcessUploadedVideo::dispatch($videoUpload);

        ($this->dispatchYouTubeUpload)($videoUpload);
    }
}
