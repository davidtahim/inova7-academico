<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Subject;
use App\Models\User;
use App\Services\SolverProblemBuilder;
use App\Support\CourseScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Coordenação vê e altera só os próprios cursos (coordenador ou assistente). */
class CourseScopeTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private Course $mine;

    private Course $other;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $this->coordinator = User::create(['name' => 'Coord SI', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $this->mine = Course::create(['code' => 'SI', 'name' => 'Sistemas de Informação', 'coordinator_user_id' => $this->coordinator->id]);
        $this->other = Course::create(['code' => 'DIR', 'name' => 'Direito']);
    }

    private function offering(Course $course, string $name): ClassOffering
    {
        $subject = Subject::create(['code' => strtoupper(substr(md5($name), 0, 8)), 'name' => $name]);
        $matrix = CurriculumMatrix::firstOrCreate(['course_id' => $course->id, 'code' => 'M-' . $course->code], ['name' => 'Matriz ' . $course->code, 'status' => 'Atual']);
        $link = CurriculumMatrixDiscipline::create(['curriculum_matrix_id' => $matrix->id, 'subject_id' => $subject->id, 'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => 2]);

        return ClassOffering::create([
            'academic_term_id' => $this->term->id, 'course_id' => $course->id, 'curriculum_matrix_id' => $matrix->id,
            'curriculum_matrix_discipline_id' => $link->id, 'subject_id' => $subject->id, 'class_code' => 'T-' . $course->code,
            'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => 2,
        ]);
    }

    public function test_lists_show_only_own_courses(): void
    {
        $this->offering($this->mine, 'Algoritmos');
        $this->offering($this->other, 'Direito Civil');

        $this->actingAs($this->coordinator)->get(route('admin.courses.index'))->assertOk()
            ->assertSee('Sistemas de Informação')->assertDontSee('Direito');
        $this->actingAs($this->coordinator)->get(route('allocation.index'))->assertOk()
            ->assertSee('Algoritmos')->assertDontSee('Direito Civil');
        $this->actingAs($this->coordinator)->get(route('planning.index'))->assertOk()
            ->assertSee('Algoritmos')->assertDontSee('Direito Civil');
        $this->actingAs($this->coordinator)->get(route('audit.courses.index'))->assertOk()
            ->assertSee('Sistemas de Informação')->assertDontSee('Direito');
        $this->actingAs($this->coordinator)->get(route('admin.matrices.index'))->assertOk()
            ->assertSee('Matriz SI')->assertDontSee('Matriz DIR');
    }

    public function test_other_courses_are_blocked(): void
    {
        $theirs = $this->offering($this->other, 'Direito Civil');
        $matrix = CurriculumMatrix::where('course_id', $this->other->id)->first();

        $this->actingAs($this->coordinator)->get(route('admin.courses.edit', $this->other))->assertForbidden();
        $this->actingAs($this->coordinator)->get(route('admin.matrices.edit', $matrix))->assertForbidden();
        $this->actingAs($this->coordinator)->get(route('allocation.index', ['course' => $this->other->id]))->assertForbidden();
        $this->actingAs($this->coordinator)->put(route('allocation.update', $theirs), ['per_day' => 1])->assertForbidden();
        $this->actingAs($this->coordinator)->postJson(route('allocation.board.move'), ['offering_id' => $theirs->id])->assertForbidden();
        $this->actingAs($this->coordinator)->get(route('audit.courses.show', $this->other))->assertForbidden();
        $this->actingAs($this->coordinator)->get(route('enrollment.index', ['matrix' => $matrix->id]))->assertForbidden();
        $this->actingAs($this->coordinator)->get(route('admin.courses.edit', $this->mine))->assertOk();
    }

    public function test_assistant_also_gets_the_course(): void
    {
        $assistant = User::create(['name' => 'Ianelli', 'email' => 'i@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $this->other->assistants()->attach($assistant->id);

        $this->assertSame([$this->other->id], CourseScope::ids($assistant));
        $this->actingAs($assistant)->get(route('admin.courses.edit', $this->other))->assertOk();
        $this->actingAs($assistant)->get(route('admin.courses.edit', $this->mine))->assertForbidden();
    }

    public function test_admin_and_unlinked_coordinator_see_everything(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@x.local', 'password' => 'senha1234', 'role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('admin.courses.index'))->assertOk()->assertSee('Direito');

        $new = User::create(['name' => 'Nova', 'email' => 'n@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $this->actingAs($new)->get(route('admin.courses.index'))->assertOk()
            ->assertSee('Direito')->assertSee('Você ainda não tem cursos vinculados');
    }

    public function test_solver_only_moves_own_courses_and_publishing_is_for_admin(): void
    {
        $this->offering($this->mine, 'Algoritmos');
        $theirs = $this->offering($this->other, 'Direito Civil');

        $problem = app(SolverProblemBuilder::class)->build($this->term, [$this->mine->id]);
        $this->assertSame([$this->mine->id], collect($problem['offerings'])->where('frozen', false)->pluck('course')->unique()->values()->all());

        $this->actingAs($this->coordinator)->postJson(route('allocation.solver.apply'), [
            'solution' => [['id' => $theirs->id, 'professor_id' => null, 'room' => null, 'placements' => ['1' => ['A', 'B']]]],
        ])->assertOk()->assertJsonPath('saved', 0);
        $this->assertSame(0, $theirs->slots()->count());

        $this->actingAs($this->coordinator)->post(route('allocation.publish'))->assertForbidden();
    }

    public function test_course_created_by_coordinator_is_his(): void
    {
        $this->actingAs($this->coordinator)->post(route('admin.courses.store'), ['code' => 'IA', 'name' => 'Inteligência Artificial', 'active' => 1])->assertRedirect();
        $this->assertSame($this->coordinator->id, Course::where('code', 'IA')->value('coordinator_user_id'));
    }
}
