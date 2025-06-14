<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Middleware\AdminMiddleware;
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
    | Profile Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'show']);      // عرض البروفايل
    Route::post('/profile/update', [ProfileController::class, 'update']);  // تعديل البروفايل




    /*
    |--------------------------------------------------------------------------
    | Book Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/books/latest', [BookController::class, 'latest']); // تابع لعرض الكتب المضافة حديثاً , آخر 20 تمت اضافتهن
    Route::get('/books/{id}', [BookController::class, 'show']); // عرض التفاصيل الخاصة بكل كتاب
    Route::get('/books/fetch/{identifier}/{categoryId}', [BookController::class, 'fetchAndStoreByIdentifier']); // تابع لأدخال الكتب  عن طريق رقمه حسب القسم الخاص به
    Route::post('/books/{book}/rate', [BookController::class, 'rate']); // تقييم كتاب معين
    Route::post('/books/search', [BookController::class, 'search']);




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

    /*
    |--------------------------------------------------------------------------
    | Authors Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('authors')->group(function () {
        Route::get('/all-athors', [AuthorController::class, 'index']); // عرض جميع المؤلفين
        Route::get('/{id}/books', [AuthorController::class, 'booksByAuthor']); // كتب مؤلف معين
        Route::get('/search', [AuthorController::class, 'searchAuthorByName']); // تابع للبحث عن المؤلف معين عن طريق الأسم
        Route::get('/{authorId}/books/search', [AuthorController::class, 'searchBooksByAuthor']); // تابع البحث عن كتاب معين عن طريق اسم الكتاب او الناشر
    });
    /*
    |--------------------------------------------------------------------------
    | Category Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('favorites')->group(function () {
        Route::post('/add', [FavoriteController::class, 'addToFavorites']); // إضافة كتاب إلى المفضلة
        Route::delete('/remove', [FavoriteController::class, 'removeFromFavorites']); // إزالة كتاب من المفضلة
        Route::get('/all_favorites', [FavoriteController::class, 'getFavorites']); // عرض كل الكتب في المفضلة
    });


    /*
    |--------------------------------------------------------------------------
    | Library Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('library')->group(function () {
        Route::post('/add', [LibraryController::class, 'addToLibrary']);          // إضافة كتاب إلى مكتبتي
        Route::delete('/remove', [LibraryController::class, 'removeFromLibrary']); // إزالة كتاب من مكتبتي
        Route::get('/all', [LibraryController::class, 'getLibrary']);             // عرض كل الكتب في مكتبتي
        Route::get('/pdf', [LibraryController::class, 'getPdfLink']); //PDF الكتاب
    });
});




//  الروات الخاص بالآدمن
Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->group(function () {
 // إضافة قسم جديد
    Route::post('/categories/add-category', [CategoryController::class, 'store']);

    // إضافة مؤلف جديد
    Route::post('/authors', [AuthorController::class, 'store']);

    // إدخال الكتب عن طريق الرقم حسب القسم
    Route::get('/books/fetch/{identifier}/{categoryId}', [BookController::class, 'fetchAndStoreByIdentifier']);


});
