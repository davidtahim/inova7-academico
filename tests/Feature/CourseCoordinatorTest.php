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

    public function test_new_coordinator_keeps_the_previous_one_as_assistant(): void
    {
        $ianelli = User::create(['name' => 'Ianelli Exemplo', 'email' => 'ianelli@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $si = Course::create(['code' => 'SI', 'name' => 'Sistemas de Informação', 'coordinator_user_id' => $this->admin->id]);

        // Marcar Ianelli como coordenadora no usuário dela: David vira assistente.
        $this->actingAs($this->admin)->put(route('admin.users.update', $ianelli), $this->payload($ianelli, [
            'coordinated_submitted' => 1, 'coordinated_course_ids' => [$si->id],
        ]))->assertRedirect();
        $this->assertSame($ianelli->id, $si->fresh()->coordinator_user_id);
        $this->assertSame([$this->admin->id], $si->assistants()->pluck('users.id')->all());

        // E pelo cadastro do curso: volta David como coordenador e Ianelli fica como assistente.
        $this->actingAs($this->admin)->put(route('admin.courses.update', $si), [
            'code' => 'SI', 'name' => 'Sistemas de Informação', 'coordinator_user_id' => $this->admin->id, 'assistant_ids' => [$this->admin->id], 'active' => 1,
        ])->assertRedirect();
        $si->refresh();
        $this->assertSame($this->admin->id, $si->coordinator_user_id);
        $this->assertSame([$ianelli->id], $si->assistants()->pluck('users.id')->all());

        $this->actingAs($this->admin)->get(route('admin.courses.index'))->assertOk()
            ->assertSee('Coordenação: David Tahim Alves Brito')->assertSee('Assistente: Ianelli Exemplo');
    }

    public function test_assistant_courses_from_the_user_page(): void
    {
        $staff = User::create(['name' => 'Secretaria', 'email' => 's@x.local', 'password' => 'senha1234', 'role' => 'staff', 'is_active' => true]);
        $ads = Course::create(['code' => 'ADS', 'name' => 'Análise e Desenvolvimento de Sistemas', 'coordinator_user_id' => $this->admin->id]);

        $this->actingAs($this->admin)->get(route('admin.users.edit', $staff))->assertOk()->assertSee('Cursos em que é assistente');
        $this->actingAs($this->admin)->put(route('admin.users.update', $staff), $this->payload($staff, [
            'assisted_submitted' => 1, 'assisted_course_ids' => [$ads->id],
        ]))->assertRedirect();
        $this->assertSame([$ads->id], $staff->assistedCourses()->pluck('courses.id')->all());
        $this->assertSame($this->admin->id, $ads->fresh()->coordinator_user_id);
    }

    public function test_migration_turns_ianelli_into_assistant_and_restores_david(): void
    {
        $ianelli = User::create(['name' => 'Ianelli Teste', 'email' => 'ianelli@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $si = Course::create(['code' => 'SI', 'name' => 'Sistemas de Informação', 'coordinator_user_id' => $ianelli->id]);
        $other = Course::create(['code' => 'X', 'name' => 'Outro', 'coordinator_user_id' => $ianelli->id]);

        (require database_path('migrations/2026_11_28_160000_create_course_assistants.php'))->up();

        $this->assertSame($this->admin->id, $si->fresh()->coordinator_user_id);
        $this->assertSame([$ianelli->id], $si->assistants()->pluck('users.id')->all());
        $this->assertSame($ianelli->id, $other->fresh()->coordinator_user_id);
    }
}
