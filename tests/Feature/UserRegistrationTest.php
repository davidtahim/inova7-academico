<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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

    public function test_user_can_be_created_when_registration_number_column_is_missing(): void
    {
        if (Schema::hasColumn('users', 'registration_number')) {
            DB::statement('DROP INDEX IF EXISTS users_registration_number_unique');

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('registration_number');
            });
        }

        $response = $this->post('/usuarios', [
            'name' => 'Carlos Pereira',
            'email' => 'carlos@inova7.local',
            'registration_number' => '2024002',
            'role' => 'coordinator',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', [
            'email' => 'carlos@inova7.local',
            'role' => 'coordinator',
        ]);
    }
}
