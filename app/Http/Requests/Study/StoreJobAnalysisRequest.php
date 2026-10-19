<?php

namespace App\Http\Requests\Study;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobAnalysisRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:40', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => 'El título puede tener hasta 120 caracteres.',
            'body.required' => 'Pega el texto de la oferta.',
            'body.min' => 'El texto es demasiado corto para analizarlo. Pega la oferta completa.',
            'body.max' => 'El texto es demasiado largo. Pega solo la descripción de la oferta (hasta 20.000 caracteres).',
        ];
    }
}
