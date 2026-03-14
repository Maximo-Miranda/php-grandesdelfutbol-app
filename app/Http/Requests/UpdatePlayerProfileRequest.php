<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdatePlayerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'nickname' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'preferred_position' => ['nullable', 'string', 'max:50'],
            'photo' => [
                'nullable',
                File::image()
                    ->max(10 * 1024)
                    ->dimensions(
                        Rule::dimensions()
                            ->minWidth(200)
                            ->minHeight(200)
                            ->maxWidth(6000)
                            ->maxHeight(6000)
                    ),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photo.image' => 'El archivo debe ser una imagen (JPG, PNG o WebP).',
            'photo.max' => 'La foto no debe superar 10 MB.',
            'photo.dimensions' => 'La foto debe tener al menos 200x200 px y maximo 6000x6000 px.',
            'photo.mimes' => 'La foto debe ser JPG, PNG o WebP.',
            'photo.mimetypes' => 'La foto debe ser JPG, PNG o WebP.',
        ];
    }
}
