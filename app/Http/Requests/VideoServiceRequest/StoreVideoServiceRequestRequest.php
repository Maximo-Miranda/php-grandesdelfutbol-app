<?php

namespace App\Http\Requests\VideoServiceRequest;

use Illuminate\Foundation\Http\FormRequest;

class StoreVideoServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $isGuest = ! $this->user();

        return [
            'name' => [$isGuest ? 'required' : 'nullable', 'string', 'max:255'],
            'email' => [$isGuest ? 'required' : 'nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'club_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['required', 'string', 'max:500'],
            'preferred_date' => ['required', 'date', 'after:today'],
            'preferred_time' => ['required', 'string', 'max:10'],
            'message' => ['nullable', 'string', 'max:1000'],
            'selected_plan' => ['required', 'string', 'in:recocha,profesional,mensual'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'Ingresa un email válido.',
            'phone.required' => 'El teléfono o WhatsApp es obligatorio.',
            'venue_address.required' => 'La dirección de la cancha es obligatoria.',
            'preferred_date.required' => 'La fecha es obligatoria.',
            'preferred_date.after' => 'La fecha debe ser posterior a hoy.',
            'preferred_time.required' => 'La hora es obligatoria.',
            'selected_plan.required' => 'Selecciona un tipo de servicio.',
            'selected_plan.in' => 'Selecciona un tipo de servicio válido.',
        ];
    }
}
