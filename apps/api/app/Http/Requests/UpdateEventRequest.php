<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
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
        $event = $this->route('event');

        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('events', 'slug')->ignore($event->id)],
            'description_md' => ['sometimes', 'string', 'max:10000'],
            'category' => ['sometimes', 'in:music,arts,sports,tech,other'],
            'venue_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'venue_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'location' => ['sometimes', 'nullable', 'array'],
            'location.lat' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_with:location', 'numeric', 'between:-180,180'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date', 'after:start_at'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'is_online' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Event title must not exceed 200 characters',
            'description_md.max' => 'Event description must not exceed 10,000 characters',
            'end_at.after' => 'Event end time must be after start time',
            'location.lat.between' => 'Latitude must be between -90 and 90',
            'location.lng.between' => 'Longitude must be between -180 and 180',
        ];
    }
}
