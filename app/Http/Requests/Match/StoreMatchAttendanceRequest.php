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

        if (now()->gte($match->effectiveRegistrationOpensAt())) {
            return;
        }

        $validator->after(function (Validator $validator) use ($match): void {
            $opensAt = $match->effectiveRegistrationOpensAt()->format('d/m/Y H:i');
            $validator->errors()->add(
                'status',
                "La convocatoria aún no está abierta. Abre el {$opensAt}.",
            );
        });
    }
}
