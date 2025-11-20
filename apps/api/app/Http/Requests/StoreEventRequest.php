<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:events,slug'],
            'description_md' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'in:music,arts,sports,tech,other'],
            'venue_name' => ['required_if:is_online,false', 'nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'array'],
            'location.lat' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_with:location', 'numeric', 'between:-180,180'],
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'is_online' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Event title is required',
            'title.max' => 'Event title must not exceed 200 characters',
            'description_md.required' => 'Event description is required',
            'description_md.max' => 'Event description must not exceed 10,000 characters',
            'venue_name.required_if' => 'Venue name is required for in-person events',
            'start_at.after' => 'Event start time must be in the future',
            'end_at.after' => 'Event end time must be after start time',
            'location.lat.between' => 'Latitude must be between -90 and 90',
            'location.lng.between' => 'Longitude must be between -180 and 180',
        ];
    }
}
