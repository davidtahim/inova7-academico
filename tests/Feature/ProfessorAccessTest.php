<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acesso à disponibilidade (CCG-FOR-02) e à habilitação docente por perfil.
 */
class ProfessorAccessTest extends TestCase
{
    use RefreshDatabase;

    private Professor $professor;

    private Professor $otherProfessor;

    protected function setUp(): void
    {
        parent::setUp();

        AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);

        $this->professor = Professor::create(['name' => 'Prof. Ana', 'email' => 'ana@prof.local', 'active' => true]);
        $this->otherProfessor = Professor::create(['name' => 'Prof. Bruno', 'email' => 'bruno@prof.local', 'active' => true]);
    }

    private function userWithRole(string $role, ?Professor $professor = null): User
    {
        return User::create([
            'name' => "Usuário {$role}",
            'email' => uniqid($role) . '@inova7.local',
            'password' => 'senha1234',
            'role' => $role,
            'is_active' => true,
            'professor_id' => $professor?->id,
        ]);
    }

    private function availabilityPayload(): array
    {
        return ['availability_slots' => [
            ['weekday' => 1, 'starts_at' => '07:30', 'ends_at' => '08:20', 'preference' => 'preferred'],
            ['weekday' => 2, 'starts_at' => '18:30', 'ends_at' => '19:20', 'preference' => 'available'],
        ]];
    }

    public function test_teacher_can_view_and_edit_own_availability(): void
    {
        $teacher = $this->userWithRole('teacher', $this->professor);

        $this->actingAs($teacher)
            ->get(route('admin.professors.availability', $this->professor))
            ->assertOk()
            ->assertViewHas('isEditable', true);

        $this->actingAs($teacher)
            ->putJson(route('admin.professors.update-availability', $this->professor), $this->availabilityPayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('professor_availabilities', 2);
        $this->assertDatabaseHas('professor_availabilities', [
            'professor_id' => $this->professor->id,
            'weekday' => 1,
            'preference' => 'preferred',
        ]);
    }

    public function test_saved_states_are_rendered_in_the_grid_and_legend(): void
    {
        $teacher = $this->userWithRole('teacher', $this->professor);
        $this->actingAs($teacher)
            ->putJson(route('admin.professors.update-availability', $this->professor), $this->availabilityPayload());

        $this->actingAs($teacher)
            ->get(route('admin.professors.availability', $this->professor))
            ->assertViewHas('savedAvailabilities', fn ($saved) => ($saved[1]['07:30 - 08:20'] ?? null) === 'preferred'
                && ($saved[2]['18:30 - 19:20'] ?? null) === 'available')
            ->assertViewHas('preferredCount', 1)
            ->assertViewHas('availableCount', 1)
            ->assertViewHas('unavailableCount', 88);
    }

    public function test_profile_edit_shows_the_same_grid_with_saved_states(): void
    {
        $teacher = $this->userWithRole('teacher', $this->professor);
        $this->actingAs($teacher)
            ->putJson(route('admin.professors.update-availability', $this->professor), $this->availabilityPayload());

        $this->actingAs($teacher)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('id="disponibilidade"', false)
            ->assertViewHas('isEditable', true)
            ->assertViewHas('savedAvailabilities', fn ($saved) => ($saved[1]['07:30 - 08:20'] ?? null) === 'preferred')
            ->assertViewHas('preferredCount', 1)
            ->assertViewHas('availableCount', 1)
            ->assertViewHas('unavailableCount', 88)
            ->assertSee(route('admin.professors.update-availability', $this->professor), false);
    }

    public function test_teacher_cannot_access_another_professor(): void
    {
        $teacher = $this->userWithRole('teacher', $this->professor);

        $this->actingAs($teacher)->get(route('admin.professors.availability', $this->otherProfessor))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.professors.subjects', $this->otherProfessor))->assertForbidden();
        $this->actingAs($teacher)
            ->putJson(route('admin.professors.update-availability', $this->otherProfessor), $this->availabilityPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('professor_availabilities', 0);
    }

    public function test_teacher_without_linked_professor_is_denied(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->get(route('admin.professors.availability', $this->professor))->assertForbidden();
    }

    public function test_teacher_can_select_own_subjects(): void
    {
        $teacher = $this->userWithRole('teacher', $this->professor);
        $subject = Subject::create(['code' => 'SI101', 'name' => 'Algoritmos']);

        $this->actingAs($teacher)
            ->get(route('admin.professors.subjects', $this->professor))
            ->assertOk()
            ->assertViewHas('isEditable', true);

        $this->actingAs($teacher)
            ->put(route('admin.professors.update-subjects', $this->professor), ['subject_ids' => [$subject->id]])
            ->assertRedirect(route('teacher.subjects'));

        $this->assertDatabaseHas('professor_subject', ['professor_id' => $this->professor->id, 'subject_id' => $subject->id]);
    }

    public function test_coordinator_and_admin_can_edit_any_professor(): void
    {
        foreach (['coordinator', 'admin'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('admin.professors.availability', $this->otherProfessor))
                ->assertOk()
                ->assertViewHas('isEditable', true);

            $this->actingAs($user)
                ->putJson(route('admin.professors.update-availability', $this->otherProfessor), $this->availabilityPayload())
                ->assertOk();
        }
    }

    public function test_staff_can_view_but_not_edit(): void
    {
        $staff = $this->userWithRole('staff');
        $subject = Subject::create(['code' => 'SI102', 'name' => 'Banco de Dados']);

        $this->actingAs($staff)
            ->get(route('admin.professors.availability', $this->professor))
            ->assertOk()
            ->assertViewHas('isEditable', false);

        $this->actingAs($staff)
            ->get(route('admin.professors.subjects', $this->professor))
            ->assertOk()
            ->assertViewHas('isEditable', false);

        $this->actingAs($staff)
            ->putJson(route('admin.professors.update-availability', $this->professor), $this->availabilityPayload())
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('admin.professors.update-subjects', $this->professor), ['subject_ids' => [$subject->id]])
            ->assertForbidden();

        $this->assertDatabaseCount('professor_availabilities', 0);
        $this->assertDatabaseCount('professor_subject', 0);
    }

    public function test_student_and_guest_are_denied(): void
    {
        $student = $this->userWithRole('student');

        $this->actingAs($student)->get(route('admin.professors.availability', $this->professor))->assertForbidden();
        $this->actingAs($student)->get(route('admin.professors.subjects', $this->professor))->assertForbidden();

        auth()->logout();
        $this->get(route('admin.professors.availability', $this->professor))->assertRedirect(route('login'));
    }
}
