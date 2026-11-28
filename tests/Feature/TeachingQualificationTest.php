<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Professor;
use App\Models\Subject;
use App\Models\User;
use App\Services\TeachingQualificationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Habilitação docente (Professor ↔ Disciplinas, CCG-FOR-02).
 */
class TeachingQualificationTest extends TestCase
{
    use RefreshDatabase;

    private Professor $professor;

    private User $teacher;

    private CurriculumMatrix $si;

    private CurriculumMatrix $ads;

    private Subject $algoritmos;

    private Subject $bancoDados;

    private Subject $eticaEad;

    protected function setUp(): void
    {
        parent::setUp();

        AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);

        $this->professor = Professor::create(['name' => 'Prof. Ana', 'active' => true]);
        $this->teacher = User::create([
            'name' => 'Prof. Ana',
            'email' => 'ana@prof.local',
            'password' => 'senha1234',
            'role' => 'teacher',
            'is_active' => true,
            'professor_id' => $this->professor->id,
        ]);

        $siCourse = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $adsCourse = Course::create(['code' => 'GRA-PPC-0240-G', 'name' => 'Análise e Desenvolvimento de Sistemas']);
        $this->si = CurriculumMatrix::create(['course_id' => $siCourse->id, 'code' => 'GRA-MAT-0228-F', 'name' => 'Matriz 15', 'status' => 'Atual']);
        $this->ads = CurriculumMatrix::create(['course_id' => $adsCourse->id, 'code' => 'GRA-MAT-2140-H', 'name' => 'Matriz 56', 'status' => 'Ativa']);

        $this->algoritmos = Subject::create(['code' => 'SI101', 'name' => 'Algoritmos', 'presential_hours' => 40]);
        $this->bancoDados = Subject::create(['code' => 'SI201', 'name' => 'Banco de Dados', 'presential_hours' => 40]);
        $this->eticaEad = Subject::create(['code' => 'EAD001', 'name' => 'Ética', 'online_hours' => 40]);

