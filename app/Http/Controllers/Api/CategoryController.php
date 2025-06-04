<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Book;
use Illuminate\Http\Request;
use App\Http\Responses\Response;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    // إضافة قسم جديد
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:categories,name',
        ]);

        if ($validator->fails()) {
            return Response::Validation($validator->errors(), 'Validation error');
        }

        $category = Category::create([
            'name' => $request->name,
        ]);

        return Response::Success($category, 'Category created successfully', 201);
    }

    // عرض جميع الأقسام
    public function index(Request $request)
    {
        $showAll = $request->query('show_all');

        if ($showAll === 'true') {
            $categories = Category::select('id', 'name')->get();
        } else {
            $categories = Category::select('id', 'name')->limit(10)->get();
        }

        return Response::Success($categories, 'Categories retrieved successfully');
    }


    // عرض جميع الكتب داخل قسم معيّن
    public function booksByCategory($categoryId)
    {
        $category = Category::with('books')->find($categoryId);

        if (!$category) {
            return Response::Error(null, 'Category not found', 404);
        }

        $books = $category->books->map(function ($book) {
            return [
                'id'        => $book->id,
                'title'     => $book->title,
                'cover_url' => $book->cover_url,
            ];
        });

        return Response::Success([
            'category' => [
                'id'   => $category->id,
                'name' => $category->name,
            ],
            'books' => $books
        ], 'Books by category retrieved successfully');
    }


    // ( بحث عن كتاب حسب  ( اسم المؤلف , اسم الكتاب , اسم دار النشر   .
    public function searchInCategory(Request $request, $categoryId)
    {
        $query = strtolower($request->input('query'));

        if (!$query) {
            return Response::Error(null, 'Search query is required', 422);
        }

        // التحقق من وجود القسم
        $category = Category::find($categoryId);
        if (!$category) {
            return Response::Error(null, 'Category not found', 404);
        }


        $books = Book::where('category_id', $categoryId)
            ->where(function ($q) use ($query) {
                $q->whereRaw('LOWER(title) LIKE ?', ["%$query%"])
                    ->orWhereRaw('LOWER(publisher) LIKE ?', ["%$query%"])
                    ->orWhereHas('author', function ($authorQuery) use ($query) {
                        $authorQuery->whereRaw('LOWER(name) LIKE ?', ["%$query%"]);
                    });
            })
            ->get();

        if ($books->isEmpty()) {
            return Response::Error(null, 'No books found in this category matching your search', 404);
        }

        // رد مختصر فقط id, title, cover_url
        $results = $books->map(function ($book) {
            return [
                'id'        => $book->id,
                'title'     => $book->title,
                'cover_url' => $book->cover_url,
            ];
        });

        return Response::Success($results, 'Search results retrieved successfully');
    }


    // البحث عن قسم حسب الاسم
    public function searchCategoryByName(Request $request)
    {
        $name = strtolower($request->input('name'));

        if (!$name) {
            return Response::Error(null, 'Category name is required', 422);
        }

        $category = Category::whereRaw('LOWER(name) LIKE ?', ["%$name%"])->first();

        if (!$category) {
            return Response::Error(null, 'Category not found', 404);
        }

        return Response::Success([
            'id'   => $category->id,
            'name' => $category->name,
        ], 'Category found successfully');
    }
}
