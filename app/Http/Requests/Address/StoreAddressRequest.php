<?php

namespace App\Http\Requests\Address;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'company' => 'nullable|string|max:255',

            'document_type' => 'nullable|string|in:CC,NIT,RUC,RFC',
            'document_number' => 'nullable|required_with:document_type|string|max:30',
            'fiscal_name' => 'nullable|string|max:255',

            'street_address' => 'required|string|max:255',
            'apartment' => 'nullable|string|max:50',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',

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
