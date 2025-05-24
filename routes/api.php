<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\AuthorController;
/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// التحقق من كود التفعيل
Route::post('/verify', [MessageController::class, 'verifyCode']);

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// استعادة كلمة المرور
Route::post('user/password/email', [AuthController::class, 'userForgetPassword']);
Route::post('user/password/code/check', [AuthController::class, 'userCheckCode']);
Route::post('user/password/reset', [AuthController::class, 'userResetPassword']);

// إعادة إرسال OTP
Route::post('user/password/resend', [AuthController::class, 'resendOtpPassword']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Requires Authentication)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authenticated User Actions
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtpPhone']);

    /*
    |--------------------------------------------------------------------------
    | Book Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::get('/fetch-book/{isbn}/{categoryId}', [BookController::class, 'fetchAndStore']);

    /*
    |--------------------------------------------------------------------------
    | Category Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('categories')->group(function () {
        Route::post('/add-category', [CategoryController::class, 'store']);                 // إضافة قسم جديد
        Route::get('/all-categories', [CategoryController::class, 'index']);                // عرض جميع الأقسام
        Route::get('/category-books/{id}', [CategoryController::class, 'booksByCategory']); // عرض الكتب في قسم معيّن
        Route::get('/search', [CategoryController::class, 'searchCategoryByName']); // تابع للبحث عن القسم حسب الأسم
        Route::get('/{id}/search', [CategoryController::class, 'searchInCategory']);       //  تابع البحث داخل القسم حسب (الكتاب، المؤلف، الناشر)

    });


    Route::prefix('authors')->group(function () {
        Route::get('/all-athors', [AuthorController::class, 'index']); // جميع المؤلفين
        Route::get('/{id}/books', [AuthorController::class, 'booksByAuthor']); // كتب مؤلف معين
        Route::get('/search', [AuthorController::class, 'searchAuthorByName']); // تابع للبحث عن المؤلف معين عن طريق الأسم
        Route::get('/{authorId}/books/search', [AuthorController::class, 'searchBooksByAuthor']); // تابع البحث عن كتاب معين عن طريق اسم الكتاب او الناشر
    });

    // البحث عن الكتب
    Route::get('/books/search', [BookController::class, 'search']);
});
