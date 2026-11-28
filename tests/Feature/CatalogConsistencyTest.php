<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cursos × matrizes × turmas sempre coerentes: vínculo pela tela do curso,
 * bloqueio de duplicatas e painel de consistência.
 */
class CatalogConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
    }

    public function test_course_page_lists_matrices_and_moves_one_from_another_course_with_its_classes(): void
    {
        $ia = Course::create(['code' => 'CSE330', 'name' => 'Bacharelado em Inteligência Artificial']);
        $si = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $wrong = CurriculumMatrix::create(['course_id' => $si->id, 'code' => 'IA-2027', 'name' => 'Matriz IA', 'status' => 'Atual']);
        $term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $subject = Subject::create(['code' => 'S1', 'name' => 'Lógica']);
        $offering = ClassOffering::create(['academic_term_id' => $term->id, 'course_id' => $si->id, 'curriculum_matrix_id' => $wrong->id, 'subject_id' => $subject->id, 'class_code' => 'CSE3300101NNA', 'period' => 1, 'shift' => 'NOITE']);

        $this->actingAs($this->coordinator)->get(route('admin.courses.index'))->assertOk()
            ->assertSee('Bacharelado em Inteligência Artificial')->assertSee('Sem matriz: vincular');
        $this->actingAs($this->coordinator)->get(route('admin.courses.edit', $ia))->assertOk()
            ->assertSee('Matrizes deste curso (0)')->assertSee('Hoje em: Sistemas de Informação', false);

        $this->actingAs($this->coordinator)->post(route('admin.courses.attach-matrix', $ia), ['matrix_id' => $wrong->id])
            ->assertSessionHas('success');
        $this->assertSame($ia->id, $wrong->fresh()->course_id);
        $this->assertSame($ia->id, $offering->fresh()->course_id, 'As turmas acompanham a matriz');
        $this->actingAs($this->coordinator)->get(route('admin.courses.edit', $ia))->assertSee('Matrizes deste curso (1)');
    }

    public function test_duplicates_are_blocked_and_deletion_is_safe(): void
    {
        $si = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $ads = Course::create(['code' => 'GRA-PPC-0240-G', 'name' => 'ADS']);
        CurriculumMatrix::create(['course_id' => $si->id, 'code' => 'GRA-MAT-0228-F', 'name' => 'Matriz 15']);

        $this->actingAs($this->coordinator)->post(route('admin.courses.store'), ['code' => 'SI', 'name' => 'SISTEMAS DE INFORMACAO'])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->coordinator)->post(route('admin.matrices.store'), ['course_id' => $ads->id, 'code' => 'GRA-MAT-0228-F', 'name' => 'Cópia'])
            ->assertSessionHasErrors('code');
        $this->assertSame(1, CurriculumMatrix::count());

        $this->actingAs($this->coordinator)->from(route('admin.courses.index'))->delete(route('admin.courses.destroy', $si))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'não pode ser excluído'));
        $this->assertNotNull($si->fresh());
        $this->actingAs($this->coordinator)->delete(route('admin.courses.destroy', $ads))->assertSessionHas('success');
    }

    public function test_overview_points_out_inconsistencies(): void
    {
        $si = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $siDup = Course::create(['code' => 'SI', 'name' => 'Sistemas de Informação']);
        $ia = Course::create(['code' => 'CSE330', 'name' => 'Inteligência Artificial']);
        CurriculumMatrix::create(['course_id' => $si->id, 'code' => 'GRA-MAT-2140-H', 'name' => 'Matriz 56']);
        CurriculumMatrix::create(['course_id' => $ia->id, 'code' => 'GRA-MAT-2140-H', 'name' => 'Matriz 56 (errada)']);

        $this->actingAs($this->coordinator)->get(route('admin.planejamento.index'))->assertOk()
            ->assertSee('Consistência do catálogo')
            ->assertSee('Curso cadastrado mais de uma vez: Sistemas de Informação')
            ->assertSee('Matriz GRA-MAT-2140-H cadastrada em mais de um curso')
            ->assertSee('Curso sem matriz: Sistemas de Informação');
        unset($siDup);
    }
}