        $this->link($this->si, $this->algoritmos, 1, ['required_room_type' => 'LABORATORIO_INFORMATICA']);
        $this->link($this->si, $this->bancoDados, 3);
        // Disciplina transversal: aparece em SI e ADS.
        $this->link($this->ads, $this->algoritmos, 1, ['allows_shared_class' => true]);
        $this->link($this->ads, $this->eticaEad, 2);
    }

    private function link(CurriculumMatrix $matrix, Subject $subject, int $period, array $extra = []): CurriculumMatrixDiscipline
    {
        return CurriculumMatrixDiscipline::create(array_merge([
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $subject->id,
            'period' => $period,
            'shift' => 'NOITE',
            'weekly_hours' => 2,
            'offering_type' => 'OBRIGATORIA',
        ], $extra));
    }

    public function test_catalog_groups_subjects_by_course_matrix_and_period(): void
    {
        $outside = Subject::create(['code' => 'X1', 'name' => 'Disciplina avulsa']);

        $data = app(TeachingQualificationCatalog::class)->build();

        $this->assertSame(
            ['Análise e Desenvolvimento de Sistemas', 'Sistemas de Informação'],
            $data['matrices']->map(fn ($g) => $g['matrix']->course->name)->all()
        );

        $siGroup = $data['matrices']->firstWhere('matrix.id', $this->si->id);
        $this->assertSame([1, 3], $siGroup['periods']->keys()->all());
        $this->assertSame('Algoritmos', $siGroup['periods'][1]->first()->subject->name);

        $this->assertSame([$outside->id], $data['others']->pluck('id')->all());
        // Algoritmos conta uma vez só, mesmo estando em duas matrizes.
        $this->assertSame(4, $data['total_subjects']);
    }

    public function test_inactive_matrices_are_not_listed(): void
    {
        $this->ads->update(['status' => 'Inativa']);

        $data = app(TeachingQualificationCatalog::class)->build();

        $this->assertSame([$this->si->id], $data['matrices']->pluck('matrix.id')->all());
        // Ética só estava na matriz inativa: passa para "outras disciplinas".
        $this->assertContains($this->eticaEad->id, $data['others']->pluck('id')->all());
    }

    public function test_ead_subjects_are_detected(): void
    {
        $this->assertTrue(TeachingQualificationCatalog::isFullyOnline($this->eticaEad));
        $this->assertFalse(TeachingQualificationCatalog::isFullyOnline($this->algoritmos));
    }

    public function test_page_renders_grouped_catalog_with_selections(): void
    {
        $this->professor->subjects()->sync([$this->bancoDados->id]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.subjects'))
            ->assertOk()
            ->assertSeeInOrder(['Análise e Desenvolvimento de Sistemas', 'Matriz 56', 'Sistemas de Informação', 'Matriz 15'])
            ->assertSee('1º período')
            ->assertSee('100% EaD')
            ->assertSee('Lab. informática')
            ->assertSee('Você marcou')
            ->assertSee('Só as marcadas')
            ->assertSee('Alterações não salvas', false)
            ->assertViewHas('selectedIds', [$this->bancoDados->id])
            ->assertViewHas('selectedCount', 1)
            ->assertViewHas('totalSubjects', 3);
    }

    public function test_duplicate_ids_from_shared_subjects_are_saved_once(): void
    {
        $this->actingAs($this->teacher)
            ->put(route('admin.professors.update-subjects', $this->professor), [
                'subject_ids' => [$this->algoritmos->id, $this->algoritmos->id, $this->eticaEad->id],
            ])
            ->assertRedirect(route('teacher.subjects'));

        $this->assertEqualsCanonicalizing(
            [$this->algoritmos->id, $this->eticaEad->id],
            $this->professor->subjects()->pluck('subjects.id')->all()
        );
    }

    public function test_unchecking_everything_clears_qualifications(): void
    {
        $this->professor->subjects()->sync([$this->algoritmos->id, $this->bancoDados->id]);

        $this->actingAs($this->teacher)
            ->put(route('admin.professors.update-subjects', $this->professor), [])
            ->assertRedirect();

        $this->assertSame(0, $this->professor->subjects()->count());
    }

    public function test_invalid_subject_is_rejected(): void
    {
        $this->actingAs($this->teacher)
            ->put(route('admin.professors.update-subjects', $this->professor), ['subject_ids' => [999999]])
            ->assertSessionHasErrors('subject_ids.0');

        $this->assertSame(0, $this->professor->subjects()->count());
    }

    public function test_coordinator_qualifies_a_professor(): void
    {
        $coordinator = User::create([
            'name' => 'Coordenação',
            'email' => 'coord@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $this->actingAs($coordinator)
            ->put(route('admin.professors.update-subjects', $this->professor), ['subject_ids' => [$this->bancoDados->id]])
            ->assertRedirect();

        $this->assertSame([$this->bancoDados->id], $this->professor->subjects()->pluck('subjects.id')->all());
    }

    public function test_teacher_sees_only_matrices_of_assigned_courses(): void
    {
        $this->professor->courses()->sync([$this->ads->course_id]);
        // Marcada antes, num curso que não é mais dele: continua visível para desmarcar.
        $this->professor->subjects()->sync([$this->bancoDados->id]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.subjects'))
            ->assertOk()
            ->assertSee('Matriz 56')
            ->assertDontSee('Matriz 15')
            ->assertSee('Mostrando as disciplinas dos seus cursos')
            ->assertSee('Marcadas em outros cursos')
            ->assertSee('Banco de Dados');
    }

    public function test_teacher_without_courses_sees_every_matrix(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('teacher.subjects'))
            ->assertOk()
            ->assertSee('Matriz 56')
            ->assertSee('Matriz 15')
            ->assertDontSee('Mostrando as disciplinas dos seus cursos');
    }

    public function test_admin_sets_the_courses_of_a_teacher(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@inova7.local', 'password' => 'senha1234',
            'role' => 'admin', 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $this->teacher))
            ->assertOk()
            ->assertSee('Cursos em que o professor atua')
            ->assertSee('Análise e Desenvolvimento de Sistemas');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $this->teacher), [
                'name' => 'Prof. Ana', 'email' => 'ana@prof.local', 'role' => 'teacher', 'is_active' => 1,
                'courses_submitted' => 1, 'course_ids' => [$this->ads->course_id],
            ])
            ->assertRedirect(route('admin.users.show', $this->teacher));

        // O vínculo com o professor existente é mantido (sem criar outro cadastro).
        $this->assertSame($this->professor->id, $this->teacher->fresh()->professor_id);
        $this->assertSame(1, Professor::count());
        $this->assertSame([$this->ads->course_id], $this->professor->courses()->pluck('courses.id')->all());

        // Desmarcar todos: volta a ver todas as matrizes.
        $this->actingAs($admin)
            ->put(route('admin.users.update', $this->teacher), [
                'name' => 'Prof. Ana', 'email' => 'ana@prof.local', 'role' => 'teacher', 'is_active' => 1,
                'courses_submitted' => 1,
            ]);
        $this->assertSame(0, $this->professor->courses()->count());
    }
}
