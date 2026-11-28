<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Professor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Coordenador do curso vinculado a um usuário (coordenação ou Administrador de TI). */
class CourseCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'David Tahim Alves Brito', 'email' => 'david.brito@uni7.edu.br', 'password' => 'senha1234', 'role' => 'admin', 'is_active' => true]);
    }

    private function payload(User $user, array $extra = []): array
    {
        return ['name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'is_active' => 1] + $extra;
    }

    public function test_admin_ti_also_coordinates_courses_from_his_user_page(): void
    {
        $si = Course::create(['code' => 'SI', 'name' => 'Sistemas de Informação']);
        $ads = Course::create(['code' => 'ADS', 'name' => 'Análise e Desenvolvimento de Sistemas']);
        $other = Course::create(['code' => 'X', 'name' => 'Outro curso']);

        $this->actingAs($this->admin)->get(route('admin.users.edit', $this->admin))->assertOk()->assertSee('Cursos que coordena');
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), $this->payload($this->admin, [
            'coordinated_submitted' => 1, 'coordinated_course_ids' => [$si->id, $ads->id],
        ]))->assertRedirect();

        $this->assertSame('admin', $this->admin->fresh()->role);
        $this->assertEqualsCanonicalizing([$si->id, $ads->id], $this->admin->coordinatedCourses()->pluck('id')->all());
        $this->assertNull($other->fresh()->coordinator_user_id);

        // Desmarcar tira a coordenação.
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), $this->payload($this->admin, [
            'coordinated_submitted' => 1, 'coordinated_course_ids' => [$si->id],
        ]));
        $this->assertNull($ads->fresh()->coordinator_user_id);
    }

    public function test_course_form_picks_a_coordinator_user(): void
    {
        $coordinator = User::create(['name' => 'Maria Souza', 'email' => 'maria@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $teacher = User::create(['name' => 'Prof', 'email' => 'p@x.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true]);
        $course = Course::create(['code' => 'SI', 'name' => 'Sistemas de Informação']);

        $this->actingAs($this->admin)->get(route('admin.courses.edit', $course))->assertOk()
            ->assertSee('Maria Souza · Coordenação')->assertSee('David Tahim Alves Brito · Administrador de TI')->assertDontSee('>Prof ·', false);

        $this->actingAs($this->admin)->put(route('admin.courses.update', $course), ['code' => 'SI', 'name' => 'Sistemas de Informação', 'coordinator_user_id' => $teacher->id, 'active' => 1])
            ->assertSessionHasErrors('coordinator_user_id');
        $this->actingAs($this->admin)->put(route('admin.courses.update', $course), ['code' => 'SI', 'name' => 'Sistemas de Informação', 'coordinator_user_id' => $coordinator->id, 'active' => 1])
            ->assertRedirect();
        $this->assertSame($coordinator->id, $course->fresh()->coordinator_user_id);
        $this->assertSame('Maria Souza', $course->fresh()->coordinatorDisplayName());
    }

    public function test_ccg_for_02_uses_the_coordinator_user_when_no_name_is_set(): void
    {
        $term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $course = Course::create(['code' => 'SI', 'name' => 'Sistemas de Informação', 'coordinator_user_id' => $this->admin->id]);
        $professor = Professor::create(['name' => 'Ana', 'active' => true]);
        $professor->courses()->attach($course->id);

        $controller = app(\App\Http\Controllers\UserController::class);
        $data = (new \ReflectionMethod($controller, 'ccgFor02Data'))->invoke($controller, $professor, $term, 'x');
        $this->assertSame('SISTEMAS DE INFORMAÇÃO – PROF. DAVID TAHIM ALVES BRITO', $data['coordination']);

        $course->update(['coordinator_name' => 'Prof. David Tahim']);
        $data = (new \ReflectionMethod($controller, 'ccgFor02Data'))->invoke($controller, $professor->fresh(), $term, 'x');
        $this->assertSame('SISTEMAS DE INFORMAÇÃO – PROF. DAVID TAHIM', $data['coordination']);
    }
}
