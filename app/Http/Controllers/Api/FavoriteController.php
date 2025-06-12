<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Book;
use Illuminate\Support\Facades\Auth;
use App\Http\Responses\Response;


class FavoriteController extends Controller
{
    /**
     * Add a book to the user's favorites.
     */
    public function addToFavorites(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
        ]);

        $user = Auth::user();
        $bookId = $request->input('book_id');

        if ($user->favoritedBooks()->where('book_id', $bookId)->exists()) {
            return Response::Error(null, 'Book is already in your favorites', 409);
        }

        $user->favoritedBooks()->attach($bookId);

        $book = Book::select('id', 'title', 'cover_url')->find($bookId);

        return Response::Success($book, 'Book added to favorites successfully');
    }

    /**
     * Remove a book from the user's favorites.
     */
    public function removeFromFavorites(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
        ]);

        $user = Auth::user();
        $bookId = $request->input('book_id');

        if (!$user->favoritedBooks()->where('book_id', $bookId)->exists()) {
            return Response::Error(null, 'Book is not in your favorites', 404);
        }

        $user->favoritedBooks()->detach($bookId);

        return Response::Success(null, 'Book removed from favorites successfully');
    }

    /**
     * Get all favorite books of the user.
     */
    public function getFavorites()
    {
        $user = Auth::user();

        $favorites = $user->favoritedBooks()
            ->select('books.id', 'books.title', 'books.cover_url')
            ->get();

        return Response::Success($favorites, 'Favorite books retrieved successfully');
    }
}
