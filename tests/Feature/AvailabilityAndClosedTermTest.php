<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Disponibilidade: salva no semestre exibido (não "some" ao reabrir), cópia entre
 * semestres e regra de semestre concluído (somente consulta).
 */
class AvailabilityAndClosedTermTest extends TestCase
{
    use RefreshDatabase;

    private Professor $professor;

    private User $teacher;

    private AcademicTerm $planning;

    private AcademicTerm $closed;

    protected function setUp(): void
    {
        parent::setUp();
        // O concluído é criado depois (id maior): antes o sistema caía nele por engano.
        $this->planning = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $this->closed = AcademicTerm::create(['code' => '2026.1', 'status' => 'closed']);
        $this->professor = Professor::create(['name' => 'Ana', 'active' => true]);
        $this->teacher = User::create(['name' => 'Ana', 'email' => 'ana@x.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true, 'professor_id' => $this->professor->id]);
    }

    private function slots(array $cells): array
    {
        return array_map(fn ($c) => ['weekday' => $c[0], 'starts_at' => $c[1], 'ends_at' => $c[2], 'preference' => $c[3]], $cells);
    }

    public function test_current_term_prefers_the_planning_semester(): void
    {
        $this->assertSame('2027.1', AcademicTerm::current()->code);

        $this->actingAs($this->teacher)->get(route('teacher.availability'))->assertOk()
            ->assertViewHas('term', fn ($t) => $t->is($this->planning))
            ->assertSee('Quando você pode dar aula');
    }

    public function test_saved_grid_is_there_when_the_page_is_reopened(): void
    {
        $this->actingAs($this->teacher)->postJson(route('admin.professors.update-availability', $this->professor), [
            'academic_term_id' => $this->planning->id,
            'availability_slots' => $this->slots([[1, '18:30', '19:20', 'preferred'], [3, '19:20', '20:10', 'available']]),
        ])->assertOk()->assertJson(['success' => true, 'saved' => 2, 'term' => '2027.1']);

        $this->actingAs($this->teacher)->get(route('teacher.availability'))->assertOk()
            ->assertViewHas('savedAvailabilities', fn ($s) => $s[1]['18:30 - 19:20'] === 'preferred' && $s[3]['19:20 - 20:10'] === 'available')
            ->assertSee('data-status="preferred"', false);

        // PUT continua aceito.
        $this->actingAs($this->teacher)->putJson(route('admin.professors.update-availability', $this->professor), [
            'academic_term_id' => $this->planning->id, 'availability_slots' => [],
        ])->assertOk();
        $this->assertSame(0, ProfessorAvailability::count());
    }

    public function test_closed_term_accepts_no_changes(): void
    {
        ProfessorAvailability::create(['academic_term_id' => $this->closed->id, 'professor_id' => $this->professor->id, 'weekday' => 1, 'starts_at' => '18:30', 'ends_at' => '19:20', 'preference' => 'preferred']);

        $this->actingAs($this->teacher)->postJson(route('admin.professors.update-availability', $this->professor), [
            'academic_term_id' => $this->closed->id, 'availability_slots' => [],
        ])->assertStatus(423)->assertJson(['success' => false]);
        $this->assertSame(1, ProfessorAvailability::count(), 'Nada foi apagado');

        // A grade do semestre concluído abre só para consulta.
        session(['selected_academic_term_id' => $this->closed->id]);
        $this->actingAs($this->teacher)->get(route('teacher.availability'))->assertOk()
            ->assertViewHas('isEditable', false)
            ->assertSee('semestre concluído', false);

        // Planejamento: alterações recusadas, consulta liberada.
        $coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $this->actingAs($coordinator)->withSession(['selected_academic_term_id' => $this->closed->id])
            ->postJson(route('allocation.board.move'), ['offering_id' => 1])->assertStatus(423);
        $this->actingAs($coordinator)->withSession(['selected_academic_term_id' => $this->closed->id])
            ->from(route('allocation.scenarios'))->post(route('allocation.scenarios.store'), ['name' => 'x'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, '2026.1 está concluído'));
        $this->actingAs($coordinator)->withSession(['selected_academic_term_id' => $this->closed->id])
            ->get(route('allocation.board'))->assertOk();
    }

    public function test_grid_from_another_term_can_be_copied(): void
    {
        ProfessorAvailability::create(['academic_term_id' => $this->closed->id, 'professor_id' => $this->professor->id, 'weekday' => 2, 'starts_at' => '18:30', 'ends_at' => '19:20', 'preference' => 'available']);

        $this->actingAs($this->teacher)->get(route('teacher.availability'))->assertSee('Copiar de 2026.1');

        $this->actingAs($this->teacher)->post(route('admin.professors.copy-availability', $this->professor), [
            'from_term_id' => $this->closed->id, 'to_term_id' => $this->planning->id,
        ])->assertRedirect(route('teacher.availability'))->assertSessionHas('success');
        $this->assertSame(1, ProfessorAvailability::where('academic_term_id', $this->planning->id)->count());

        // Copiar para um semestre concluído é recusado.
        $this->actingAs($this->teacher)->post(route('admin.professors.copy-availability', $this->professor), [
            'from_term_id' => $this->planning->id, 'to_term_id' => $this->closed->id,
        ])->assertSessionHas('error');
    }

    public function test_lattes_is_saved_complete_even_without_scheme(): void
    {
        $this->assertSame('https://lattes.cnpq.br/123', Professor::normalizeLattes('lattes.cnpq.br/123'));
        $this->assertSame('http://lattes.cnpq.br/1234567890123456', Professor::normalizeLattes('1234567890123456'));
        $this->assertSame('http://lattes.cnpq.br/9', Professor::normalizeLattes('http://lattes.cnpq.br/9'));
        $this->assertNull(Professor::normalizeLattes('  '));
    }
}
