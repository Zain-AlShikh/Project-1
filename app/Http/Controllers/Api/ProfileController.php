<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Http\Responses\Response;
use App\Models\User;

class ProfileController extends Controller
{
    // عرض البروفايل
    public function show()
    {
        $user = Auth::user();

        $data = [
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'phone'         => $user->phone,
            'email'         => $user->email,
            'location'      => $user->location,
            'profile_image' => $user->profile_image ? asset('storage/' . $user->profile_image) : null,
        ];

        return Response::Success($data, 'Profile retrieved successfully');
    }

    // تعديل البروفايل
    public function update(UpdateProfileRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->first_name = $request->input('first_name', $user->first_name);
        $user->last_name  = $request->input('last_name', $user->last_name);
        $user->phone      = $request->input('phone', $user->phone);
        $user->email      = $request->input('email', $user->email);
        $user->location   = $request->input('location', $user->location);

        // التحقق من وجود صورة جديدة
        if ($request->hasFile('profile_image')) {
            // حذف الصورة القديمة إن وجدت
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            // حفظ الصورة الجديدة
            $path = $request->file('profile_image')->store('profile_images', 'public');
            $user->profile_image = $path;
        }

        $user->save();

        return Response::Success(null, 'Profile updated successfully');
    }
}
