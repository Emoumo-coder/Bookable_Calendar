<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
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
            'booking_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'clients' => 'required|array|min:1',
            'clients.*.first_name' => 'required|string|max:100',
            'clients.*.last_name' => 'required|string|max:100',
            'clients.*.email' => 'required|email|max:255',
        ];
    }

    /**
     * Custom error messages for validation.
     */
    public function messages(): array
    {
        return [
            'clients.required' => 'At least one client is required',
            'clients.*.first_name.required' => 'Each client must have a first name',
            'clients.*.last_name.required' => 'Each client must have a last name',
            'clients.*.email.required' => 'Each client must have an email',
            'clients.*.email.email' => 'Each client must have a valid email',
        ];
    }

}
