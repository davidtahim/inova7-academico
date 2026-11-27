<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Matriz de permissões centralizada (User::can* + Gates em AppServiceProvider).
 */
class RbacTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return User::create([
            'name' => "Usuário {$role}",
            'email' => uniqid($role) . '@inova7.local',
            'password' => 'senha1234',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function matrix(): CurriculumMatrix
    {
        $course = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);

        return CurriculumMatrix::create([
            'course_id' => $course->id,
            'code' => 'GRA-MAT-0228-F',
            'name' => 'Matriz 15',
            'status' => 'Atual',
        ]);
    }

    private function linkPayload(Subject $subject): array
    {
        return [
            'subject_id' => $subject->id,
            'period' => 1,
            'shift' => 'NOITE',
            'weekly_hours' => 2,
            'offering_type' => 'OBRIGATORIA',
            'required_room_type' => 'LABORATORIO_INFORMATICA',
            'max_consecutive_classes' => 2,
        ];
    }

    public function test_permission_matrix(): void
    {
        $expected = [
            //             users  catalog manage  planning imports audit
            'admin' => [true,  true,   true,   true,    true,   true],
            'coordinator' => [false, true,   true,   true,    false,  true],
            'staff' => [false, true,   false,  false,   true,   true],
            'teacher' => [false, false,  false,  false,   false,  false],
            'student' => [false, false,  false,  false,   false,  false],
        ];
        $abilities = ['manage-users', 'access-catalog', 'manage-catalog', 'manage-planning', 'access-imports', 'access-audit'];

        foreach ($expected as $role => $row) {
            $user = $this->userWithRole($role);
            foreach ($abilities as $i => $ability) {
                $this->assertSame($row[$i], Gate::forUser($user)->allows($ability), "{$role} / {$ability}");
            }
        }
    }

    public function test_coordinator_lands_on_catalog_admin_after_login(): void
    {
        User::create([
            'name' => 'Coordenação SI',
            'email' => 'coord@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $this->post('/login', ['email' => 'coord@inova7.local', 'password' => 'senha1234'])
            ->assertRedirect(route('admin.planejamento.index'));

        $this->get(route('admin.planejamento.index'))->assertOk()->assertDontSee(route('admin.users.index'));
        $this->get(route('admin.matrices.index'))->assertOk();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_coordinator_manages_matrix_discipline_links(): void
    {
        $coordinator = $this->userWithRole('coordinator');
        $matrix = $this->matrix();
        $subject = Subject::create(['code' => 'SI101', 'name' => 'Algoritmos']);

        $this->actingAs($coordinator)->get(route('admin.matrices.disciplines.show', $matrix))->assertOk();

        $this->actingAs($coordinator)
            ->post(route('admin.matrices.disciplines.store', $matrix), $this->linkPayload($subject))
            ->assertRedirect(route('admin.matrices.disciplines.show', $matrix));

        $link = CurriculumMatrixDiscipline::firstOrFail();
        $this->assertSame('LABORATORIO_INFORMATICA', $link->required_room_type);

        $this->actingAs($coordinator)
            ->put(route('admin.matrices.disciplines.update', [$matrix, $link]), [
                'period' => '2',
                'shift' => 'NOITE',
                'weekly_hours' => 3,
                'offering_type' => 'OBRIGATORIA',
                'allows_shared_class' => '1',
            ])
            ->assertRedirect(route('admin.matrices.disciplines.show', $matrix));

        $link->refresh();
        $this->assertSame(2, $link->period);
        $this->assertTrue($link->allows_shared_class);

        $this->actingAs($coordinator)
            ->delete(route('admin.matrices.disciplines.destroy', [$matrix, $link]))
            ->assertRedirect();
        $this->assertDatabaseCount('curriculum_matrix_discipline', 0);
    }

    public function test_link_from_another_matrix_returns_404(): void
    {
        $coordinator = $this->userWithRole('coordinator');
        $matrix = $this->matrix();
        $other = CurriculumMatrix::create(['course_id' => $matrix->course_id, 'code' => 'OUTRA', 'name' => 'Outra']);
        $subject = Subject::create(['code' => 'SI101', 'name' => 'Algoritmos']);
        $link = CurriculumMatrixDiscipline::create(['curriculum_matrix_id' => $other->id, 'subject_id' => $subject->id, 'period' => 1, 'weekly_hours' => 2]);

        $this->actingAs($coordinator)
            ->delete(route('admin.matrices.disciplines.destroy', [$matrix, $link]))
            ->assertNotFound();
    }

    public function test_staff_and_teacher_cannot_manage_matrix_links(): void
    {
        $matrix = $this->matrix();
        $subject = Subject::create(['code' => 'SI101', 'name' => 'Algoritmos']);

        foreach (['staff', 'teacher', 'student'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)
                ->post(route('admin.matrices.disciplines.store', $matrix), $this->linkPayload($subject))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('curriculum_matrix_discipline', 0);
    }

    public function test_staff_can_consult_catalog_but_not_edit_it(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('catalog.courses'))->assertOk();
        $this->actingAs($staff)->get(route('admin.courses.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.courses.store'), ['code' => 'X', 'name' => 'Y'])->assertForbidden();
    }

    public function test_imports_are_limited_to_admin_and_staff(): void
    {
        $this->actingAs($this->userWithRole('staff'))->get(route('imports.ubiqua.index'))->assertOk();
        $this->actingAs($this->userWithRole('coordinator'))->get(route('imports.ubiqua.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('teacher'))->get(route('imports.ubiqua.index'))->assertForbidden();
    }
}
