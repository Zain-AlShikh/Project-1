<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Author;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Responses\Response;
use App\Jobs\SendNewBookEmail;
use App\Models\User;
use  App\Models\BookUserRating;
use Illuminate\Support\Facades\Auth;

class BookController extends Controller
{
    /**
     * Display all books with minimal info.
     */
    public function index()
    {
        $books = Book::select('id', 'title', 'cover_url')->get();

        return Response::Success($books, 'Books fetched successfully');
    }




    /**
     * Get latest added books (title and cover only), supports limit or all.
     */
    public function latest(Request $request)
    {
        $showAll = $request->query('show_all');

        $query = Book::select('id', 'title', 'cover_url')
            ->orderBy('created_at', 'desc');

        if ($showAll === 'true') {
            $books = $query->limit(value: 20)->get();
        } else {
            $books = $query->limit(value: 5)->get();
        }

        return Response::Success($books, 'Latest books fetched successfully');
    }


    /**
     * Get top-rated books with average rating greater than 4 .
     */
    public function topRated(Request $request)
    {
        $showAll = $request->query('show_all');

        $query = Book::withAvg('ratings', 'rating')
            ->having('ratings_avg_rating', '>', 3)
            ->orderByDesc('ratings_avg_rating');

        // تحديد عدد النتائج
        if ($showAll !== 'true') {
            $query->limit(5);
        }

        $books = $query->get();

        // تنسيق النتائج
        $formattedBooks = $books->map(function ($book) {
            return [
                'id' => $book->id,
                'title' => $book->title,
                'cover_url' => $book->cover_url,
                'average_rating' => round($book->ratings_avg_rating, 1),
            ];
        });

        return Response::Success($formattedBooks, 'Top rated books retrieved successfully');
    }





    /**
     * Search for top-rated books only (avg rating > 4)
     */
    public function searchTopRated(Request $request)
    {
        $queryText = $request->input('query');

        if (!$queryText) {
            return Response::Error(null, 'Search query is required', 422);
        }

        $books = Book::withAvg('ratings', 'rating')
            ->having('ratings_avg_rating', '>=', 4)
            ->where(function ($q) use ($queryText) {
                $q->where('title', 'LIKE', "%{$queryText}%")
                    ->orWhere('publisher', 'LIKE', "%{$queryText}%")
                    ->orWhereHas('author', function ($authorQuery) use ($queryText) {
                        $authorQuery->where('name', 'LIKE', "%{$queryText}%");
                    });
            })
            ->orderByDesc('ratings_avg_rating')
            ->get();

        if ($books->isEmpty()) {
            return Response::Error(null, 'No top-rated books found matching your search', 404);
        }

        $results = $books->map(function ($book) {
            return [
                'id' => $book->id,
                'title' => $book->title,
                'cover_url' => $book->cover_url,
                'average_rating' => round($book->ratings_avg_rating, 1),
            ];
        });

        return Response::Success($results, 'Top-rated search results retrieved successfully');
    }






    /**
     * Show detailed info about a specific book.
     */
    public function show($id)
    {

        $book = Book::with(['author', 'category', 'ratings'])->find($id);

        if (!$book) {
            return Response::Error(null, 'Book not found', 404);
        }

        $user = Auth::user();
        $userRating = null;

        if ($user) {

            $userRating = $book->ratings()->where('user_id', $user->id)->value('rating');
        }


        $averageRating = round($book->ratings()->avg('rating'), 1);
        // $ratingsCount = $book->ratings()->count();


        return Response::Success([
            'id'             => $book->id,
            'title'          => $book->title,
            'author'         => $book->author?->name ?? 'Unknown Author',
            'category'       => $book->category?->name ?? 'Unknown Category',
            'isbn'           => $book->isbn,
            'publish_year'   => $book->publish_year,
            'pages_count'    => $book->pages_count,
            'publisher'      => $book->publisher,
            'cover_url'      => $book->cover_url,
            'description'    => $book->description,
            'subject'        => $book->subject,
            'pdf_url'        => $book->pdf_url,
            'language'       => $book->language,
            'average_rating' => $averageRating,
            'your_rating'    => $userRating,
        ], 'Book details retrieved successfully');
    }




