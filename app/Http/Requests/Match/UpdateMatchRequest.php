<?php

namespace App\Http\Requests\Match;

use App\Models\FootballMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('match'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $club = $this->route('club');

        return [
            'title' => ['required', 'string', 'min:2', 'max:100'],
            'scheduled_at' => ['required', 'date'],
            'field_id' => [
                'integer',
                Rule::exists('fields', 'id')->where(function ($query) use ($club) {
                    $query->whereIn('venue_id', $club->venues()->pluck('id'));
                }),
            ],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:300'],
            'arrival_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'max_players' => ['required', 'integer', 'min:2', 'max:50'],
            'max_substitutes' => ['required', 'integer', 'min:0', 'max:50'],
            'registration_opens_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'notes' => ['nullable', 'string', 'max:500'],
            'team_a_name' => ['nullable', 'string', 'max:50', Rule::notIn($this->reservedTeamNames())],
            'team_b_name' => ['nullable', 'string', 'max:50', Rule::notIn($this->reservedTeamNames())],
            'team_a_color' => ['nullable', 'string', Rule::in(FootballMatch::JERSEY_COLORS)],
            'team_b_color' => ['nullable', 'string', Rule::in(FootballMatch::JERSEY_COLORS)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'El titulo es obligatorio.',
            'title.min' => 'El titulo debe tener al menos :min caracteres.',
            'title.max' => 'El titulo no puede tener mas de :max caracteres.',
            'scheduled_at.required' => 'La fecha y hora son obligatorias.',
            'scheduled_at.date' => 'La fecha y hora no son validas.',
            'field_id.exists' => 'La cancha seleccionada no pertenece a este club.',
            'field_id.integer' => 'La cancha seleccionada no es valida.',
            'duration_minutes.required' => 'La duracion es obligatoria.',
            'duration_minutes.min' => 'La duracion minima es :min minutos.',
            'duration_minutes.max' => 'La duracion maxima es :max minutos.',
            'arrival_minutes.required' => 'El tiempo de llegada es obligatorio.',
            'arrival_minutes.min' => 'El tiempo de llegada minimo es :min minutos.',
            'arrival_minutes.max' => 'El tiempo de llegada maximo es :max minutos.',
            'max_players.required' => 'El numero de jugadores es obligatorio.',
            'max_players.min' => 'Minimo :min jugadores.',
            'max_players.max' => 'Maximo :max jugadores.',
            'max_substitutes.required' => 'El numero de suplentes es obligatorio.',
            'max_substitutes.min' => 'Minimo :min suplentes.',
            'max_substitutes.max' => 'Maximo :max suplentes.',
            'registration_opens_hours.required' => 'Las horas de apertura de registro son obligatorias.',
            'registration_opens_hours.min' => 'Minimo :min hora.',
            'registration_opens_hours.max' => 'Maximo :max horas.',
            'notes.max' => 'Las notas no pueden tener mas de :max caracteres.',
            'team_a_name.not_in' => 'El nombre del equipo no puede ser una palabra reservada (Titular, Suplente, etc.).',
            'team_b_name.not_in' => 'El nombre del equipo no puede ser una palabra reservada (Titular, Suplente, etc.).',
        ];
    }

    /** @return string[] */
    protected function reservedTeamNames(): array
    {
        return [
            'Titular', 'Titulares', 'Suplente', 'Suplentes',
            'Starter', 'Starters', 'Substitute', 'Substitutes',
        ];
    }
}
