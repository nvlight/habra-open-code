<?php

namespace App\Http\Requests;

use App\Enums\Difficulty;
use App\Enums\PublicationLabel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublicationRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'lead' => ['nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'string'],
            'cover' => ['nullable', 'string', 'max:2048'],
            'difficulty' => ['nullable', Rule::enum(Difficulty::class)],
            'label' => ['nullable', Rule::enum(PublicationLabel::class)],
            'is_translation' => ['boolean'],
            'source_url' => ['nullable', 'string', 'url', 'max:2048'],
            'original_author' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'hubs' => ['array', 'max:5'],
            'hubs.*' => ['integer', 'exists:hubs,id'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
        ];
    }
}
