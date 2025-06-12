<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Mail\SendCodeResetPassword;
use App\Models\ResetCodePassword;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Responses\Response;
use Illuminate\Support\Facades\Cache;
use SomarKesen\TelegramGateway\Facades\TelegramGateway;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        try {
            $data = $request->only(['first_name', 'last_name', 'phone', 'password', 'email', 'location']);
            $data['password'] = Hash::make($request->password);



            if ($request->hasFile('profile_image')) {
                $data['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
            }

            $user = User::create($data);
            $token = $user->createToken('Your_App_Name')->plainTextToken;

            // إرسال رسالة التحقق
            $this->sendVerificationMessage($request->phone);

            return Response::Success([
                'user' => $user->makeHidden(['created_at', 'updated_at']),
                'token' => $token
            ], 'User registered and verification message sent successfully');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * إرسال رسالة التحقق عبر Telegram Gateway
     */
    public function sendVerificationMessage($phone)
    {

        $code = rand(1000, 9999);

        try {
            // خريطة الأرقام مع التوكينات المخصصة
            $tokens = [
                '0983801332' => 'AAGvGgAALrSI75ySLNIiS3RWt-LQUVuHYqhfoC2_prZ0Rg',
                '0934169837' => 'AAEXEgAAT5jKD4E2BDMUaKkW_WWkORYsL1ozFGP8Qq8K0g',
                '0959710488' => 'AAHmGwAA3opMo5Lz3ahUyLooJkn1PX5qHNFBtqqscF7Z2g',
                '0984972040' => 'AAHnGwAA3YrshqRdP1yQ5TMkLXaJlxwzetni7iFCRNw6Kw',
            ];

            // اختيار التوكن المناسب
            $token = $tokens[$phone] ?? env('TELEGRAM_API_TOKEN');

            // إرسال رمز التحقق عبر Telegram
            app(\SomarKesen\TelegramGateway\Services\TelegramGatewayService::class)
                ->sendVerificationMessage($phone, [
                    'code' => $code,
                    'ttl' => 600,
                ], $token);

            // تخزين الرمز مع الهاتف في الكاش
            Cache::put('verification_code_' . $code, $phone, now()->addMinutes(10));

            return response()->json([
                'message' => 'Verification code sent successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send verification message.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function resendOtpPhone(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return Response::Error([], 'User not authenticated.');
        }


        return $this->sendVerificationMessage($user->phone);
    }






    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->only(['identifier', 'password']);

            $user = User::where('email', $credentials['identifier'])
                ->orWhere('phone', $credentials['identifier'])
                ->first();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return Response::Validation(['identifier' => ['Invalid credentials. Please check your email/phone and password.']]);
            }

            if (!$user->is_verified) {
                return Response::Error([], 'Your account is not verified. Please verify your phone number first.');
            }

            $token = $user->createToken('Your_App_Name')->plainTextToken;

            return Response::Success([
                'user' => $user->makeHidden(['created_at', 'updated_at']),
                'token' => $token
            ], 'User logged in successfully');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }




    public function userForgetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:users',
        ]);

        ResetCodePassword::query()->where('email', $request['email'])->delete();
        $data['code'] = mt_rand(1000, 9999);

        $codeData = ResetCodePassword::query()->create($data);

        Mail::to($request['email'])->send(new SendCodeResetPassword($codeData['code']));
        return response()->json(['message' => 'code sent successfully']);
    }



    public function resendOtpPassword(Request $request)
    {
        // البحث عن آخر رمز مسجل لهذا المستخدم
        $passwordReset = ResetCodePassword::query()->latest()->first();

        if (!$passwordReset) {
            return response()->json(['message' => 'No OTP request found for this user'], 404);
        }

        // حذف الرمز القديم
        ResetCodePassword::query()->where('email', $passwordReset->email)->delete();

        // إنشاء رمز جديد
        $newCode = mt_rand(1000, 9999);
        $newPasswordReset = ResetCodePassword::query()->create([
            'email' => $passwordReset->email,
            'code' => $newCode
        ]);

        // إرسال البريد الإلكتروني مجددًا
        Mail::to($passwordReset->email)->send(new SendCodeResetPassword($newCode));

        return response()->json(['message' => 'OTP has been resent successfully']);
    }




    public function userCheckCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|exists:reset_code_passwords',
        ]);

        $passwordRest = ResetCodePassword::query()->firstWhere('code', $request['code']);

        if ($passwordRest['created_at'] > now()->addHour()) {
            $passwordRest->delete();
            return  response()->json(['message' => trans('password.code_is_expire')], 422);
        }

        return response()->json([
            'code' => $passwordRest['code'],
            'message' => 'password code is valid '
        ], 200);
    }


    public function userResetPassword(Request $request)
    {
        $input = $request->validate([
            'code' => 'required|string|exists:reset_code_passwords',
            'password' => ['required', 'confirmed',]
        ]);

        $passwordRest = ResetCodePassword::query()->firstWhere('code', $request['code']);


        if ($passwordRest['created_at'] > now()->addHour()) {
            $passwordRest->delete();
            return  response()->json(['message' => 'password code is expire'], 422);
        }


        $user = User::query()->firstWhere('email', $passwordRest['email']);

        $input['password'] = bcrypt($input['password']);
        $user->update([
            'password' => $input['password'],
        ]);

        $passwordRest->delete();
        return response()->json([
            'message' => 'password has been successfully reset'
        ]);
    }

    public function logout(Request $request)
    {
        try {

            $request->user()->currentAccessToken()->delete();

            return Response::Success([], 'User logged out successfully');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }
}
