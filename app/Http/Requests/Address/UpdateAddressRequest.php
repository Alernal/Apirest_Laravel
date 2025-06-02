<?php

namespace App\Http\Requests\Address;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'company' => 'nullable|string|max:255',

            'document_type' => 'nullable|string|in:CC,NIT,RUC,RFC',
            'document_number' => 'nullable|required_with:document_type|string|max:30',
            'fiscal_name' => 'nullable|string|max:255',

            'street_address' => 'sometimes|required|string|max:255',
            'apartment' => 'nullable|string|max:50',
            'city' => 'sometimes|required|string|max:100',
            'state' => 'sometimes|required|string|max:100',
            'postal_code' => 'sometimes|required|string|max:20',
            'country' => 'sometimes|required|string|max:100',

            'is_default' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $type = $this->input('document_type');
            $fiscal = $this->input('fiscal_name');

            if ($type === 'NIT' && empty($fiscal)) {
                $validator->errors()->add('fiscal_name', 'El campo razón social es obligatorio cuando el tipo de documento es NIT.');
            }
        });
    }
}
