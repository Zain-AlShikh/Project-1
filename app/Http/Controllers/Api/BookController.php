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
            $books = $query->get();
        } else {
            $books = $query->limit(value: 5)->get();
        }

        return Response::Success($books, 'Latest books fetched successfully');
    }





    /**
     * Show detailed info about a specific book.
     */
    public function show($id)
    {
        $book = Book::with(['author', 'category'])->find($id);

        if (!$book) {
            return Response::Error(null, 'Book not found', 404);
        }

        return Response::Success([
            'id'           => $book->id,
            'title'        => $book->title,
            'author'       => $book->author?->name ?? 'Unknown Author',
            'category'     => $book->category?->name ?? 'Unknown Category',
            'isbn'         => $book->isbn,
            'publish_year' => $book->publish_year,
            'pages_count'  => $book->pages_count,
            'publisher'    => $book->publisher,
            'cover_url'    => $book->cover_url,
            'description'  => $book->description,
            'subject'      => $book->subject,
            'pdf_url'      => $book->pdf_url,
            'language'     => $book->language,
            'created_at'   => $book->created_at,
        ], 'Book details retrieved successfully');
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
}
