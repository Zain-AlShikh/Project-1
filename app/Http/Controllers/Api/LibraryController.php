<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Book;
use Illuminate\Support\Facades\Auth;
use App\Http\Responses\Response;

class LibraryController extends Controller
{
    /**
     * Add a book to the user's library.
     */
    public function addToLibrary(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
        ]);

        $user = Auth::user();
        $bookId = $request->input('book_id');

        if ($user->libraryBooks()->where('book_id', $bookId)->exists()) {
            return Response::Error(null, 'Book is already in your library', 409);
        }

        $user->libraryBooks()->attach($bookId);

        $book = Book::select('id', 'title', 'cover_url')->find($bookId);

        return Response::Success($book, 'Book added to your library successfully');
    }

    /**
     * Remove a book from the user's library.
     */
    public function removeFromLibrary(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
        ]);

        $user = Auth::user();
        $bookId = $request->input('book_id');

        if (!$user->libraryBooks()->where('book_id', $bookId)->exists()) {
            return Response::Error(null, 'Book is not in your library', 404);
        }

        $user->libraryBooks()->detach($bookId);

        return Response::Success(null, 'Book removed from your library successfully');
    }

    /**
     * Get all books in the user's library.
     */
    public function getLibrary()
    {
        $user = Auth::user();

        $libraryBooks = $user->libraryBooks()
            ->select('books.id', 'books.title', 'books.cover_url')
            ->get();

        return Response::Success($libraryBooks, 'Your library books retrieved successfully');
    }


    public function getPdfLink(Request $request)
{
    $request->validate([
        'book_id' => 'required|exists:books,id',
    ]);

    $user = Auth::user();
    $bookId = $request->book_id;

    $inLibrary = $user->libraryBooks()->where('book_id', $bookId)->exists();

    if (!$inLibrary) {
        return Response::Error(null, 'This book is not in your library', 403);
    }

    $book = Book::select('id', 'title', 'pdf_url')->find($bookId);

    if (!$book || !$book->pdf_url) {
        return Response::Error(null, 'PDF not available for this book', 404);
    }

    return Response::Success([
        // 'title' => $book->title,
        'pdf_url' => $book->pdf_url
    ], 'PDF link retrieved successfully');
}

}
