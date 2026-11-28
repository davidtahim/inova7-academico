<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Professor;
use App\Models\Room;
use App\Models\StudentSubjectStatus;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentPlanner;
use App\Services\ScheduleAllocator;
use App\Services\StudentHistoryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enturmação dos alunos da matriz antiga (SI 2019.1) a partir do histórico. Dados fictícios.
 */
class StudentEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private CurriculumMatrix $matrix;

    private array $subjects = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $course = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $this->matrix = CurriculumMatrix::create(['course_id' => $course->id, 'code' => 'SI-2019.1', 'name' => 'Sistemas de Informação 2019.1', 'status' => 'Ativa']);

        foreach ([['UNI7T013', 'ENGENHARIA DE SOFTWARE', 5], ['UNI7T248', 'ANÁLISE E PROJETO DE SISTEMAS I', 6], ['UNI7T037', 'TRABALHO DE CONCLUSÃO DE CURSO II', 8], ['UNI7T001', 'ALGORITMOS I', 2]] as [$code, $name, $period]) {
            $subject = Subject::create(['code' => $code, 'name' => $name]);
            CurriculumMatrixDiscipline::create(['curriculum_matrix_id' => $this->matrix->id, 'subject_id' => $subject->id, 'period' => $period, 'shift' => 'NOITE', 'weekly_hours' => 2]);
            $this->subjects[$code] = $subject;
        }
    }

    private function csvRows(): array
    {
        $row = fn ($mat, $nome, $turno, $codigo, $disc, $situacao, $periodoLetivo = '', $periodoMatriz = '', $resultado = '') => [
            'matricula' => $mat, 'nome' => $nome, 'curriculo' => 'GSI2019.1', 'turno' => $turno,
            'periodo_letivo' => $periodoLetivo, 'periodo_matriz' => $periodoMatriz, 'codigo' => $codigo,
            'disciplina' => $disc, 'carga_horaria' => '80', 'situacao' => $situacao, 'resultado' => $resultado,
        ];

        return [
            $row('90000001', 'ALUNA FICTICIA UM', 'NOITE', 'UNI7T001', 'ALGORITMOS I', 'aprovada', '20231', '', '8,50 80 APROVADO POR MÉDIA'),
            $row('90000001', 'ALUNA FICTICIA UM', 'NOITE', 'UNI7T013', 'ENGENHARIA DE SOFTWARE', 'reprovada', '20241'),
            $row('90000001', 'ALUNA FICTICIA UM', 'NOITE', 'UNI7T013', 'ENGENHARIA DE SOFTWARE', 'aprovada', '20251', '', '7,00 80 APROVADO POR MÉDIA'),
            $row('90000001', 'ALUNA FICTICIA UM', 'NOITE', 'UNI7T248', 'ANÁLISE E PROJETO DE SISTEMAS I', 'falta_cursar', '', '6'),
            $row('90000001', 'ALUNA FICTICIA UM', 'NOITE', 'UNI7T037', 'TRABALHO DE CONCLUSÃO DE CURSO II', 'falta_cursar', '', '8'),
            $row('90000002', 'ALUNO FICTICIO DOIS', 'MANHÃ', 'UNI7T013', 'ENGENHARIA DE SOFTWARE', 'reprovada', '20242'),
            $row('90000002', 'ALUNO FICTICIO DOIS', 'MANHÃ', 'UNI7T037', 'TRABALHO DE CONCLUSÃO DE CURSO II', 'matriculada', '20252'),
            $row('90000002', 'ALUNO FICTICIO DOIS', 'MANHÃ', 'UNI7T001', 'ALGORITMOS I', 'trancada', '20231'),
        ];
    }

    private function import(): void
    {
        app(StudentHistoryImporter::class)->import($this->csvRows(), 'teste');
    }

    private function offering(string $code, string $classCode, array $extra = []): ClassOffering
    {
        $subject = $this->subjects[$code];

        return ClassOffering::create(array_merge([
            'academic_term_id' => $this->term->id, 'course_id' => $this->matrix->course_id,
            'curriculum_matrix_id' => $this->matrix->id,
            'curriculum_matrix_discipline_id' => CurriculumMatrixDiscipline::where('subject_id', $subject->id)->value('id'),
            'subject_id' => $subject->id, 'class_code' => $classCode, 'period' => 5, 'shift' => 'NOITE', 'weekly_hours' => 2,
        ], $extra));
    }

    public function test_history_import_keeps_best_status_and_links_matrix(): void
    {
        $this->import();

        $one = User::where('registration_number', '90000001')->firstOrFail();
        $this->assertSame('student', $one->role);
        $this->assertSame('Aluna Ficticia Um', $one->name);
        $this->assertSame('90000001@aluno.uni7.edu.br', $one->email);
        $this->assertSame($this->matrix->id, $one->curriculum_matrix_id);
        $this->assertSame('NOITE', $one->shift);
        $this->assertTrue($one->must_change_password);

        // Reprovada e depois aprovada → aprovada.
        $this->assertSame('aprovada', StudentSubjectStatus::where('user_id', $one->id)->where('subject_id', $this->subjects['UNI7T013']->id)->value('status'));
        $this->assertSame(8.5, (float) StudentSubjectStatus::where('user_id', $one->id)->where('subject_id', $this->subjects['UNI7T001']->id)->value('grade'));
        $this->assertSame(4, StudentSubjectStatus::where('user_id', $one->id)->count());

        // Reimportar não duplica.
        $this->import();
        $this->assertSame(2, User::where('role', 'student')->count());
        $this->assertSame(7, StudentSubjectStatus::count());
    }

    public function test_plan_enrolls_pending_subjects_and_sets_directed_study(): void
    {
        $this->import();
        $es = $this->offering('UNI7T013', 'CSE0280105NNB');
        $tcc = $this->offering('UNI7T037', 'CSE0280108NNB');

        $report = app(EnrollmentPlanner::class)->plan($this->term, $this->matrix);

        // Aluna 1: APS I (sem turma) + TCC II. Aluno 2: ES (reprovada) + Algoritmos I (trancada, sem turma); TCC II "matriculada" fica de fora.
        $this->assertSame(2, $report['enrolled']);
        $this->assertSame(['ANÁLISE E PROJETO DE SISTEMAS I' => 1, 'ALGORITMOS I' => 1], $report['without_offering']);
        $this->assertSame(1, $es->enrollments()->count());
        $this->assertSame(1, $tcc->enrollments()->count());

        $es->refresh();
        $this->assertSame(1, (int) $es->student_count);
        $this->assertTrue($es->directed_study);
        $this->assertEquals(1, $es->weekly_hours);

        // Incluindo as "matriculadas" sem resultado, o aluno 2 também vai para TCC II.
        app(EnrollmentPlanner::class)->plan($this->term, $this->matrix, includeInProgress: true);
        $this->assertSame(2, $tcc->enrollments()->count());

        // Refazer sem elas remove a sugestão (idempotente), mas mantém enturmação confirmada.
        ClassEnrollment::where('class_offering_id', $es->id)->update(['status' => 'confirmed']);
        app(EnrollmentPlanner::class)->plan($this->term, $this->matrix);
        $this->assertSame(1, $tcc->fresh()->enrollments()->count());
        $this->assertSame(1, $es->enrollments()->count());
    }

    public function test_open_directed_study_class_for_subject_without_offering(): void
    {
        $this->import();
        $planner = app(EnrollmentPlanner::class);

        $offering = $planner->openDirectedStudyClass($this->term, $this->matrix, $this->subjects['UNI7T248']);
        $this->assertSame('CSE0280106NNB', $offering->class_code);
        $this->assertTrue($offering->directed_study);
        $this->assertSame(6, (int) $offering->period);

        $report = $planner->plan($this->term, $this->matrix);
        $this->assertArrayNotHasKey('ANÁLISE E PROJETO DE SISTEMAS I', $report['without_offering']);
        $this->assertSame(1, $offering->enrollments()->count());
    }

    public function test_coordinator_screen_and_student_personal_schedule(): void
    {
        $this->import();
        $es = $this->offering('UNI7T013', 'CSE0280105NNB');
        $professor = Professor::create(['name' => 'Prof. Fictício', 'active' => true]);
        Room::create(['code' => 'Sala 45', 'name' => 'Sala 45']);
        app(ScheduleAllocator::class)->apply($es, ['professor_id' => $professor->id, 'weekdays' => [5], 'letters' => ['A', 'B'], 'room' => 'Sala 45']);

        $coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $this->actingAs($coordinator)->get(route('enrollment.index'))->assertOk()->assertSee('ENGENHARIA DE SOFTWARE')->assertSee('Sem turma');
        $this->actingAs($coordinator)->post(route('enrollment.plan'), ['matrix' => $this->matrix->id])->assertRedirect();
        $this->actingAs($coordinator)->post(route('enrollment.open-class'), ['matrix' => $this->matrix->id, 'subject_id' => $this->subjects['UNI7T248']->id])->assertRedirect();
        $this->assertTrue(ClassOffering::where('class_code', 'CSE0280106NNB')->exists());

        $student = User::where('registration_number', '90000002')->firstOrFail();
        $this->term->update(['schedule_published_at' => now()]);
        $this->actingAs($student)->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Meu horário')
            ->assertSee('ENGENHARIA DE SOFTWARE')
            ->assertSee('Prof. Fictício')
            ->assertSee('Sala 45');

        $this->actingAs($student)->get(route('enrollment.index'))->assertForbidden();
    }

    public function test_student_clashes_are_detected(): void
    {
        $this->import();
        $professor = Professor::create(['name' => 'Prof. Fictício', 'active' => true]);
        $allocator = app(ScheduleAllocator::class);
        $es = $this->offering('UNI7T013', 'CSE0280105NNB');
        $alg = $this->offering('UNI7T001', 'CSE0280102NNB', ['period' => 2]);
        $allocator->apply($es, ['professor_id' => $professor->id, 'weekdays' => [3], 'letters' => ['A', 'B']]);
        $allocator->apply($alg, ['weekdays' => [3], 'letters' => ['B']]);

        $planner = app(EnrollmentPlanner::class);
        $planner->plan($this->term, $this->matrix);

        $student = User::where('registration_number', '90000002')->firstOrFail();
        $this->assertSame(['Qua 19:20: ALGORITMOS I × ENGENHARIA DE SOFTWARE'], $planner->studentClashes($student, $this->term));
        $this->assertSame([], $planner->studentClashes(User::where('registration_number', '90000001')->firstOrFail(), $this->term));
    }

    public function test_import_command_reads_csv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'hist');
        $handle = fopen($path, 'w');
        fputcsv($handle, array_keys($this->csvRows()[0]), ';');
        foreach ($this->csvRows() as $row) {
            fputcsv($handle, $row, ';');
        }
        fclose($handle);

        $this->artisan('alunos:importar-historico', ['arquivo' => $path, '--fonte' => 'teste'])
            ->expectsOutputToContain('Alunos: 2')
            ->assertSuccessful();
        @unlink($path);

        $this->assertSame(2, User::where('role', 'student')->whereNotNull('curriculum_matrix_id')->count());
    }
}
