<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'La orden debe incluir al menos un producto.',
            'items.array' => 'El formato de los productos de la orden no es válido.',
            'items.min' => 'La orden debe incluir al menos un producto.',
            'items.*.product_id.required' => 'Cada renglón debe indicar el producto.',
            'items.*.product_id.exists' => 'Uno de los productos indicados no existe.',
            'items.*.quantity.required' => 'Cada renglón debe indicar la cantidad.',
            'items.*.quantity.min' => 'La cantidad debe ser al menos 1.',
        ];
    }
}
