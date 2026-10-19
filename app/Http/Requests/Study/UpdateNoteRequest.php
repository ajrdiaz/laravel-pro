<?php

namespace App\Http\Requests\Study;

use App\Models\Note;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Missing or empty means "no note".
            'body' => ['nullable', 'string', 'max:'.Note::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.max' => 'La nota es demasiado larga (hasta 20.000 caracteres).',
        ];
    }
}
