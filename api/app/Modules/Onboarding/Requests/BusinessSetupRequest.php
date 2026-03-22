<?php

namespace App\Modules\Onboarding\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', 'max:100'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'google_reviews_url' => ['nullable', 'url', 'max:500'],
            'tone' => ['nullable', 'in:professional,friendly,casual'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'website_url.url' => 'Please enter a valid website URL including https://',
            'google_reviews_url.url' => 'Please enter a valid Google Reviews URL.',
            'tone.in' => 'Tone must be one of: professional, friendly, or casual.',
        ];
    }
}
