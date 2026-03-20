<?php

namespace App\Concerns;

use App\Models\FootballMatch;
use Illuminate\Validation\Validator;

trait ValidatesReelTime
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var FootballMatch|null $match */
            $match = $this->route('match');

            if (! $match) {
                return;
            }

            $maxSeconds = $match->video_duration_seconds ?? $match->duration_minutes * 60;
            $requestedSeconds = ((int) $this->input('minute')) * 60 + (int) $this->input('second');

            if ($requestedSeconds > $maxSeconds) {
                $validator->errors()->add(
                    'minute',
                    sprintf('El tiempo no puede exceder la duración del video (%d:%02d).', intdiv($maxSeconds, 60), $maxSeconds % 60),
                );
            }
        });
    }
}