    /**
     * Summary of rate Book >>
     */
    public function rate(Request $request, $bookId)
    {
        $request->validate([
            'rating' => 'required|numeric|min:1|max:5',
        ]);

        $user = Auth::user();
        $book = Book::findOrFail($bookId);

        $rating = BookUserRating::updateOrCreate(
            ['user_id' => $user->id, 'book_id' => $book->id],
            ['rating' => round($request->rating, 1)]
        );

        return Response::Success($rating, 'Book rated successfully');
    }




    /**
     * Summary of search book >>>
     *
     */

    public function search(Request $request)
    {
        $query = $request->input('query');

        if (!$query) {
            return Response::Error(null, 'Search query is required', 422);
        }

        $books = Book::where(function ($q) use ($query) {
            $q->where('title', 'LIKE', "%{$query}%")
                ->orWhere('publisher', 'LIKE', "%{$query}%")
                ->orWhereHas('author', function ($authorQuery) use ($query) {
                    $authorQuery->where('name', 'LIKE', "%{$query}%");
                });
        })
            ->get();

        if ($books->isEmpty()) {
            return Response::Error(null, 'No books found matching your search', 404);
        }

        $results = $books->map(function ($book) {
            return [
                'id' => $book->id,
                'title' => $book->title,
                'cover_url' => $book->cover_url,
            ];
        });

        return Response::Success($results, 'Search results retrieved successfully');
    }

    /**
     * Fetch a book from OpenLibrary by ISBN and store it under a specified category.
     */
    public function fetchAndStoreByIdentifier($identifier, $categoryId)
    {
        $category = Category::find($categoryId);
        if (!$category) {
            return Response::Error(null, 'Category not found', 404);
        }

        $book = null;
        $author = null;
        $pdfUrl = null;

        $tryEndpoints = [
            "isbn" => "https://openlibrary.org/isbn/{$identifier}.json",
            "olid" => "https://openlibrary.org/books/{$identifier}.json",
            "work" => "https://openlibrary.org/works/{$identifier}.json",
        ];

        foreach ($tryEndpoints as $type => $url) {
            $response = Http::get($url);
            if (!$response->ok()) {
                continue;
            }

            $data = $response->json();
            $title = $data['title'] ?? 'Untitled';

            // الوصف
            $descriptionRaw = is_array($data['description'] ?? null)
                ? ($data['description']['value'] ?? null)
                : ($data['description'] ?? null);
            $description = $descriptionRaw ?: 'There is no description available for this book.';

            // المؤلف
            $authorKey = null;
            if ($type === 'work' && !empty($data['authors'][0]['author']['key'])) {
                $authorKey = $data['authors'][0]['author']['key'];
            } elseif (!empty($data['authors'][0]['key'])) {
                $authorKey = $data['authors'][0]['key'];
            }

            if ($authorKey) {
                $authorResponse = Http::get("https://openlibrary.org{$authorKey}.json");
                if ($authorResponse->ok()) {
                    $authorName = $authorResponse->json()['name'] ?? null;
                    if ($authorName) {
                        $author = Author::firstOrCreate(['name' => $authorName]);
                    }
                }
            }

            if (!$author) {
                $author = Author::inRandomOrder()->first();

                if (!$author) {
                    $author = Author::create([
                        'name' => 'Unknown Author',
                    ]);
                }
            }

            $coverUrl = isset($data['covers'][0])
                ? "https://covers.openlibrary.org/b/id/{$data['covers'][0]}-L.jpg"
                : null;

            if (isset($data['ocaid'])) {
                $pdfUrl = "https://archive.org/download/{$data['ocaid']}/{$data['ocaid']}.pdf";
            }

            $pagesCount = null;
            if (isset($data['number_of_pages'])) {
                $pagesCount = $data['number_of_pages'];
            } elseif (isset($data['pagination'])) {
                preg_match('/\d+/', $data['pagination'], $matches);
                $pagesCount = $matches[0] ?? null;
            }
            if (is_null($pagesCount)) {
                $pagesCount = rand(100, 350);
            }

            $subjects = $data['subjects'] ?? ['No subjects available'];
            $subjectsJson = json_encode($subjects);

            // معالجة publish_year
            $publishDateRaw = $data['publish_date'] ?? $data['created']['value'] ?? null;
            if ($publishDateRaw) {
                $timestamp = strtotime($publishDateRaw);
                if ($timestamp !== false) {
                    $publishYear = (int) date('Y', $timestamp);
                } else {
                    $publishYear = 1999;
                }
            } else {
                $publishYear = 1999;
            }

            // اللغة
            $language = $data['languages'][0]['key'] ?? null;
            if (!$language) {
                $language = '\language\eng';
            }

            $book = Book::create([
                'title'        => $title,
                'author_id'    => $author->id,
                'category_id'  => $categoryId,
                'isbn'         => $data['isbn_10'][0] ?? $data['isbn_13'][0] ?? ($type === 'isbn' ? $identifier : null),
                'publish_year' => $publishYear,
                'pages_count'  => $pagesCount,
                'publisher'    => $data['publishers'][0] ?? 'Unknown Publisher',
                'cover_url'    => $coverUrl,
                'description'  => $description,
                'subject'      => $subjectsJson,
                'pdf_url'      => $pdfUrl,
                'language'     => $language,
            ]);

            break;
        }

        if (!$book) {
            return Response::Error(null, 'Book not found using provided identifier.', 404);
        }

        $bookData = [
            'title'        => $book->title,
            'author'       => $author->name ?? 'Unknown Author',
            'isbn'         => $book->isbn,
            'publish_year' => $book->publish_year,
            'pages_count'  => $book->pages_count,
            'description'  => $book->description,
            'language'     => $book->language,
        ];

        $emails = User::pluck('email');
        foreach ($emails as $email) {
            dispatch(new SendNewBookEmail($email, $bookData));
        }

        return Response::Success([
            'id'           => $book->id,
            'title'        => $book->title,
            'author'       => $author->name ?? 'Unknown Author',
            'category_id'  => $book->category_id,
            'isbn'         => $book->isbn,
            'publish_year' => $book->publish_year,
            'pages_count'  => $book->pages_count,
            'publisher'    => $book->publisher,
            'cover_url'    => $book->cover_url,
            'description'  => $book->description,
            'subject'      => $book->subject,
            'pdf_url'      => $book->pdf_url,
            'language'     => $book->language,
        ], 'Book fetched and saved successfully.', 201);
    }




