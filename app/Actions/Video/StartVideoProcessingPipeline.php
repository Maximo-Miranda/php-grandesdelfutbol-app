<?php

namespace App\Actions\Video;

use App\Jobs\ProcessUploadedVideo;
use App\Models\MatchVideoUpload;

class StartVideoProcessingPipeline
{
    /**
     * Kick off the video pipeline by transferring the original from Drive to S3.
     *
     * The YouTube upload is chained from ProcessUploadedVideo once the file is
     * on S3, so the original is downloaded from Drive only once (YouTube then
     * reads from S3 instead of pulling from Drive a second time).
     */
    public function __invoke(MatchVideoUpload $videoUpload): void
    {
        ProcessUploadedVideo::dispatch($videoUpload);
    }
}
