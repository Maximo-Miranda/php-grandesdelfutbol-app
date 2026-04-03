<?php

namespace App\Rules;

use App\Models\Club;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueClubName implements ValidationRule
{
    public function __construct(private ?int $ignoreId = null) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Club::whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
            ->when($this->ignoreId, fn ($q) => $q->where('id', '!=', $this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('Ya existe un club con este nombre.');
        }
    }
}
