<?php

namespace App\Console\Commands;

use App\Enums\VideoUploadStatus;
use App\Models\MatchVideoUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecoverStuckVideoUploads extends Command
{
    protected $signature = 'videos:recover-stuck
                            {--minutes=30 : How many minutes without updates before considered stuck}';

    protected $description = 'Mark MatchVideoUpload records stuck in Uploading/Encoding as Failed, so admins can retry.';

    public function handle(): int
    {
        $thresholdMinutes = (int) $this->option('minutes');
        $threshold = now()->subMinutes($thresholdMinutes);

        $stuck = MatchVideoUpload::query()
            ->whereIn('status', [VideoUploadStatus::Uploading, VideoUploadStatus::Encoding])
            ->where('updated_at', '<', $threshold)
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No hay subidas estancadas.');

            return self::SUCCESS;
        }

        foreach ($stuck as $upload) {
            Log::warning('video.upload.recovered_stuck', [
                'upload_id' => $upload->id,
                'match_id' => $upload->football_match_id,
                'previous_status' => $upload->status->value,
                'stuck_since' => $upload->updated_at?->toIso8601String(),
            ]);
        }

        MatchVideoUpload::query()
            ->whereIn('id', $stuck->pluck('id'))
            ->update([
                'status' => VideoUploadStatus::Failed,
                'error_message' => "Procesamiento excedió {$thresholdMinutes} min sin actividad. Reintentá subiendo el video de nuevo.",
            ]);

        $this->info("Marcadas como Failed: {$stuck->count()} subidas estancadas.");

        return self::SUCCESS;
    }
}
