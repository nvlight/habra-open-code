<?php

namespace App\Http\Requests;

use App\Enums\Difficulty;
use App\Enums\PublicationLabel;
use App\Enums\PublicationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(PublicationType::class)],
            'hub' => ['nullable', 'string', 'exists:hubs,alias'],
            'company' => ['nullable', 'string', 'exists:companies,slug'],
            'author' => ['nullable', 'string', 'exists:users,login'],
            'difficulty' => ['nullable', Rule::enum(Difficulty::class)],
            'label' => ['nullable', Rule::enum(PublicationLabel::class)],
            'min_rating' => ['nullable', 'integer', 'between:-1000,100000'],
            'sort' => ['nullable', Rule::in(['new', 'best'])],
            'status' => ['nullable', Rule::in(['published', 'sandbox'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only([
            'type', 'hub', 'company', 'author', 'difficulty', 'label', 'min_rating',
        ]);
    }
}
