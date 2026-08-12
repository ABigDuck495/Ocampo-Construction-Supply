<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = User::all();

        if ($request->wantsJson()) {
            return response()->json(
                $users->map(fn (User $user) => $this->formatUser($user))->values()
            );
        }

        return view('users.index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create user endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'Name' => 'required|string|max:255',
            'Password' => 'required|string|min:6',
            'Role' => 'nullable|string|max:50',
            'Email' => 'nullable|email|max:255',
            'PhoneNumber' => 'nullable|string|max:20',
            'Status' => 'nullable|in:Active,Inactive',
        ]);

        return User::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return User::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return User::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'Name' => 'sometimes|required|string|max:255',
            'Password' => 'nullable|string|min:6',
            'Role' => 'nullable|string|max:50',
            'Email' => 'nullable|email|max:255',
            'PhoneNumber' => 'nullable|string|max:20',
            'Status' => 'nullable|in:Active,Inactive',
        ]);

        $user = User::findOrFail($id);
        $user->fill($validated);
        $user->save();

        return $user;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.'], 200);
    }

    public function activity(Request $request, User $user)
    {
        return response()->json([
            'ordersCreated' => $user->orders()
                ->when($request->date, fn($q) => $q->whereDate('OrderDate', $request->date))
                ->count(),
            'transactionsProcessed' => $user->transactions()
                ->when($request->date, fn($q) => $q->whereDate('TransactionDate', $request->date))
                ->count(),
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->UserID,
            'name' => $user->Name,
            'email' => $user->Email,
            'role' => strtolower($user->Role ?? ''),
            'status' => strtolower($user->Status ?? 'active'),
            'lastLogin' => $user->LastLoginAt
                ? $user->LastLoginAt->format('M j, Y, g:i A')
                : null,
        ];
    }
}