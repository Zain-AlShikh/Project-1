<?php

namespace App\Http\Controllers\ApiAdmin;

use App\Http\Controllers\Controller;



use App\Models\Category;
use Illuminate\Support\Facades\DB;
use App\Http\Responses\Response;
use App\Models\Book;

class StatisticsController extends Controller
{

    public function ratingsByCategory()
{
    $categories = Category::select('id', 'name')->get();
    $maxBooksForFullBar = 50; // العدد المطلوب لامتلاء المدرج
    $maxRatingPerBook = 5;    // أعلى تقييم ممكن للكتاب
    $maxTotalRating = $maxBooksForFullBar * $maxRatingPerBook;

    $result = $categories->map(function ($category) use ($maxBooksForFullBar, $maxRatingPerBook, $maxTotalRating) {
        $bookIds = Book::where('category_id', $category->id)->pluck('id');
        $booksCount = $bookIds->count();

        $ratings = DB::table('book_user_ratings')
            ->whereIn('book_id', $bookIds)
            ->pluck('rating');

        $ratingsSum = $ratings->sum();
        $average = $booksCount > 0 ? round($ratingsSum / $booksCount, 2) : 0;
        $ratingPercent = $maxTotalRating > 0 ? round(($ratingsSum / $maxTotalRating) * 100, 2) : 0;

        // شرح التقييم:
        $explanation = '';
        if ($booksCount < $maxBooksForFullBar) {
            $explanation = "تم تقييم $booksCount كتابًا فقط. لتحقيق 100% يلزم وجود $maxBooksForFullBar كتابًا على الأقل بتقييم 5 نجوم.";
        } elseif ($average < 5) {
            $explanation = "متوسط تقييم الكتب هو $average من 5. لتحقيق 100% يجب أن يكون متوسط التقييم 5.";
        } else {
            $explanation = "تم تحقيق أقصى تقييم ممكن.";
        }

        return [
            'category' => $category->name,
            'average_rating' => $average,
            'rating_percent' => min($ratingPercent, 100),
            'rating_count' => $ratings->count(),
            'books_count' => $booksCount,
            'explanation' => $explanation,
        ];
    });

    return Response::Success($result, 'Category ratings retrieved successfully');
}

}
