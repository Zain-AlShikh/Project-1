<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $fillable = [
        'title',
        'author_id',
        'category_id',
        'isbn',
        'publish_year',
        'pages_count',
        'publisher',
        'cover_url',
        'description',
        'subject',
        'pdf_url',
        'language',
    ];

    protected $casts = [
        'subject' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'book_user_favorites');
    }

    public function inLibrariesOf()
    {
        return $this->belongsToMany(User::class, 'book_user_library');
    }

    public function ratings()
    {
        return $this->belongsToMany(User::class, 'book_user_ratings')
            ->withPivot('rating')
            ->withTimestamps();
    }



}
