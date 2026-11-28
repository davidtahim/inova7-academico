<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tela de disciplinas unificada: grade por matriz + catálogo.
 */
class SubjectsCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;

    private CurriculumMatrix $m15;

    private CurriculumMatrix $m56;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $si = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $ads = Course::create(['code' => 'GRA-PPC-0240-G', 'name' => 'Análise e Desenvolvimento de Sistemas']);
        $this->m15 = CurriculumMatrix::create(['course_id' => $si->id, 'code' => 'GRA-MAT-0228-F', 'name' => 'Matriz 15', 'status' => 'Atual']);
        $this->m56 = CurriculumMatrix::create(['course_id' => $ads->id, 'code' => 'GRA-MAT-2140-H', 'name' => 'Matriz 56', 'status' => 'Atual']);

        $lp = Subject::create(['code' => 'GSER895302', 'name' => 'Lógica de Programação', 'abbreviation' => 'LP']);
        $bd = Subject::create(['code' => 'GSER895400', 'name' => 'Banco de Dados']);
        Subject::create(['code' => 'UNI7T999', 'name' => 'Disciplina Avulsa']);
        $this->link($this->m15, $lp, 1, 2);
        $this->link($this->m15, $bd, 2, 3);
        $this->link($this->m56, $lp, 1, 2);
    }

    private function link(CurriculumMatrix $matrix, Subject $subject, int $period, int $weekly): void
    {
        CurriculumMatrixDiscipline::create([
            'curriculum_matrix_id' => $matrix->id, 'subject_id' => $subject->id, 'period' => $period,
            'shift' => 'NOITE', 'weekly_hours' => $weekly, 'offering_type' => 'OBRIGATORIA',
        ]);
    }

    public function test_matrix_view_shows_the_curriculum_by_period(): void
    {
        $this->actingAs($this->coordinator)->get(route('admin.subjects.index', ['matriz' => $this->m15->id]))
            ->assertOk()
            ->assertSeeInOrder(['1º período', 'Lógica de Programação', '2º período', 'Banco de Dados'])
            ->assertSee('5</strong> aulas/semana no total', false)
            ->assertSee('Adicionar à Matriz 15')
            ->assertDontSee('Disciplina Avulsa</div>', false);

        // A escolha fica guardada: voltar a /cadastros/disciplinas reabre a mesma matriz.
        $this->actingAs($this->coordinator)->get(route('admin.subjects.index'))->assertSee('Adicionar à Matriz 15');
    }

    public function test_search_filters_within_the_matrix(): void
    {
        $this->actingAs($this->coordinator)->get(route('admin.subjects.index', ['matriz' => $this->m15->id, 'q' => 'banco']))
            ->assertOk()->assertSee('Banco de Dados')->assertDontSee('Lógica de Programação</div>', false);
    }

    public function test_catalog_shows_where_each_subject_is_and_unlinked_filter(): void
    {
        $this->actingAs($this->coordinator)->get(route('admin.subjects.index', ['matriz' => 'todas']))
            ->assertOk()
            ->assertSee('Matriz 15 · 1º')
            ->assertSee('Matriz 56 · 1º')
            ->assertSee('Fora de todas as matrizes');

        $this->actingAs($this->coordinator)->get(route('admin.subjects.index', ['matriz' => 'sem']))
            ->assertOk()->assertSee('Disciplina Avulsa')->assertDontSee('Banco de Dados');
    }

    public function test_new_subject_created_from_a_matrix_returns_ready_to_link(): void
    {
        $this->actingAs($this->coordinator)->get(route('admin.subjects.create', ['matriz' => $this->m56->id]))
            ->assertOk()->assertSee('você volta para a matriz', false);

        $response = $this->actingAs($this->coordinator)->post(route('admin.subjects.store'), [
            'code' => 'GSER2049800', 'name' => 'Back-end Frameworks', 'matriz' => $this->m56->id,
        ]);
        $subject = Subject::where('name', 'Back-end Frameworks')->firstOrFail();

        $response->assertRedirect(route('admin.subjects.index', ['matriz' => $this->m56->id]))
            ->assertSessionHas('link_subject', $subject->id);
    }

    public function test_old_matrix_grid_address_opens_the_matrix_view(): void
    {
        $this->actingAs($this->coordinator)->get('/cadastros/matrizes/' . $this->m15->id . '/disciplinas')
            ->assertRedirect(route('admin.subjects.index', ['matriz' => $this->m15->id]));
    }

    public function test_staff_cannot_manage_the_curriculum(): void
    {
        $staff = User::create(['name' => 'Func', 'email' => 'f@x.local', 'password' => 'senha1234', 'role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff)->get(route('admin.subjects.index', ['matriz' => $this->m15->id]))->assertForbidden();
    }

    public function test_catalog_offers_adding_an_unlinked_subject_to_a_matrix(): void
    {
        $avulsa = Subject::where('name', 'Disciplina Avulsa')->firstOrFail();

        $this->actingAs($this->coordinator)->get(route('admin.subjects.index', ['matriz' => 'sem']))
            ->assertOk()->assertSee('Adicionar à matriz')->assertSee('catalogLinkModal', false);

        $this->actingAs($this->coordinator)->post(route('admin.matrices.disciplines.store', $this->m56), [
            'subject_id' => $avulsa->id, 'period' => 3, 'shift' => 'NOITE', 'weekly_hours' => 2, 'offering_type' => 'OBRIGATORIA',
        ])->assertRedirect(route('admin.subjects.index', ['matriz' => $this->m56->id]) . '#periodo-3');

        $this->actingAs($this->coordinator)->get(route('admin.subjects.index', ['matriz' => 999]))
            ->assertOk()->assertSee('A matriz #999 não foi encontrada');
    }
}
