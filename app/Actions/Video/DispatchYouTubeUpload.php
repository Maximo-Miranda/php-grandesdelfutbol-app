<?php

namespace App\Actions\Video;

use App\Jobs\UploadMatchToYouTube;
use App\Jobs\WaitForYouTubeProcessing;
use App\Models\MatchVideoUpload;

class DispatchYouTubeUpload
{
    /**
     * Upload the video to YouTube and poll its processing status until ready.
     */
    public function __invoke(MatchVideoUpload $videoUpload): void
    {
        UploadMatchToYouTube::dispatch($videoUpload)
            ->chain([new WaitForYouTubeProcessing($videoUpload)]);
    }
}