    /**
     * Summary of recommendFromPreferences
     */
    public function recommendFromPreferences()
    {
        $user = Auth::user();
        if (!$user) {
            return Response::Error(null, 'Unauthenticated', 401);
        }
        $response = Http::timeout(10)->post('http://127.0.0.1:5001/recommend-from-user', [
            'user_id' => $user->id,
        ]);

        if (!$response->ok()) {
            return Response::Error(null, 'AI recommender failed', 500);
        }
        $recIds = $response->json('book_ids', []);
        if (empty($recIds)) {
            $books = Book::select('id', 'title', 'cover_url')
                ->whereIn('id', [10, 18, 24, 30, 40, 50, 70])
                ->get();
            return Response::Success($books, 'Default recommendations ');
        }
        $books = Book::select('id', 'title', 'cover_url')
            ->whereIn('id', $recIds)
            ->get();
        return Response::Success($books, 'Recommendations based on your preferences');
    }

    /**
     * Summary of getSimilarBooks
     */
    public function getSimilarBooks($bookId)
    {
        $response = Http::timeout(10)->post('http://127.0.0.1:5001/similar-books', [
            'id' => $bookId,
        ]);

        if (!$response->ok()) {
            return Response::Error(null, 'AI similar book request failed', 500);
        }

        $similarIds = $response->json('book_ids', []);
        if (empty($similarIds)) {
            return Response::Success([], 'No similar books found');
        }

        $ratedBooks = Book::withAvg('ratings', 'rating')
            ->whereIn('id', $similarIds)
            ->having('ratings_avg_rating', '>', 0)
            ->orderByDesc('ratings_avg_rating')
            ->get();


        $books = $ratedBooks;
        if ($books->count() < 5) {
            $existingIds = $books->pluck('id')->toArray();
            $remainingIds = array_diff($similarIds, $existingIds);

            $fallbackBooks = Book::whereIn('id', $remainingIds)
                ->get()
                ->map(function ($book) {
                    $book->ratings_avg_rating = 0;
                    return $book;
                });

            $books = $books->concat($fallbackBooks)->take(5);
        }


        $formattedBooks = $books->map(function ($book) {
            return [
                'id' => $book->id,
                'title' => $book->title,
                'cover_url' => $book->cover_url,
                'average_rating' => round($book->ratings_avg_rating ?? 0, 1),
            ];
        });

        return Response::Success($formattedBooks, 'Similar books retrieved successfully');
    }
}
