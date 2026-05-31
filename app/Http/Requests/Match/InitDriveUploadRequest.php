<?php

namespace App\Http\Requests\Match;

use Illuminate\Foundation\Http\FormRequest;

class InitDriveUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('match'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:500'],
            'filesize' => ['required', 'integer', 'min:1'],
            'content_type' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'filename.required' => 'El nombre del archivo es obligatorio.',
            'filename.max' => 'El nombre del archivo no puede tener mas de :max caracteres.',
            'filesize.required' => 'El tamano del archivo es obligatorio.',
            'filesize.integer' => 'El tamano del archivo debe ser un numero entero.',
            'content_type.required' => 'El tipo de contenido es obligatorio.',
        ];
    }
}
