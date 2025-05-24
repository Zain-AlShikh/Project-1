<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Http\Responses\Response;
use App\Models\Book;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    /**
     * Get all authors
     */
   public function index(Request $request)
{
    $showAll = $request->query('show_all');

    if ($showAll === 'true') {
        $authors = Author::select('id', 'name')->get();
    } else {
        $authors = Author::select('id', 'name')->limit(10)->get();
    }

    return Response::Success($authors, 'All authors retrieved successfully');
}


    /**
     * Get all books
     */
    public function booksByAuthor($authorId)
    {
        $author = Author::find($authorId);

        if (!$author) {
            return Response::Error(null, 'Author not found', 404);
        }

        $books = $author->books()->select('id', 'title', 'cover_url')->get();

        return Response::Success([
            'author' => [
                'id'   => $author->id,
                'name' => $author->name,
            ],
            'books' => $books,
        ], 'Books by author retrieved successfully');
    }

    public function searchBooksByAuthor(Request $request, $authorId)
    {
        $query = strtolower($request->input('query'));

        if (!$query) {
            return Response::Error(null, 'Search query is required', 422);
        }

        $author = Author::find($authorId);
        if (!$author) {
            return Response::Error(null, 'Author not found', 404);
        }

        $books = Book::where('author_id', $authorId)
            ->where(function ($q) use ($query) {
                $q->whereRaw('LOWER(title) LIKE ?', ["%$query%"])
                    ->orWhereRaw('LOWER(publisher) LIKE ?', ["%$query%"]);
            })
            ->get();

        if ($books->isEmpty()) {
            return Response::Error(null, 'No books found for this author matching your search', 404);
        }

        $results = $books->map(function ($book) {
            return [
                'id'        => $book->id,
                'title'     => $book->title,
                'cover_url' => $book->cover_url,
            ];
        });

        return Response::Success($results, 'Search results retrieved successfully');
    }


    // البحث عن مؤلف حسب الاسم
    public function searchAuthorByName(Request $request)
    {
        $name = strtolower($request->input('name'));

        if (!$name) {
            return Response::Error(null, 'Author name is required', 422);
        }

        $author = Author::whereRaw('LOWER(name) LIKE ?', ["%$name%"])->first();

        if (!$author) {
            return Response::Error(null, 'Author not found', 404);
        }

        return Response::Success([
            'id'   => $author->id,
            'name' => $author->name,
        ], 'Author found successfully');
    }
}
