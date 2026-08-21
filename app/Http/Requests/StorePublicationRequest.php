<?php

namespace App\Http\Requests;

use App\Enums\Difficulty;
use App\Enums\PublicationLabel;
use App\Enums\PublicationStatus;
use App\Enums\PublicationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicationRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'lead' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'cover' => ['nullable', 'string', 'max:2048'],
            'type' => ['required', Rule::enum(PublicationType::class)],
            'status' => ['nullable', Rule::enum(PublicationStatus::class), Rule::in([PublicationStatus::Draft, PublicationStatus::Sandbox])],
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
