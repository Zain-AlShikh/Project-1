<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^[0-9]{10}$/|unique:users,phone',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'location' => 'required|string|max:255',
            'profile_image_url' => 'nullable|image',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'The first name is required.',
            'last_name.required' => 'The last name is required.',
            'phone.required' => 'The phone number is required.',
            'phone.regex' => 'The phone number must be 10 digits.',
            'phone.unique' => 'The phone number is already registered.',
            'email.required' => 'The email address is required.',
            'email.email' => 'The email address must be a valid email format.',
            'email.unique' => 'The email address is already registered.',
            'email.max' => 'The email address must not exceed 255 characters.', 
            'password.required' => 'The password is required.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'location.required' => 'The location is required.',
            'profile_image_url.image' => 'The file must be an image.',
        ];
    }
}
