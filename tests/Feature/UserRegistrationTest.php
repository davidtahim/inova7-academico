<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O cadastro público de usuários foi removido: apenas o Administrador de TI
 * cria usuários (admin.users.*). Estes testes garantem que ninguém consiga
 * se autocadastrar — e muito menos com perfil de administrador.
 */
class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_form_is_not_available(): void
    {
        $this->get('/usuarios/novo')->assertNotFound();
    }

    public function test_guest_cannot_create_an_admin_user(): void
    {
        $this->post('/usuarios', [
            'name' => 'Invasor',
            'email' => 'invasor@example.com',
            'role' => 'admin',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'is_active' => '1',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'invasor@example.com']);
    }

    public function test_admin_ti_can_still_create_users(): void
    {
        $admin = User::create([
            'name' => 'Admin TI',
            'email' => 'admin@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Maria Souza',
            'email' => 'maria@inova7.local',
            'role' => 'student',
            'password' => 'Senha1234',
            'password_confirmation' => 'Senha1234',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'maria@inova7.local', 'role' => 'student']);
    }

    public function test_user_cannot_change_own_role_through_profile(): void
    {
        $student = User::create([
            'name' => 'Aluno',
            'email' => 'aluno@inova7.local',
            'password' => 'senha1234',
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->actingAs($student)->put('/perfil', [
            'name' => 'Aluno',
            'email' => 'aluno@inova7.local',
            'role' => 'admin',
        ]);

        $this->assertSame('student', $student->refresh()->role);
    }
}
