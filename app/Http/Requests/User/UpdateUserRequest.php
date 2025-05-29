<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
            "first_name" => "sometimes|nullable|string|max:255",
            "last_name" => "sometimes|nullable|string|max:255",
            "email" => "sometimes|nullable|email|max:255|unique:users,email," . $this->user()->id,
            "phone" => "sometimes|nullable|string|max:20",
            "gender" => "sometimes|nullable|in:male,female,other",
            "profile_image_url" => "sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048",
            "is_verified" => "sometimes|boolean",
            "password" => "sometimes|nullable|string|min:8|confirmed",
        ];
    }
}
