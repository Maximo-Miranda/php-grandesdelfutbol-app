<?php

namespace App\Http\Requests\Match;

use App\Concerns\MatchRequestDefaults;
use App\Models\FootballMatch;
use Illuminate\Foundation\Http\FormRequest;

class StoreMatchRequest extends FormRequest
{
    use MatchRequestDefaults;

    public function authorize(): bool
    {
        return $this->user()->can('create', [FootballMatch::class, $this->route('club')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return $this->matchRules(fieldNullable: true);
    }
}
