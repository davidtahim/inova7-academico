<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Room;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\ScheduleDiagnostics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Etapa 4 (RF06): diagnóstico de inviabilidade — causa raiz e o que fazer.
 */
class ScheduleDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private Course $course;

    private CurriculumMatrix $matrix;

    private Professor $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        session(['selected_academic_term_id' => $this->term->id]);
        $this->course = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $this->matrix = CurriculumMatrix::create(['course_id' => $this->course->id, 'code' => 'GRA-MAT-0228-F', 'name' => 'Matriz 15', 'status' => 'Atual']);
        $this->ana = Professor::create(['name' => 'Ana', 'active' => true]);
        Room::create(['code' => 'Sala 40', 'name' => 'Sala 40']);
    }

    private function offering(string $classCode, string $name, int $classes = 2, array $linkExtra = []): ClassOffering
    {
        $subject = Subject::firstOrCreate(['name' => $name], ['code' => 'S' . substr(md5($name), 0, 6)]);
        $link = CurriculumMatrixDiscipline::firstOrCreate(
            ['curriculum_matrix_id' => $this->matrix->id, 'subject_id' => $subject->id],
            array_merge(['period' => 1, 'shift' => 'NOITE', 'weekly_hours' => $classes], $linkExtra)
        );

        return ClassOffering::create([
            'academic_term_id' => $this->term->id, 'course_id' => $this->course->id, 'curriculum_matrix_id' => $this->matrix->id,
            'curriculum_matrix_discipline_id' => $link->id, 'subject_id' => $subject->id, 'class_code' => $classCode,
            'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => $classes,
        ]);
    }

    /** Grade da Ana: disponível só nas células indicadas ("dia|HH:MM"). */
    private function anaAvailableAt(array $cells): void
    {
        foreach ($cells as $cell) {
            [$day, $start] = explode('|', $cell);
            ProfessorAvailability::create(['academic_term_id' => $this->term->id, 'professor_id' => $this->ana->id, 'weekday' => $day, 'starts_at' => $start, 'ends_at' => '00:00', 'preference' => 'available']);
        }
    }

    private function diagnose(): array
    {
        return app(ScheduleDiagnostics::class)->diagnose($this->term);
    }

    private function issue(array $result, string $kind): ?array
    {
        return collect($result['issues'])->firstWhere('kind', $kind);
    }

    public function test_subject_without_qualified_professor_is_grouped_across_classes(): void
    {
        $this->offering('CSE0280103NNA', 'Estrutura de Dados');
        $this->offering('CSE0280103NMA', 'Estrutura de Dados');

        $result = $this->diagnose();
        $issue = $this->issue($result, 'no_candidate');

        $this->assertSame('blocker', $issue['level']);
        $this->assertSame('Estrutura de Dados: nenhum professor habilitado', $issue['title']);
        $this->assertStringContainsString('CSE0280103NMA, CSE0280103NNA', $issue['detail']);
        $this->assertSame(4, $issue['classes']);
        $this->assertSame(1, $result['summary']['blockers']);
        $this->assertNotEmpty($issue['actions']);
    }

    public function test_only_qualified_professor_without_enough_free_slots_is_explained(): void
    {
        $bd = $this->offering('CSE0280103NNA', 'Banco de Dados II', 3);
        $this->ana->subjects()->attach($bd->subject_id);
        $this->anaAvailableAt(['1|18:30', '3|19:20']);

        $issue = $this->issue($this->diagnose(), 'offering_capacity');

        $this->assertSame('blocker', $issue['level']);
        $this->assertStringContainsString('O único professor habilitado (Ana) está disponível só em Seg A, Qua B', $issue['detail']);
        $this->assertStringContainsString('2 horários para 3 aulas', $issue['detail']);
        $this->assertSame(route('admin.professors.availability', $this->ana), $issue['actions'][0]['url']);
    }

    public function test_confirmed_professor_overload_suggests_other_qualified_professors(): void
    {
        $bruno = Professor::create(['name' => 'Bruno', 'active' => true]);
        $lp = $this->offering('CSE0280101NNA', 'Lógica de Programação');
        $bd = $this->offering('CSE0280103NNA', 'Banco de Dados');
        $this->ana->subjects()->attach([$lp->subject_id, $bd->subject_id]);
        $bruno->subjects()->attach($bd->subject_id);
        TeachingAssignment::create(['class_offering_id' => $lp->id, 'professor_id' => $this->ana->id, 'status' => 'confirmed']);
        TeachingAssignment::create(['class_offering_id' => $bd->id, 'professor_id' => $this->ana->id, 'status' => 'confirmed']);
        $this->anaAvailableAt(['1|18:30', '1|19:20', '2|18:30']);

        $issue = $this->issue($this->diagnose(), 'professor_overload');

        $this->assertSame('Ana: mais aulas do que horários disponíveis', $issue['title']);
        $this->assertStringContainsString('precisa dar 4 aulas na noite', $issue['detail']);
        $this->assertStringContainsString('disponível em só 3 horários', $issue['detail']);
        $this->assertStringContainsString('Banco de Dados (CSE0280103NNA) (também: Bruno)', $issue['detail']);
    }

    public function test_joint_classes_share_slots_and_do_not_overload_the_professor(): void
    {
        // Mesma disciplina em duas turmas com a Ana: aula conjunta, 2 horários bastam.
        $a = $this->offering('CSE0280101NNA', 'UX/UI Design');
        $b = $this->offering('CSE0400101NNA', 'UX/UI Design');
        $this->ana->subjects()->attach($a->subject_id);
        $this->anaAvailableAt(['1|18:30', '1|19:20']);

        $result = $this->diagnose();
        $this->assertNull($this->issue($result, 'professor_overload'));
        $this->assertSame(0, $result['summary']['blockers']);
        $this->assertSame('professor_tight', $this->issue($result, 'professor_tight')['kind'], 'Sem folga nenhuma: vira risco');
        unset($b);
    }

    public function test_class_with_more_classes_than_the_shift_holds(): void
    {
        foreach (range(1, 5) as $i) {
            $o = $this->offering('CSE0280101NNA', "Disciplina {$i}", 5);
            $this->ana->subjects()->attach($o->subject_id);
        }

        $issue = $this->issue($this->diagnose(), 'class_overload');

        $this->assertStringContainsString('25 aulas por semana', $issue['detail']);
        $this->assertStringContainsString('a noite tem 24 horários', $issue['detail']);
    }

    public function test_required_room_type_without_rooms_and_missing_grids(): void
    {
        $lab = $this->offering('CSE0280101NNA', 'Programação Web', 2, ['required_room_type' => 'LABORATORIO_INFORMATICA']);
        $this->ana->subjects()->attach($lab->subject_id);

        $result = $this->diagnose();
        $this->assertSame('blocker', $this->issue($result, 'no_room')['level']);
        $info = $this->issue($result, 'no_grid');
        $this->assertSame('info', $info['level']);
        $this->assertStringContainsString('Ana', $info['detail']);
    }

    public function test_page_and_board_summary_are_for_planners_only(): void
    {
        $this->offering('CSE0280103NNA', 'Estrutura de Dados');
        $coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $teacher = User::create(['name' => 'Prof', 'email' => 'p@x.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true]);

        $this->actingAs($coordinator)->get(route('allocation.diagnosis'))->assertOk()
            ->assertSee('A grade não pode fechar como está.')
            ->assertSee('Estrutura de Dados: nenhum professor habilitado');
        $this->actingAs($coordinator)->getJson(route('allocation.diagnosis.data'))->assertOk()
            ->assertJsonPath('summary.blockers', 1)
            ->assertJsonPath('top.0.title', 'Estrutura de Dados: nenhum professor habilitado');
        $this->actingAs($coordinator)->get(route('allocation.board'))->assertSee('Diagnóstico');

        $this->actingAs($teacher)->get(route('allocation.diagnosis'))->assertForbidden();
        $this->actingAs($teacher)->getJson(route('allocation.diagnosis.data'))->assertForbidden();
    }
}
