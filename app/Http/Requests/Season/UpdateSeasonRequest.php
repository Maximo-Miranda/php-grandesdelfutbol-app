<?php

namespace App\Http\Requests\Season;

use App\Models\Season;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('season'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'matches_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->validateMatchesCount($v));
    }

    private function validateMatchesCount(Validator $validator): void
    {
        if (! $this->has('matches_count')) {
            return;
        }

        /** @var Season $season */
        $season = $this->route('season');

        if (! $season->isActive()) {
            $validator->errors()->add('matches_count', 'Solo puedes cambiar el número de partidos de una temporada activa.');

            return;
        }

        $count = (int) $this->input('matches_count');

        if ($count % 2 === 0) {
            $validator->errors()->add('matches_count', 'El número de partidos debe ser impar.');
        }

        $played = $season->completedMatchesCount();

        if ($count < $played) {
            $validator->errors()->add('matches_count', "No puede ser menor a los partidos ya jugados ({$played}).");
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede tener más de :max caracteres.',
            'matches_count.required' => 'El número de partidos es obligatorio.',
            'matches_count.integer' => 'El número de partidos debe ser un número entero.',
            'matches_count.min' => 'El mínimo es :min partido.',
            'matches_count.max' => 'El máximo es :max partidos.',
        ];
    }
}
