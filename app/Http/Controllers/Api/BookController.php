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
    public function fetchAndStore($isbn, $categoryId)
    {
        $category = Category::find($categoryId);
        if (!$category) {
            return Response::Error(null, 'Category not found', 404);
        }

        $url = "https://openlibrary.org/isbn/{$isbn}.json";
        $response = Http::get($url);

        if ($response->failed()) {
            return Response::Error(null, 'Book not found or OpenLibrary unreachable', 404);
        }

        $data = $response->json();

        // Fetch author name
        $author = null;
        if (!empty($data['authors'][0]['key'])) {
            $authorUrl = "https://openlibrary.org" . $data['authors'][0]['key'] . ".json";
            $authorResponse = Http::get($authorUrl);
            if ($authorResponse->ok()) {
                $authorName = $authorResponse->json()['name'] ?? null;
                if ($authorName) {
                    $author = Author::firstOrCreate(['name' => $authorName]);
                }
            }
        }

        $coverUrl = isset($data['covers'][0])
            ? "https://covers.openlibrary.org/b/id/{$data['covers'][0]}-L.jpg"
            : null;

        $pdfUrl = isset($data['ocaid'])
            ? "https://archive.org/download/{$data['ocaid']}/{$data['ocaid']}.pdf"
            : null;

        $description = is_array($data['description'] ?? null)
            ? ($data['description']['value'] ?? null)
            : ($data['description'] ?? null);

        $book = Book::create([
            'title'        => $data['title'] ?? 'Untitled',
            'author_id'    => $author?->id,
            'category_id'  => $categoryId,
            'isbn'         => $isbn,
            'publish_year' => isset($data['publish_date']) ? intval(substr($data['publish_date'], 0, 4)) : null,
            'pages_count'  => $data['number_of_pages'] ?? null,
            'publisher'    => $data['publishers'][0] ?? null,
            'cover_url'    => $coverUrl,
            'description'  => $description,
            'subject'      => $data['subjects'] ?? [],
            'pdf_url'      => $pdfUrl,
            'language'     => isset($data['languages'][0]['key']) ? basename($data['languages'][0]['key']) : null,
        ]);

        //  بيانات الإيميل
        $bookData = [
            'title'        => $book->title,
            'author'       => $author?->name ?? 'Unknown Author',
            'isbn'         => $book->isbn,
            'publish_year' => $book->publish_year,
            'pages_count'  => $book->pages_count,
            'description'  => $book->description,
            'language'     => $book->language,
        ];

        // إرسال الإيميل لجميع المستخدمين
        $emails = User::pluck('email');
        foreach ($emails as $email) {
            dispatch(new SendNewBookEmail($email, $bookData)); 
        }

        return Response::Success([
            'id'           => $book->id,
            'title'        => $book->title,
            'author'       => $author?->name ?? 'Unknown Author',
            'author_id'    => $book->author_id,
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
        ], 'Book fetched and saved successfully', 201);
    }
}
