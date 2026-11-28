<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Professor;
use App\Models\Room;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\ScheduleAllocator;
use App\Services\SolverProblemBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Etapa 3: resolvedor automático (problema → Web Worker → revalidação no servidor).
 */
class ScheduleSolverTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private Course $course;

    private CurriculumMatrix $matrix;

    private Professor $ana;

    private Professor $bruno;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        session(['selected_academic_term_id' => $this->term->id]);
        $this->course = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $this->matrix = CurriculumMatrix::create(['course_id' => $this->course->id, 'code' => 'GRA-MAT-0228-F', 'name' => 'Matriz 15', 'status' => 'Atual']);
        $this->ana = Professor::create(['name' => 'Ana', 'active' => true]);
        $this->bruno = Professor::create(['name' => 'Bruno', 'active' => true]);
        Room::create(['code' => 'Sala 40', 'name' => 'Sala 40']);
        Room::create(['code' => 'Li41', 'name' => 'Li41', 'type' => 'LABORATORIO_INFORMATICA']);
        $this->coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
    }

    private function offering(string $classCode, string $name, int $classes = 2, array $linkExtra = []): ClassOffering
    {
        $subject = Subject::create(['code' => 'S' . substr(md5($name . $classCode), 0, 6), 'name' => $name]);
        $link = CurriculumMatrixDiscipline::create(array_merge(['curriculum_matrix_id' => $this->matrix->id, 'subject_id' => $subject->id, 'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => $classes], $linkExtra));

        return ClassOffering::create([
            'academic_term_id' => $this->term->id, 'course_id' => $this->course->id, 'curriculum_matrix_id' => $this->matrix->id,
            'curriculum_matrix_discipline_id' => $link->id, 'subject_id' => $subject->id, 'class_code' => $classCode,
            'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => $classes,
        ]);
    }

    public function test_problem_lists_candidates_rooms_and_fixed_professors(): void
    {
        $lp = $this->offering('CSE0280101NNA', 'Lógica de Programação', 2, ['required_room_type' => 'LABORATORIO_INFORMATICA']);
        $ma = $this->offering('CSE0280101NNA', 'Matemática Aplicada', 2);
        $this->ana->subjects()->attach([$lp->subject_id, $ma->subject_id]);
        $this->bruno->subjects()->attach($lp->subject_id);
        TeachingAssignment::create(['class_offering_id' => $ma->id, 'professor_id' => $this->ana->id, 'status' => 'confirmed']);

        $problem = app(SolverProblemBuilder::class)->build($this->term);
        $byId = collect($problem['offerings'])->keyBy('id');

        $this->assertEqualsCanonicalizing([$this->ana->id, $this->bruno->id], $byId[$lp->id]['candidates']);
        $this->assertSame(['Li41'], $byId[$lp->id]['rooms'], 'Só salas do tipo exigido');
        $this->assertSame($this->ana->id, $byId[$ma->id]['fixedProfessor']);
        $this->assertSame(['A', 'B', 'C', 'D'], array_keys($problem['shifts']['NOITE']));
        $this->assertSame(1000, $problem['weights']['conflict']);
    }

    public function test_apply_saves_valid_solution_and_rejects_clashes(): void
    {
        $lp = $this->offering('CSE0280101NNA', 'Lógica de Programação');
        $ma = $this->offering('CSE0280101NNA', 'Matemática Aplicada');
        $bd = $this->offering('CSE0280103NNA', 'Banco de Dados');

        $response = $this->actingAs($this->coordinator)->postJson(route('allocation.solver.apply'), ['solution' => [
            ['id' => $lp->id, 'professor_id' => $this->ana->id, 'room' => 'Sala 40', 'placements' => ['1' => ['A', 'B']]],
            ['id' => $ma->id, 'professor_id' => $this->bruno->id, 'room' => 'Li41', 'placements' => ['2' => ['A', 'B']]],
            // Ana em duas turmas ao mesmo tempo: o servidor recusa.
            ['id' => $bd->id, 'professor_id' => $this->ana->id, 'room' => 'Li41', 'placements' => ['1' => ['A', 'B']]],
        ]])->assertOk()->json();

        $this->assertSame(2, $response['saved']);
        $this->assertCount(1, $response['rejected']);
        $this->assertStringContainsString('Ana já leciona', $response['rejected'][0]);
        $this->assertSame([1 => ['A', 'B']], app(ScheduleAllocator::class)->currentInput($lp->fresh(['slots', 'assignments']))['placements']);
        $this->assertSame(0, $bd->slots()->count());
        $this->assertSame('planned', $lp->assignments()->first()->status);

        // Reenviar a mesma solução não regrava nada.
        $again = $this->actingAs($this->coordinator)->postJson(route('allocation.solver.apply'), ['solution' => [
            ['id' => $lp->id, 'professor_id' => $this->ana->id, 'room' => 'Sala 40', 'placements' => ['1' => ['A', 'B']]],
        ]])->json();
        $this->assertSame(0, $again['saved']);
        $this->assertSame(1, $again['unchanged']);
    }

    public function test_occupied_slots_outside_the_problem_become_fixed_blockers(): void
    {
        // EaD com horário (cópia antiga) ocupa Ana na segunda A/B: entra como bloqueio fixo.
        $dol = $this->offering('CSE0280101NNA', 'Desenvolvimento Pessoal', 2);
        app(ScheduleAllocator::class)->apply($dol, ['professor_id' => $this->ana->id, 'placements' => [1 => ['A', 'B']], 'room' => 'Sala 40']);
        $dol->update(['modality' => 'DOL']);
        $lp = $this->offering('CSE0280103NNA', 'Lógica de Programação', 2);
        $this->ana->subjects()->attach($lp->subject_id);

        $problem = app(SolverProblemBuilder::class)->build($this->term);
        $frozen = collect($problem['offerings'])->firstWhere('id', $dol->id);
        $this->assertTrue($frozen['frozen']);
        $this->assertSame([1 => ['A', 'B']], $frozen['placements']);
        $this->assertFalse(collect($problem['offerings'])->firstWhere('id', $lp->id)['frozen']);

        $node = new Process(['node', '--version']);
        $node->run();
        if (! $node->isSuccessful()) {
            return;
        }
        $script = 'const {Solver}=require(' . json_encode(public_path('js/schedule-solver.js')) . ');'
            . 'const p=JSON.parse(require("fs").readFileSync(0,"utf8"));'
            . '(async()=>{const s=new Solver(p,{seed:3});const r=await s.run(2000);console.log(JSON.stringify(r));})();';
        $process = new Process(['node', '-e', $script]);
        $process->setInput(json_encode($problem));
        $process->mustRun();
        $result = json_decode($process->getOutput(), true);

        $this->assertSame([$lp->id], array_column($result['solution'], 'id'), 'Bloqueio fixo não é devolvido');
        $applied = $this->actingAs($this->coordinator)->postJson(route('allocation.solver.apply'), ['solution' => $result['solution']])->json();
        $this->assertSame([], $applied['rejected'], 'Ana não pode cair na segunda A/B');
    }

    public function test_solver_routes_are_restricted_to_planners(): void
    {
        $teacher = User::create(['name' => 'Prof', 'email' => 'p@x.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true]);
        $this->actingAs($teacher)->getJson(route('allocation.solver.problem'))->assertForbidden();
        $this->actingAs($teacher)->postJson(route('allocation.solver.apply'), ['solution' => []])->assertForbidden();
        $this->actingAs($this->coordinator)->get(route('allocation.board'))->assertOk()->assertSee('Resolver automaticamente')->assertSee('schedule-solver.js');
    }

    public function test_javascript_solver_reaches_zero_on_small_problem(): void
    {
        $node = (new Process(['node', '--version']));
        $node->run();
        if (! $node->isSuccessful()) {
            $this->markTestSkipped('Node não disponível para testar o resolvedor JavaScript.');
        }

        // 3 disciplinas da mesma turma, 2 professores; começa tudo empilhado na segunda A/B.
        $offerings = [$this->offering('CSE0280101NNA', 'Lógica de Programação'), $this->offering('CSE0280101NNA', 'Matemática Aplicada'), $this->offering('CSE0280101NNA', 'Banco de Dados')];
        foreach ($offerings as $o) {
            $this->ana->subjects()->attach($o->subject_id);
            $this->bruno->subjects()->attach($o->subject_id);
        }
        $problem = app(SolverProblemBuilder::class)->build($this->term);

        $script = 'const {Solver}=require(' . json_encode(public_path('js/schedule-solver.js')) . ');'
            . 'const p=JSON.parse(require("fs").readFileSync(0,"utf8"));'
            . '(async()=>{const s=new Solver(p,{seed:7});const r=await s.run(3000);console.log(JSON.stringify(r));})();';
        $process = new Process(['node', '-e', $script]);
        $process->setInput(json_encode($problem));
        $process->mustRun();
        $result = json_decode($process->getOutput(), true);

        $this->assertSame(0, $result['hard'], json_encode($result['counts']));
        $this->assertCount(3, $result['solution']);
        foreach ($result['solution'] as $item) {
            $this->assertSame(2, array_sum(array_map('count', $item['placements'])), 'Cada disciplina com as 2 aulas da carga');
        }

        // E o servidor aceita a solução inteira.
        $applied = $this->actingAs($this->coordinator)->postJson(route('allocation.solver.apply'), ['solution' => $result['solution']])->json();
        $this->assertSame(3, $applied['saved']);
        $this->assertSame([], $applied['rejected']);
        $this->assertSame(0, $applied['hard_after']);
    }
}
