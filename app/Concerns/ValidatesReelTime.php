<?php

namespace App\Concerns;

use App\Models\FootballMatch;
use Illuminate\Validation\Validator;

trait ValidatesReelTime
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var FootballMatch|null $match */
            $match = $this->route('match');

            if (! $match?->video_duration_seconds) {
                return;
            }

            $requestedSeconds = ((int) $this->input('minute')) * 60 + (int) $this->input('second');

            if ($requestedSeconds > $match->video_duration_seconds) {
                $validator->errors()->add(
                    'minute',
                    sprintf('El tiempo no puede exceder la duración del video (%d:%02d).', intdiv($match->video_duration_seconds, 60), $match->video_duration_seconds % 60),
                );
            }
        });
    }
}
