<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Entrar como professor": coordenação e TI preenchem disponibilidade e disciplinas pelo professor. */
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Professor $professor;

    protected function setUp(): void
    {
        parent::setUp();
        AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $this->professor = Professor::create(['name' => 'Ana Exemplo', 'active' => true]);
        $this->teacher = User::create(['name' => 'Ana Exemplo', 'email' => 'ana@prof.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true, 'professor_id' => $this->professor->id]);
    }

    private function user(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => $role . '@x.local', 'password' => 'senha1234', 'role' => $role, 'is_active' => true]);
    }

    public function test_admin_enters_as_teacher_and_returns(): void
    {
        $admin = $this->user('admin');
        $show = route('admin.users.show', $this->teacher);

        $this->actingAs($admin)->get($show)->assertOk()->assertSee('Entrar como este professor');

        $this->actingAs($admin)->from($show)->post(route('impersonate.start', $this->teacher))
            ->assertRedirect(route('teacher.dashboard'));
        $this->assertAuthenticatedAs($this->teacher);

        $this->get(route('teacher.availability'))->assertOk()
            ->assertSee('Você está agindo como')->assertSee('Voltar para minha conta');

        // Salvar a disponibilidade como o professor.
        $term = AcademicTerm::first();
        $this->postJson(route('admin.professors.update-availability', $this->professor), [
            'academic_term_id' => $term->id,
            'availability_slots' => [['weekday' => 1, 'starts_at' => '18:30', 'ends_at' => '19:20', 'preference' => 'available']],
        ])->assertOk();
        $this->assertSame(1, $this->professor->availabilities()->count());

        // E-mail e senha do professor ficam protegidos.
        $this->from(route('profile.edit'))->put(route('profile.update'), ['name' => 'X', 'email' => 'x@x.local'])
            ->assertSessionHas('error');
        $this->assertSame('ana@prof.local', $this->teacher->fresh()->email);

        $this->post(route('impersonate.stop'))->assertRedirect($show);
        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(session()->has('impersonator_id'));
    }

    public function test_coordinator_enters_from_the_professors_list(): void
    {
        $coordinator = $this->user('coordinator');

        $this->actingAs($coordinator)->get(route('admin.professors.index'))->assertOk()
            ->assertSee(route('impersonate.start', $this->teacher), false);
        $this->actingAs($coordinator)->post(route('impersonate.start', $this->teacher))->assertRedirect(route('teacher.dashboard'));
        $this->assertAuthenticatedAs($this->teacher);
    }

    public function test_only_active_teacher_accounts_can_be_entered(): void
    {
        $coordinator = $this->user('coordinator');
        $admin = $this->user('admin');

        $this->actingAs($coordinator)->post(route('impersonate.start', $admin))->assertForbidden();
        $this->actingAs($admin)->post(route('impersonate.start', $coordinator))->assertForbidden();

        $this->teacher->update(['is_active' => false]);
        $this->actingAs($admin)->post(route('impersonate.start', $this->teacher))->assertForbidden();

        $this->teacher->update(['is_active' => true]);
        $this->actingAs($this->user('staff'))->post(route('impersonate.start', $this->teacher))->assertForbidden();
        $other = User::create(['name' => 'Outro', 'email' => 'o@prof.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true]);
        $this->actingAs($other)->post(route('impersonate.start', $this->teacher))->assertForbidden();
    }

    public function test_stop_without_impersonation_just_goes_home(): void
    {
        $this->actingAs($this->teacher)->post(route('impersonate.stop'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->teacher);
    }
}
