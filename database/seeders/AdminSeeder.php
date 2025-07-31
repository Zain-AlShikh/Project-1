<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    $user = [
        'first_name' => 'sami',
        'last_name' => 'Amad',
        'phone' => '6876876888',
        'email' => 'user23@example.com',
        'password' => Hash::make('1234567654'),
        'location' => 'Dmascus',
        // 'profile_image' => null,
        // 'profile_image' => 'profile_images/image_user.jpg',
        'profile_image' => 'profile_images/image_user.jpg', // نفس المسار

        'is_verified' => true,
        'role' => 'user',
    ];

    User::create($user);
}

       
}
