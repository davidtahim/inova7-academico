<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Manutenção sem terminal: migrations e caches pelo navegador (só Administrador de TI).
 */
class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => $role . '@x.local', 'password' => 'senha1234', 'role' => $role, 'is_active' => true]);
    }

    public function test_admin_sees_pending_migration_and_applies_it(): void
    {
        $admin = $this->user('admin');
        // Simula uma atualização recém-chegada: a última migration ainda não foi aplicada.
        Schema::dropIfExists('schedule_scenarios');
        DB::table('migrations')->where('migration', '2026_11_28_000500_create_schedule_scenarios_table')->delete();

        $this->actingAs($admin)->get(route('system.maintenance'))->assertOk()
            ->assertSee('1 pendente(s)')
            ->assertSee('2026_11_28_000500_create_schedule_scenarios_table')
            ->assertSee('Aplicar migrations pendentes');

        $this->actingAs($admin)->from(route('system.maintenance'))->post(route('system.maintenance.migrate'))
            ->assertRedirect(route('system.maintenance'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 migration(s) aplicada(s)'));

        $this->assertTrue(Schema::hasTable('schedule_scenarios'));
        $this->actingAs($admin)->get(route('system.maintenance'))->assertSee('atualizado')->assertDontSee('Aplicar migrations pendentes');
    }

    public function test_nothing_to_apply_and_cache_clear(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->from(route('system.maintenance'))->post(route('system.maintenance.migrate'))
            ->assertSessionHas('warning', 'Nenhuma migration pendente.');
        $this->actingAs($admin)->from(route('system.maintenance'))->post(route('system.maintenance.clear'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Caches limpos'));
    }

    public function test_only_it_admin_can_use_maintenance(): void
    {
        foreach (['coordinator', 'staff', 'teacher', 'student'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get(route('system.maintenance'))->assertForbidden();
            $this->actingAs($user)->post(route('system.maintenance.migrate'))->assertForbidden();
        }
    }

    public function test_public_files_are_served_by_laravel_without_the_symlink(): void
    {
        Storage::fake('public');
        Storage::disk('public')->putFileAs('profile-photos', UploadedFile::fake()->image('foto.jpg'), 'foto.jpg');
        $user = $this->user('teacher');

        $this->get('/storage/profile-photos/foto.jpg')->assertRedirect(route('login'));
        $this->actingAs($user)->get('/storage/profile-photos/foto.jpg')->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->actingAs($user)->get('/storage/profile-photos/nao-existe.jpg')->assertNotFound();
        $this->actingAs($user)->get('/storage/../.env')->assertNotFound();
    }
}
