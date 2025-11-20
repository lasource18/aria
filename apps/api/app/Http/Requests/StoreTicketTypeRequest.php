<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:free,paid,donation'],
            'price_xof' => ['required_if:type,paid', 'numeric', 'min:0', 'max:10000000'],
            'fee_pass_through_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_qty' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_order_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sales_start' => ['nullable', 'date'],
            'sales_end' => ['nullable', 'date', 'after:sales_start'],
            'refundable' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ticket type name is required',
            'name.max' => 'Ticket type name must not exceed 100 characters',
            'type.required' => 'Ticket type is required',
            'type.in' => 'Ticket type must be one of: free, paid, donation',
            'price_xof.required_if' => 'Price is required for paid tickets',
            'price_xof.min' => 'Price must be at least 0 XOF',
            'price_xof.max' => 'Price must not exceed 10,000,000 XOF',
            'max_qty.min' => 'Maximum quantity must be at least 1',
            'max_qty.max' => 'Maximum quantity must not exceed 100,000',
            'per_order_limit.min' => 'Per-order limit must be at least 1',
            'sales_end.after' => 'Sales end time must be after sales start time',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set price to 0 for free tickets
        if ($this->type === 'free') {
            $this->merge(['price_xof' => 0]);
        }
    }
}
