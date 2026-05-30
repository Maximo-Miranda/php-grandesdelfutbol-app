<?php

namespace App\Http\Requests\Match;

use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreMatchAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $match = $this->route('match');
        $ability = $match->status === MatchStatus::Completed ? 'update' : 'register';

        return Gate::allows($ability, $match);
    }

    public function prepareForValidation(): void
    {
        /** @var FootballMatch $match */
        $match = $this->route('match');

        // En convocatoria general (sin equipos con nómina) los miembros no eligen color:
        // todos van al pool y el admin sortea después. Los admins conservan la opción
        // de fijar equipo manualmente (ej. para registros post-partido).
        if ($match->isOpenCall() && ! $match->club->isAdminOrOwner($this->user())) {
            $this->merge(['team' => null]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'player_id' => ['required', 'exists:players,id'],
            'status' => ['required', 'string', 'in:confirmed,declined'],
            'team' => ['nullable', 'string', 'in:a,b'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        /** @var FootballMatch $match */
        $match = $this->route('match');

        if ($match->club->isAdminOrOwner($this->user())) {
            return;
        }

        if ($match->status !== MatchStatus::Upcoming) {
            return;
        }

        $now = now();

        if ($now->lt($match->effectiveRegistrationOpensAt())) {
            $validator->after(function (Validator $validator) use ($match): void {
                $opensAt = $match->effectiveRegistrationOpensAt()->format('d/m/Y H:i');
                $validator->errors()->add(
                    'status',
                    "La convocatoria aún no está abierta. Abre el {$opensAt}.",
                );
            });

            return;
        }

        if ($now->gte($match->effectiveRegistrationClosesAt())) {
            $validator->after(function (Validator $validator) use ($match): void {
                $closesAt = $match->effectiveRegistrationClosesAt()->format('d/m/Y H:i');
                $validator->errors()->add(
                    'status',
                    "La convocatoria ya cerró ({$closesAt}).",
                );
            });
        }
    }
}
