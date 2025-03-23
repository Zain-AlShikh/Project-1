<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use SomarKesen\TelegramGateway\Facades\TelegramGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
class MessageController extends Controller
{
    /**
     * التحقق من الرمز المدخل
     */
    public function verifyCode(Request $request)
    {
        // التحقق من صحة البيانات المدخلة
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|min:4|max:4',  // التحقق من الرمز فقط
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // البحث عن رقم الهاتف المرتبط بهذا الرمز
        $phone = Cache::get('verification_code_' . $request->code);

        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Verification code has expired or is invalid.',
            ], 400);
        }

        // حذف الرمز بعد استخدامه
        Cache::forget('verification_code_' . $request->code);

        // جلب المستخدم من قاعدة البيانات عبر رقم الهاتف
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // تحديث حالة التحقق للمستخدم
        $user->update(['is_verified' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Verification successful.',
            // 'user' => $user->makeHidden(['created_at', 'updated_at', 'password']),
        ], 200);
    }


}


