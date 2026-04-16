<?php

namespace App\Http\Requests\Match;

use App\Concerns\MatchRequestDefaults;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchRequest extends FormRequest
{
    use MatchRequestDefaults;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('match'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return $this->matchRules();
    }
}
