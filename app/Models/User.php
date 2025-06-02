<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\MessageSent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
// use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'location',
        'profile_image',
        'is_verified',
        'role'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

public function getProfileImageUrlAttribute()
{
    return $this->profile_image ? asset('storage/' . $this->profile_image) : null;
}


    public function favoriteBooks()
    {
        return $this->belongsToMany(Book::class, 'book_user_favorites');
    }

    public function libraryBooks()
    {
        return $this->belongsToMany(Book::class, 'book_user_library');
    }

    public function ratedBooks()
    {
        return $this->belongsToMany(Book::class, 'book_user_ratings')
            ->withPivot('rating')
            ->withTimestamps();
    }
}
