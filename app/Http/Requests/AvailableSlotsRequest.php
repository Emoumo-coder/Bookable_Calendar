<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvailableSlotsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_slug' => 'required|exists:services,slug',
            'date' => 'required|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'service_slug.required' => 'Service slug is required',
            'service_slug.exists' => 'Service not found',
            'date.required' => 'Date is required',
            'date.date_format' => 'Date must be in YYYY-MM-DD format',
        ];
    }
}
