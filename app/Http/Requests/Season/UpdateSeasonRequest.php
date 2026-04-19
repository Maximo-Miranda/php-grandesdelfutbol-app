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
            'matches_count' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $count = (int) $this->input('matches_count');

            if ($count % 2 === 0) {
                $v->errors()->add('matches_count', 'El número de partidos debe ser impar.');
            }

            /** @var Season $season */
            $season = $this->route('season');
            $played = $season->completedMatchesCount();
            if ($count < $played) {
                $v->errors()->add('matches_count', "No puede ser menor a los partidos ya jugados ({$played}).");
            }
        });
    }
}
