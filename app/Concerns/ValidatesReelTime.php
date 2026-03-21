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

            $durationSeconds = $match?->videoUpload?->duration_seconds;

            if (! $durationSeconds) {
                return;
            }

            $requestedSeconds = ((int) $this->input('minute')) * 60 + (int) $this->input('second');

            if ($requestedSeconds > $durationSeconds) {
                $validator->errors()->add(
                    'minute',
                    sprintf('El tiempo no puede exceder la duración del video (%d:%02d).', intdiv($durationSeconds, 60), $durationSeconds % 60),
                );
            }
        });
    }
}
