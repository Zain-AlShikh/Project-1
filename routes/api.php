<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MessageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/verify', [MessageController::class, 'verifyCode']);

//Auth....
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

//reset password
Route::post('user/password/email' , [AuthController::class  , 'userForgetPassword']) ;
Route::post('user/password/code/check' , [AuthController::class  , 'userCheckCode']) ;
Route::post('user/password/reset' , [AuthController::class  , 'userResetPassword']) ;
  // resend
Route::post('user/password/resend', [AuthController::class, 'resendOtpPassword']);



Route::group(['middleware' => ['auth:sanctum']], function () {

    Route::post('logout', [AuthController::class, 'logout']);
    // resend
    Route::post('/resend-otp', [AuthController::class, 'resendOtpPhone']) ;

});
