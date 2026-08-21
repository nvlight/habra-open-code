<?php

namespace App\Http\Requests;

use App\Enums\Difficulty;
use App\Enums\PublicationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:5000'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'location' => ['nullable', 'string', 'max:255'],
            'feed_settings' => ['nullable', 'array'],
            'feed_settings.types' => ['array'],
            'feed_settings.types.*' => [Rule::enum(PublicationType::class)],
            'feed_settings.min_rating' => ['nullable', 'integer', 'between:0,100000'],
            'feed_settings.difficulties' => ['array'],
            'feed_settings.difficulties.*' => [Rule::enum(Difficulty::class)],
        ];
    }
}
