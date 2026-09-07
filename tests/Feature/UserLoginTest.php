<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'maria@inova7.local',
            'password' => 'senha1234',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'maria@inova7.local',
            'password' => 'senha1234',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inativo@inova7.local',
            'password' => 'senha1234',
            'is_active' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'inativo@inova7.local',
            'password' => 'senha1234',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_view_the_profile_page(): void
    {
        $user = User::create([
            'name' => 'Maria Souza',
            'email' => 'maria.perfil@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/perfil');

        $response->assertOk();
        $response->assertSee('Perfil do usuário');
    }
}
