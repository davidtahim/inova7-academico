<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\Professor;
use App\Models\ScheduleSlot;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Painel docente em /perfil: turmas do semestre, checklist e dados funcionais.
 */
class TeacherDashboardTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private Professor $professor;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $this->professor = Professor::create([
            'name' => 'David Tahim Alves Brito',
            'email' => 'david@prof.local',
            'registration' => 'UNI727720',
            'qualification' => 'Mestre',
            'active' => true,
        ]);
        $this->teacher = User::create([
            'name' => 'David Tahim Alves Brito',
            'email' => 'david@prof.local',
            'password' => 'senha1234',
            'role' => 'teacher',
            'is_active' => true,
            'professor_id' => $this->professor->id,
        ]);
    }

    private function offering(string $classCode, Subject $subject, array $extra = []): ClassOffering
    {
        $course = Course::firstOrCreate(['code' => 'GRA-PPC-0228-E'], ['name' => 'Sistemas de Informação']);

        return ClassOffering::create(array_merge([
            'academic_term_id' => $this->term->id,
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'class_code' => $classCode,
            'period' => 3,
            'shift' => 'NOITE',
            'weekly_hours' => 2,
            'student_count' => 40,
        ], $extra));
    }

    public function test_teacher_lands_on_dashboard_after_login(): void
    {
        $this->post('/login', ['email' => 'david@prof.local', 'password' => 'senha1234'])
            ->assertRedirect(route('profile'));
    }

    public function test_dashboard_lists_semester_classes_with_kpis_and_schedule(): void
    {
        $bd = Subject::create(['code' => 'SI201', 'name' => 'Banco de Dados']);
        $redes = Subject::create(['code' => 'SI301', 'name' => 'Redes de Computadores']);
        $this->professor->subjects()->sync([$bd->id]);

        $o1 = $this->offering('SI0301N', $bd);
        $o2 = $this->offering('SI0302N', $redes, ['student_count' => 35]);
        TeachingAssignment::create(['class_offering_id' => $o1->id, 'professor_id' => $this->professor->id, 'weekly_hours' => 2, 'status' => 'confirmed']);
        TeachingAssignment::create(['class_offering_id' => $o2->id, 'professor_id' => $this->professor->id, 'weekly_hours' => 2, 'status' => 'planned']);
        ScheduleSlot::create(['class_offering_id' => $o1->id, 'professor_id' => $this->professor->id, 'weekday' => 1, 'starts_at' => '18:30', 'ends_at' => '19:20', 'room' => 'Lab 2']);

        // Turma de outro semestre não aparece.
        $old = AcademicTerm::create(['code' => '2026.2', 'status' => 'closed']);
        $o3 = $this->offering('ANTIGA', $bd, ['academic_term_id' => $old->id]);
        TeachingAssignment::create(['class_offering_id' => $o3->id, 'professor_id' => $this->professor->id, 'status' => 'confirmed']);

        // Antes de publicar, o professor vê as turmas, mas o horário fica "em elaboração".
        $this->actingAs($this->teacher)->withSession(['selected_academic_term_id' => $this->term->id])
            ->get(route('profile'))->assertSee('Em elaboração')->assertDontSee('Seg 18:30–19:20');
        $this->term->update(['schedule_published_at' => now()]);

        $this->actingAs($this->teacher)
            ->withSession(['selected_academic_term_id' => $this->term->id])
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Minhas turmas em 2027.1')
            ->assertSee('SI0301N')
            ->assertSee('Seg 18:30–19:20 · Lab 2')
            ->assertSee('Confirmada')
            ->assertSee('Sugerida')
            ->assertSee('Fora da habilitação')
            ->assertDontSee('ANTIGA')
            ->assertViewHas('stats', ['classes' => 2, 'weekly_hours' => 4.0, 'students' => 75, 'confirmed' => 1]);
    }

    public function test_professional_data_row_and_missing_fields_are_shown(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('profile'))
            ->assertOk()
            ->assertSeeInOrder(['Nome', 'Matrícula', 'Chapa', 'Formação', 'Status', 'Ações'])
            ->assertSee('UNI727720')
            ->assertSee('Mestre')
            ->assertSee('Complete seu cadastro: falta Chapa.')
            ->assertViewHas('missingFields', ['Chapa'])
            ->assertSee('Nenhuma turma alocada para 2027.1 ainda.');
    }

    public function test_teacher_updates_professional_data(): void
    {
        $this->actingAs($this->teacher)
            ->put(route('profile.professional.update'), [
                'name' => 'David Tahim Alves Brito',
                'registration' => 'UNI727720',
                'chapa' => '004521',
                'qualification' => 'Doutor',
                'lattes' => 'http://lattes.cnpq.br/123',
                'active' => '0', // status não é editável pelo professor
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success');

        $this->professor->refresh();
        $this->assertSame('004521', $this->professor->chapa);
        $this->assertSame('Doutor', $this->professor->qualification);
        $this->assertSame('http://lattes.cnpq.br/123', $this->professor->lattes);
        $this->assertTrue($this->professor->active);
        $this->assertSame('UNI727720', $this->teacher->refresh()->registration_number);
    }

    public function test_validation_keeps_form_open_and_rejects_duplicate_registration(): void
    {
        Professor::create(['name' => 'Outro', 'registration' => 'UNI000001', 'active' => true]);

        $this->actingAs($this->teacher)
            ->from(route('profile'))
            ->put(route('profile.professional.update'), [
                'name' => 'David',
                'registration' => 'UNI000001',
                'chapa' => '',
                'qualification' => 'Importado',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrorsIn('professional', ['registration', 'chapa', 'qualification']);

        $this->assertSame('UNI727720', $this->professor->refresh()->registration);
    }

    public function test_only_teachers_can_update_professional_data(): void
    {
        $coordinator = User::create(['name' => 'C', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);

        $this->actingAs($coordinator)
            ->put(route('profile.professional.update'), ['name' => 'X', 'registration' => 'Y', 'chapa' => 'Z', 'qualification' => 'Mestre'])
            ->assertForbidden();
    }

    public function test_legacy_account_is_linked_by_email_without_duplicating_professor(): void
    {
        // Conta antiga, sem professor_id: substitui o usuário vinculado do setUp.
        $this->teacher->delete();
        $legacy = User::create([
            'name' => 'David Tahim Alves Brito',
            'email' => 'david@prof.local',
            'password' => 'senha1234',
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->actingAs($legacy)->get(route('profile'))->assertOk()->assertSee('UNI727720');

        $this->assertSame($this->professor->id, $legacy->refresh()->professor_id);
        $this->assertSame(1, Professor::count());
    }

    public function test_teacher_without_professor_record_sees_guidance(): void
    {
        $orphan = User::create(['name' => 'Sem vínculo', 'email' => 'semvinculo@prof.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true]);

        $this->actingAs($orphan)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('ainda não está vinculado a um cadastro docente');
    }
}
