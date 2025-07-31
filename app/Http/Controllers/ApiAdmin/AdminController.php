<?php

// namespace App\Http\Controllers;

namespace App\Http\Controllers\ApiAdmin;

use App\Http\Controllers\Controller;


use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);
        if (!Auth::attempt($request->only('email','password')))
        return response()->json([
            'message' => 'invalid email or password'
        ], 401);

        $user = User::where('email', $request->email)->FirstOrFail();
        $token = $user->createToken('auth_Token')->plainTextToken;
        return response()->json([
            'message' => 'Login successful',
            // 'User' => $user,
            'token' => $token
        ], 200);
    }











/**
     * Display a listing of the resource.
         */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $c)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $c)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $c)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $c)
    {
        //
    }
}
