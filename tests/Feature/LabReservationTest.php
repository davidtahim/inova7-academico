<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\LabReservation;
use App\Models\Professor;
use App\Models\Room;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleAllocator;
use App\Services\SolverProblemBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Grade horária × reservas de laboratório (PED-RGU-18307, Art. 8º III e Art. 16).
 */
class LabReservationTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private CurriculumMatrix $matrix;

    private Course $course;

    private Professor $ana;

    private Room $li41;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning', 'starts_at' => '2027-02-01', 'ends_at' => '2027-06-30']);
        session(['selected_academic_term_id' => $this->term->id]);
        $this->course = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $this->matrix = CurriculumMatrix::create(['course_id' => $this->course->id, 'code' => 'GRA-MAT-0228-F', 'name' => 'Matriz 15', 'status' => 'Atual']);
        $this->ana = Professor::create(['name' => 'Ana', 'active' => true]);
        Room::create(['code' => 'Sala 40', 'name' => 'Sala 40']);
        $this->li41 = Room::create(['code' => 'Li41', 'name' => 'Li41', 'type' => 'LABORATORIO_INFORMATICA', 'computers' => 30, 'softwares' => 'VS Code, Python 3, MySQL Workbench']);
        Room::create(['code' => 'Li52', 'name' => 'Li52', 'type' => 'LABORATORIO_INFORMATICA', 'computers' => 20, 'softwares' => 'VS Code']);
        $this->admin = User::create(['name' => 'NTI', 'email' => 'nti@x.local', 'password' => 'senha1234', 'role' => 'admin', 'is_active' => true]);
    }

    private function offering(string $name, int $practice, ?string $software = null, int $students = 25): ClassOffering
    {
        $subject = Subject::create(['code' => 'S' . substr(md5($name), 0, 6), 'name' => $name, 'practice_hours' => $practice, 'required_software' => $software]);
        $link = CurriculumMatrixDiscipline::create(['curriculum_matrix_id' => $this->matrix->id, 'subject_id' => $subject->id, 'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => 2, 'offering_type' => 'OBRIGATORIA']);

        return ClassOffering::create([
            'academic_term_id' => $this->term->id, 'course_id' => $this->course->id, 'curriculum_matrix_id' => $this->matrix->id,
            'curriculum_matrix_discipline_id' => $link->id, 'subject_id' => $subject->id, 'class_code' => 'T' . substr(md5($name), 0, 4),
            'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => 2, 'student_count' => $students,
        ]);
    }

    private function apply(ClassOffering $o, array $placements, ?string $room): array
    {
        return app(ScheduleAllocator::class)->apply($o->fresh(['subject', 'matrixDiscipline']), ['professor_id' => $this->ana->id, 'placements' => $placements, 'room' => $room], 'planned');
    }

    public function test_practical_subject_needs_an_able_lab_and_theory_stays_out_of_labs(): void
    {
        $lp = $this->offering('Lógica de Programação', 20, 'Python', 25);
        $teoria = $this->offering('Ética', 0);

        $this->assertStringContainsString('precisa de laboratório', $this->apply($lp, [1 => ['A', 'B']], 'Sala 40')['errors'][0]);
        $this->assertStringContainsString('20 computadores para 25 alunos', implode(' ', $this->apply($lp, [1 => ['A', 'B']], 'Li52')['errors']));
        $this->assertStringContainsString('100% teórica', $this->apply($teoria, [2 => ['A', 'B']], 'Li41')['errors'][0]);
        $this->assertTrue($this->apply($teoria, [2 => ['A', 'B']], 'Sala 40')['saved']);

        $noSoftware = $this->offering('Computação Gráfica', 10, 'Blender', 10);
        $this->assertStringContainsString('softwares exigidos: blender', $this->apply($noSoftware, [3 => ['A', 'B']], 'Li41')['errors'][0]);

        // O resolvedor só oferece salas permitidas.
        $problem = collect(app(SolverProblemBuilder::class)->build($this->term)['offerings'])->keyBy('id');
        $this->assertSame(['Li41'], $problem[$lp->id]['rooms']);
        $this->assertSame(['Sala 40'], $problem[$teoria->id]['rooms']);
    }

    public function test_grade_creates_updates_and_cancels_the_semester_reservation(): void
    {
        $lp = $this->offering('Lógica de Programação', 20, 'Python', 25);

        $this->assertTrue($this->apply($lp, [1 => ['A', 'B']], 'Li41')['saved']);
        $r = LabReservation::where('turma_id', $lp->id)->firstOrFail();
        $this->assertSame(['Aula', 'grade', 'Aprovada', 'Semanal', 1, '18:30', '20:10', 25, 'NOITE'],
            [$r->tipo_solicitacao, $r->origem, $r->status, $r->frequencia, $r->dia_semana, $r->start(), $r->end(), $r->participantes, $r->turno]);
        $this->assertTrue($r->recorrente);
        $this->assertSame('2027-02-01', $r->data_inicio->toDateString());
        $this->assertSame($this->li41->id, $r->laboratorio_id);

        // Muda o dia: a reserva de segunda é cancelada e a de quarta criada.
        $this->assertTrue($this->apply($lp, [3 => ['C', 'D']], 'Li41')['saved']);
        $this->assertSame('Cancelada', $r->fresh()->status);
        $this->assertSame(1, LabReservation::where('turma_id', $lp->id)->where('status', 'Aprovada')->where('dia_semana', 3)->count());

        // Tira da grade: libera o laboratório.
        $this->assertTrue($this->apply($lp, [], null)['saved']);
        $this->assertSame(0, LabReservation::where('turma_id', $lp->id)->where('status', 'Aprovada')->count());
    }

    public function test_approved_nti_reservation_blocks_the_class_and_vice_versa(): void
    {
        $lp = $this->offering('Lógica de Programação', 20, 'Python', 25);
        LabReservation::create(['laboratorio_id' => $this->li41->id, 'tipo_solicitacao' => 'Prova', 'origem' => 'manual', 'titulo' => 'Prova de BD',
            'dia_semana' => 1, 'data' => '2027-03-01', 'hora_inicio' => '19:00', 'hora_fim' => '21:00', 'status' => 'Aprovada']); // segunda
        $this->assertStringContainsString('reservado em 01/03/2027', implode(' ', $this->apply($lp, [1 => ['A', 'B']], 'Li41')['errors']));
        $this->assertTrue($this->apply($lp, [2 => ['A', 'B']], 'Li41')['saved']);

        // Pedido avulso em cima da aula regular (terça 18:30) é recusado.
        $this->actingAs($this->admin)->from(route('labs.index'))->post(route('labs.reservations.store'), [
            'laboratorio_id' => $this->li41->id, 'tipo_solicitacao' => 'Evento', 'titulo' => 'Hackathon',
            'data' => '2027-03-02', 'hora_inicio' => '19:00', 'hora_fim' => '20:00', // terça, dentro do semestre
        ])->assertSessionHas('error', fn ($m) => str_contains($m, 'aula regular'));
    }

    public function test_requests_from_teachers_wait_for_nti_and_panel_shows_badges(): void
    {
        $teacher = User::create(['name' => 'Prof', 'email' => 'p@x.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true]);
        $student = User::create(['name' => 'Aluno', 'email' => 'a@x.local', 'password' => 'senha1234', 'role' => 'student', 'is_active' => true]);
        $date = now()->next('Wednesday');

        $this->actingAs($teacher)->post(route('labs.reservations.store'), [
            'laboratorio_id' => $this->li41->id, 'tipo_solicitacao' => 'Avulsa', 'titulo' => 'Aula extra',
            'data' => $date->toDateString(), 'hora_inicio' => '14:00', 'hora_fim' => '15:40', 'participantes' => 20,
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'aguarda aprovação'));
        $request = LabReservation::firstOrFail();
        $this->assertSame('Pendente', $request->status);

        $this->actingAs($teacher)->post(route('labs.reservations.approve', $request))->assertForbidden();
        $this->actingAs($this->admin)->post(route('labs.reservations.approve', $request))->assertSessionHas('success');
        $this->assertSame('Aprovada', $request->fresh()->status);

        // Fora do horário de funcionamento (sábado à tarde) é recusado.
        $this->actingAs($this->admin)->post(route('labs.reservations.store'), [
            'laboratorio_id' => $this->li41->id, 'tipo_solicitacao' => 'Evento', 'titulo' => 'Sábado',
            'data' => now()->next('Saturday')->toDateString(), 'hora_inicio' => '13:00', 'hora_fim' => '15:00',
        ])->assertSessionHas('error', fn ($m) => str_contains($m, 'horário de funcionamento'));

        $this->actingAs($teacher)->get(route('labs.index', ['lab' => $this->li41->id, 'semana' => $date->toDateString()]))->assertOk()
            ->assertSee('Aula regular')->assertSee('Livre')->assertSee('Aula extra');
        $this->actingAs($student)->get(route('labs.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('labs.rooms'))->assertForbidden();
    }

    public function test_matrix_link_suggests_lab_for_practical_subjects(): void
    {
        $coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $subject = Subject::create(['code' => 'PR1', 'name' => 'Programação Web', 'practice_hours' => 40]);

        $this->actingAs($coordinator)->post(route('admin.matrices.disciplines.store', $this->matrix), [
            'subject_id' => $subject->id, 'period' => 2, 'shift' => 'NOITE', 'weekly_hours' => 2, 'offering_type' => 'OBRIGATORIA',
        ]);
        $this->assertSame('LABORATORIO_INFORMATICA', CurriculumMatrixDiscipline::where('subject_id', $subject->id)->value('required_room_type'));
    }

    public function test_lab_managers_decide_only_for_their_area_and_admin_for_all(): void
    {
        $saudeLab = Room::create(['code' => 'LabSaude1', 'name' => 'Anatomia', 'type' => 'LABORATORIO_SAUDE', 'computers' => 40]);
        $gestorTi = User::create(['name' => 'Gestor TI', 'email' => 'gti@x.local', 'password' => 'senha1234', 'role' => 'lab_manager', 'lab_area' => 'ti', 'is_active' => true]);
        $gestorSaude = User::create(['name' => 'Gestora Saúde', 'email' => 'gs@x.local', 'password' => 'senha1234', 'role' => 'lab_manager', 'lab_area' => 'saude', 'is_active' => true]);
        $this->assertSame('Gestor de laboratórios — TI (NTI)', $gestorTi->role_label);

        $pedido = LabReservation::create(['laboratorio_id' => $this->li41->id, 'tipo_solicitacao' => 'Avulsa', 'origem' => 'manual', 'titulo' => 'Oficina',
            'dia_semana' => 2, 'data' => '2027-03-02', 'hora_inicio' => '14:00', 'hora_fim' => '15:00', 'status' => 'Pendente']);
        $this->actingAs($gestorSaude)->post(route('labs.reservations.approve', $pedido))->assertForbidden();
        $this->actingAs($gestorTi)->post(route('labs.reservations.approve', $pedido))->assertSessionHas('success');

        // Reserva criada pela gestora da Saúde no laboratório dela já entra aprovada.
        $this->actingAs($gestorSaude)->post(route('labs.reservations.store'), [
            'laboratorio_id' => $saudeLab->id, 'tipo_solicitacao' => 'Prova', 'titulo' => 'Prova prática',
            'data' => '2027-03-03', 'hora_inicio' => '08:20', 'hora_fim' => '10:00',
        ])->assertRedirect();
        $this->assertSame('Aprovada', LabReservation::where('titulo', 'Prova prática')->value('status'));

        $this->actingAs($gestorSaude)->get(route('labs.index'))->assertOk()->assertSee('Laboratórios — Saúde')->assertSee('LabSaude1');
        $this->actingAs($gestorSaude)->get(route('labs.rooms', ['area' => 'ti']))->assertForbidden();
        $this->actingAs($gestorSaude)->put(route('labs.rooms.update', $this->li41), ['computers' => 1])->assertForbidden();
        $this->actingAs($this->admin)->get(route('labs.rooms', ['area' => 'saude']))->assertOk()->assertSee('LabSaude1');

        // Gestor entra direto no painel da sua área.
        $this->post('/logout');
        $this->post('/login', ['email' => 'gs@x.local', 'password' => 'senha1234'])->assertRedirect(route('labs.index', ['area' => 'saude']));
    }

    public function test_other_areas_follow_the_same_grade_rules(): void
    {
        $saudeLab = Room::create(['code' => 'LabSaude1', 'name' => 'Anatomia', 'type' => 'LABORATORIO_SAUDE', 'computers' => 40]);
        $anatomia = $this->offering('Anatomia', 0);
        $anatomia->matrixDiscipline->update(['required_room_type' => 'LABORATORIO_SAUDE']);
        $etica = $this->offering('Ética', 0);

        $this->assertStringContainsString('exige Laboratório de Saúde', $this->apply($anatomia, [1 => ['A', 'B']], 'Li41')['errors'][0]);
        $this->assertStringContainsString('não exige laboratório de saúde', $this->apply($etica, [1 => ['C', 'D']], 'LabSaude1')['errors'][0]);
        $this->assertTrue($this->apply($anatomia, [1 => ['A', 'B']], 'LabSaude1')['saved']);
        $this->assertSame($saudeLab->id, LabReservation::where('turma_id', $anatomia->id)->value('laboratorio_id'));
    }

    public function test_it_admin_registers_a_lab_manager_with_an_area(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Gestor', 'email' => 'g@x.local', 'role' => 'lab_manager', 'password' => 'senha1234', 'password_confirmation' => 'senha1234',
        ])->assertSessionHasErrors('lab_area');

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Gestor', 'email' => 'g@x.local', 'role' => 'lab_manager', 'lab_area' => 'engenharia', 'password' => 'senha1234', 'password_confirmation' => 'senha1234',
        ]);
        $this->assertSame('engenharia', User::where('email', 'g@x.local')->value('lab_area'));
        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk()->assertSee('Área dos laboratórios');
    }
}
