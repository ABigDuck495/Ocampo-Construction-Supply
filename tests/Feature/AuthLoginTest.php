<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creation_stores_username(): void
    {
        $admin = User::create([
            'Name' => 'Admin User',
            'Username' => 'adminuser',
            'Password' => 'Password123',
            'Email' => 'admin@example.com',
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);

        $this->actingAs($admin);

        $response = $this->postJson('/users', [
            'Name' => 'Alice Example',
            'Username' => 'alice123',
            'Password' => 'Password123',
            'Email' => 'alice@example.com',
            'Role' => 'Staff',
            'Status' => 'Active',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'Username' => 'alice123',
            'Email' => 'alice@example.com',
        ]);
    }
}
