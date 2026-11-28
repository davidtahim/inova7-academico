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
use App\Models\User;
use App\Services\ScheduleAllocator;
use App\Services\ScheduleScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Etapa 2: grade global (arrastar e soltar), pontuação de penalidade (RN02).
 */
class ScheduleBoardTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private Course $course;

    private CurriculumMatrix $matrix;

    private Professor $ana;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        session(['selected_academic_term_id' => $this->term->id]);
        $this->course = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $this->matrix = CurriculumMatrix::create(['course_id' => $this->course->id, 'code' => 'GRA-MAT-0228-F', 'name' => 'Matriz 15', 'status' => 'Atual']);
        $this->ana = Professor::create(['name' => 'Ana Lima', 'active' => true]);
        Room::create(['code' => 'Sala 40', 'name' => 'Sala 40']);
        $this->coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
    }

    private function offering(string $classCode, string $name, int $classes = 2): ClassOffering
    {
        $subject = Subject::create(['code' => 'S' . substr(md5($name . $classCode), 0, 6), 'name' => $name]);
        $link = CurriculumMatrixDiscipline::create(['curriculum_matrix_id' => $this->matrix->id, 'subject_id' => $subject->id, 'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => $classes]);

        return ClassOffering::create([
            'academic_term_id' => $this->term->id, 'course_id' => $this->course->id, 'curriculum_matrix_id' => $this->matrix->id,
            'curriculum_matrix_discipline_id' => $link->id, 'subject_id' => $subject->id, 'class_code' => $classCode,
            'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => $classes,
        ]);
    }

    public function test_score_counts_missing_classes_gaps_and_preferences(): void
    {
        $allocator = app(ScheduleAllocator::class);
        $scorer = app(ScheduleScorer::class);
        $lp = $this->offering('CSE0280101NNA', 'Lógica de Programação', 2);
        $bd = $this->offering('CSE0280103NNA', 'Banco de Dados', 2);

        // Nada alocado: 4 aulas faltando = 4 restrições rígidas; 2 disciplinas sem professor (2 × 50).
        $score = $scorer->score($this->term);
        $this->assertSame(4, $score['hard']);
        $this->assertSame(4000 + 100, $score['total']);

        // Ana com A em uma turma e D em outra na segunda: 2 janelas (B, C).
        $allocator->apply($lp, ['professor_id' => $this->ana->id, 'placements' => [1 => ['A'], 3 => ['A']], 'room' => 'Sala 40']);
        $allocator->apply($bd, ['professor_id' => $this->ana->id, 'placements' => [1 => ['D'], 3 => ['B']], 'room' => 'Sala 40']);
        $score = $scorer->score($this->term);
        $this->assertSame(0, $score['hard']);
        $this->assertSame(2 * 5, $score['soft']); // segunda: A..D com B, C vazios → 2 janelas; quarta: A, B juntas

        // Com grade de disponibilidade: "disponível" custa 1 ponto; fora da grade é rígida.
        foreach ([[1, '18:30', 'preferred'], [3, '18:30', 'available'], [3, '19:20', 'preferred']] as [$day, $start, $pref]) {
            ProfessorAvailability::create(['academic_term_id' => $this->term->id, 'professor_id' => $this->ana->id, 'weekday' => $day, 'starts_at' => $start, 'ends_at' => '00:00', 'preference' => $pref]);
        }
        $score = $scorer->score($this->term);
        $this->assertSame(1, collect($score['breakdown'])->firstWhere('type', 'unavailable')['count']); // segunda D (21:10)
        $this->assertSame(1, collect($score['breakdown'])->firstWhere('type', 'not_preferred')['count']);
        $this->assertGreaterThan(0, $score['cells']['1|21:10'] ?? 0);
    }

    public function test_board_data_and_moves_with_undoable_semantics(): void
    {
        $lp = $this->offering('CSE0280101NNA', 'Lógica de Programação', 2);
        $mat = $this->offering('CSE0280101NNA', 'Matemática Aplicada', 2);
        app(ScheduleAllocator::class)->apply($lp, ['professor_id' => $this->ana->id, 'placements' => [1 => ['A']], 'room' => 'Sala 40']);

        $this->actingAs($this->coordinator)->get(route('allocation.board'))->assertOk()->assertSee('Grade global');
        $data = $this->actingAs($this->coordinator)->getJson(route('allocation.board.data', ['course' => $this->course->id, 'shift' => 'NOITE']))
            ->assertOk()->json();
        $this->assertSame('CSE0280101NNA', $data['classes'][0]['code']);
        $this->assertSame('LP', collect($data['classes'][0]['offerings'])->firstWhere('id', $lp->id)['abbr']);
        $this->assertCount(4 * 6, $data['row_issues']);

        // Colocar a 2ª aula (sem "from"), mover e tirar da grade (sem "to").
        $this->actingAs($this->coordinator)->postJson(route('allocation.board.move'), ['offering_id' => $lp->id, 'to' => ['weekday' => 3, 'letter' => 'B']])
            ->assertOk()->assertJson(['saved' => true]);
        $this->actingAs($this->coordinator)->postJson(route('allocation.board.move'), ['offering_id' => $lp->id, 'from' => ['weekday' => 3, 'letter' => 'B'], 'to' => ['weekday' => 4, 'letter' => 'C']])
            ->assertOk();
        $this->assertSame([1 => ['A'], 4 => ['C']], app(ScheduleAllocator::class)->currentInput($lp->fresh(['slots', 'assignments']))['placements']);

        // Choque na mesma turma é recusado e nada muda.
        app(ScheduleAllocator::class)->apply($mat, ['placements' => [2 => ['A']]]);
        $this->actingAs($this->coordinator)->postJson(route('allocation.board.move'), ['offering_id' => $mat->id, 'from' => ['weekday' => 2, 'letter' => 'A'], 'to' => ['weekday' => 1, 'letter' => 'A']])
            ->assertStatus(422)->assertJson(['saved' => false]);
        $this->assertSame([2 => ['A']], app(ScheduleAllocator::class)->currentInput($mat->fresh(['slots', 'assignments']))['placements']);

        $this->actingAs($this->coordinator)->postJson(route('allocation.board.move'), ['offering_id' => $lp->id, 'from' => ['weekday' => 4, 'letter' => 'C']])->assertOk();
        $this->assertSame([1 => ['A']], app(ScheduleAllocator::class)->currentInput($lp->fresh(['slots', 'assignments']))['placements']);
    }

    public function test_board_is_restricted_to_planners(): void
    {
        $student = User::create(['name' => 'Aluno', 'email' => 'a@x.local', 'password' => 'senha1234', 'role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->get(route('allocation.board'))->assertForbidden();
        $this->actingAs($student)->postJson(route('allocation.board.move'), ['offering_id' => 1])->assertForbidden();
    }
}
