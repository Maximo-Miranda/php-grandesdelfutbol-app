<?php

namespace App\Http\Requests\Team;

use App\Models\Team;
use App\Services\SeasonService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Team::class, $this->route('club')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'coach_player_id' => ['nullable', 'integer', 'exists:players,id'],
            'captain_player_id' => ['nullable', 'integer', 'exists:players,id'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $name = $this->input('name');
            if (! is_string($name) || $name === '') {
                return;
            }

            $club = $this->route('club');
            $seasonId = $this->input('season_id') ?? app(SeasonService::class)->activeFor($club)->id;

            $exists = Team::query()
                ->where('season_id', $seasonId)
                ->where('normalized_name', Team::normalize($name))
                ->exists();

            if ($exists) {
                $v->errors()->add('name', 'Ya existe un equipo con ese nombre en esta temporada.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede tener más de :max caracteres.',
            'color.required' => 'Selecciona un color para el equipo.',
            'color.regex' => 'El color debe estar en formato hex (ej: #dc2626).',
            'bio.max' => 'La descripción no puede tener más de :max caracteres.',
            'logo.image' => 'El escudo debe ser una imagen válida.',
            'logo.mimes' => 'El escudo debe ser JPG, PNG o WebP.',
            'logo.max' => 'El escudo no puede pesar más de 5 MB.',
            'cover.image' => 'La portada debe ser una imagen válida.',
            'cover.mimes' => 'La portada debe ser JPG, PNG o WebP.',
            'cover.max' => 'La portada no puede pesar más de 10 MB.',
            'coach_player_id.exists' => 'El director técnico seleccionado no es válido.',
            'captain_player_id.exists' => 'El capitán seleccionado no es válido.',
        ];
    }
}
