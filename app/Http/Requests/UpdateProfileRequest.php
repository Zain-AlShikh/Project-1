<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'     => 'sometimes|string|max:255',
            'last_name'      => 'sometimes|string|max:255',
            'phone'          => 'sometimes|string|unique:users,phone,' . Auth::user()->id,
            'email'          => 'sometimes|email|unique:users,email,' . Auth::user()->id,
            'location'       => 'nullable|string|max:255',
            'profile_image'  => 'nullable|image|mimes:jpeg,png,jpg,gif',
        ];
    }
}
