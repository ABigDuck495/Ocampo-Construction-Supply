<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Flutter API Login
        |--------------------------------------------------------------------------
        |
        | Flutter uses Username + Password.
        | Username does NOT need to be an email address.
        |
        */

        if ($request->expectsJson()) {

            $validated = $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            $user = User::where('Username', $validated['username'])->first();

            if (
                !$user ||
                !Hash::check(
                    $validated['password'],
                    $user->getAuthPassword()
                )
            ) {
                return response()->json([
                    'message' => 'Invalid username or password.'
                ], 401);
            }

            // Update LastLoginAt
            $user->markLoggedIn();

            // Create Sanctum token
            $token = $user->createToken('flutter-app')->plainTextToken;

            return response()->json([
                'message' => 'Logged in successfully.',

                'user' => [
                    'UserID'      => $user->UserID,
                    'Name'        => $user->Name,
                    'Username'    => $user->Username,
                    'Email'       => $user->Email,
                    'Role'        => $user->Role,
                    'DriverID'    => $user->DriverID,
                    'Status'      => $user->Status,
                    'PhoneNumber' => $user->PhoneNumber,
                ],

                'token' => $token,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Laravel Web Login
        |--------------------------------------------------------------------------
        |
        | Web login uses Email + Password.
        | Email MUST be a valid email address.
        |
        */

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (
            !Auth::attempt([
                'Email'    => $validated['email'],
                'password' => $validated['password'],
            ])
        ) {
            return back()
                ->withErrors([
                    'email' => 'Invalid email or password.',
                ])
                ->onlyInput('email');
        }

        $user = Auth::user();

        // Update LastLoginAt
        $user->markLoggedIn();

        // Regenerate session
        $request->session()->regenerate();

        return redirect()->intended(
            route('deliveries.index')
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        /*
        | Revoke Sanctum token if this is a Flutter request.
        */
        if (
            $request->user() &&
            $request->user()->currentAccessToken()
        ) {
            $request->user()
                ->currentAccessToken()
                ->delete();
        }

        /*
        | Logout web session.
        */
        if ($request->hasSession()) {

            Auth::logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out successfully.'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Current User
    |--------------------------------------------------------------------------
    */

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'UserID'      => $user->UserID,
                'Name'        => $user->Name,
                'Username'    => $user->Username,
                'Email'       => $user->Email,
                'Role'        => $user->Role,
                'DriverID'    => $user->DriverID,
                'Status'      => $user->Status,
                'PhoneNumber' => $user->PhoneNumber,
            ]
        ]);
    }
}