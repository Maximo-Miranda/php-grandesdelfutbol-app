<?php

namespace App\Validation;

use App\Models\FootballMatch;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

class ValidateCancelHoursBeforeRegistration
{
    public function __invoke(Validator $validator): void
    {
        $data = $validator->getData();

        $scheduledAt = $data['scheduled_at'] ?? null;
        if (! $scheduledAt) {
            return;
        }

        $rawCancelHours = $data['cancel_hours_before'] ?? null;
        $cancelHours = $rawCancelHours !== null
            ? (int) $rawCancelHours
            : FootballMatch::DEFAULT_CANCEL_HOURS_BEFORE;
        $scheduledAt = CarbonImmutable::parse($scheduledAt);
        $cancelAt = $scheduledAt->subHours($cancelHours);

        $opensAt = isset($data['registration_opens_at'])
            ? CarbonImmutable::parse($data['registration_opens_at'])
            : $scheduledAt->subHours((int) ($data['registration_opens_hours'] ?? 24));

        if ($cancelAt->lt($opensAt)) {
            $validator->errors()->add(
                'cancel_hours_before',
                'Las horas para cancelar no pueden ser mayores que el tiempo de apertura de convocatoria.',
            );
        }
    }
}
