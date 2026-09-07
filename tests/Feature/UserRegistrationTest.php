<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created(): void
    {
        $response = $this->post('/usuarios', [
            'name' => 'Maria Souza',
            'email' => 'maria@inova7.local',
            'registration_number' => '2024001',
            'role' => 'student',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', [
            'email' => 'maria@inova7.local',
            'role' => 'student',
        ]);

        $user = User::where('email', 'maria@inova7.local')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('senha1234', $user->password));
    }
}
