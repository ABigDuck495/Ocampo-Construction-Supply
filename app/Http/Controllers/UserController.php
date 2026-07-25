<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return User::query()->latest('UserID')->get();
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
    public function activity(Request $request, User $user){
        return [
            'orders_created' => $user->orders()
                ->when($request->date, fn($q) => $q->whereDate('OrderDate', $request->date))
                ->count(),
            'transactions_processed' => $user->transactions()
                ->when($request->date, fn($q) => $q->whereDate('TransactionDate', $request->date))
                ->count(),
        ];
    }
}
