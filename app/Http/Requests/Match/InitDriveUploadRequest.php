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
            'filesize' => ['required', 'integer', 'max:'.config('youtube.drive.max_file_bytes')],
            'content_type' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $maxGb = round((int) config('youtube.drive.max_file_bytes') / (1024 ** 3), 0);

        return [
            'filename.required' => 'El nombre del archivo es obligatorio.',
            'filename.max' => 'El nombre del archivo no puede tener mas de :max caracteres.',
            'filesize.required' => 'El tamano del archivo es obligatorio.',
            'filesize.integer' => 'El tamano del archivo debe ser un numero entero.',
            'filesize.max' => "El tamano del archivo no puede exceder {$maxGb} GB.",
            'content_type.required' => 'El tipo de contenido es obligatorio.',
        ];
    }
}
