<?php

namespace App\Utility\Request;

use Illuminate\Foundation\Http\FormRequest;

class ListingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'per_page' => ['required', 'integer', 'max_digits:3', 'min_digits:1'],
            'page' => ['required', 'integer'],
            'search' => ['nullable', 'string'],
        ];
    }
}
