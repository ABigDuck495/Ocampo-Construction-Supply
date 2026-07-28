<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm(){
        return view('auth.login');
    }
    public function login(Request $request){
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('Email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->Password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        Auth::login($user);

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => $user,
        ]);
    }

    public function logout(Request $request){
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request){
        return response()->json($request->user());
    }
}
