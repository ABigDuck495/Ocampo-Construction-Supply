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

        if (! Auth::attempt(['Email' => $validated['email'], 'password' => $validated['password']])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Invalid credentials.'], 401);
            }

            return back()->withErrors([
                'email' => 'Invalid credentials.',
            ])->onlyInput('email');
        }

        $user = Auth::user();
        $user->markLoggedIn();

        $request->session()->regenerate();

        if ($request->expectsJson()) {
            // NEW: issue a Sanctum token so the Flutter app can authenticate
            // future requests without relying on session cookies.
            $token = $user->createToken('flutter-app')->plainTextToken;

            return response()->json([
                'message' => 'Logged in successfully.',
                'user' => $user,
                'token' => $token, // NEW
            ]);
        }

        return redirect()->intended(route('deliveries.index'));
    }

    public function logout(Request $request){
        // NEW: revoke the current Sanctum token if this request was
        // authenticated with one (i.e. came from the Flutter app).
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request){
        return response()->json($request->user());
    }
}