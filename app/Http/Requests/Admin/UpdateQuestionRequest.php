<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'question_text' => ['required', 'string'],
            'options' => ['required', 'array'],
            'options.a' => ['required', 'string'],
            'options.b' => ['required', 'string'],
            'options.c' => ['nullable', 'string'],
            'options.d' => ['nullable', 'string'],
            'correct_option' => ['required', 'string', 'in:a,b,c,d,A,B,C,D'],
            'base_point' => ['required', 'integer', 'min:1'],
            'time_limit_sec' => ['required', 'integer', 'min:5'],
        ];
    }
}