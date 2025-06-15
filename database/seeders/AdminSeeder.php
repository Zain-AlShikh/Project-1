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
        'first_name' => 'Ahmad',
        'last_name' => 'Asaad',
        'phone' => '966512345678',
        'email' => 'admin@example.com',
        'password' => Hash::make('12345678'),
        'location' => 'Dmascus',
        'profile_image' => null,
        'is_verified' => true,
        'role' => 'admin',
    ];

    User::create($user);
}

       
}
