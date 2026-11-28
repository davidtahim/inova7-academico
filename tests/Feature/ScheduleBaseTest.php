<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Professor;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleAllocator;
use Database\Seeders\CargaHorariaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Etapa 1 da montagem automática: carga horária (Oferta Ubíqua), aulas a distribuir,
 * limites do professor, sigla e publicação da grade.
 */
class ScheduleBaseTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private CurriculumMatrix $matrix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $course = Course::create(['code' => 'GRA-PPC-0240-G', 'name' => 'Análise e Desenvolvimento de Sistemas']);
        $this->matrix = CurriculumMatrix::create(['course_id' => $course->id, 'code' => 'GRA-MAT-2140-H', 'name' => 'Matriz 56', 'status' => 'Atual']);
    }

    private function offering(string $name, int $weekly = 2, array $extra = []): ClassOffering
    {
        $subject = Subject::create(['code' => 'X' . substr(md5($name), 0, 6), 'name' => $name]);
        $link = CurriculumMatrixDiscipline::create(['curriculum_matrix_id' => $this->matrix->id, 'subject_id' => $subject->id, 'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => $weekly]);

        return ClassOffering::create(array_merge([
            'academic_term_id' => $this->term->id, 'course_id' => $this->matrix->course_id,
            'curriculum_matrix_id' => $this->matrix->id, 'curriculum_matrix_discipline_id' => $link->id,
            'subject_id' => $subject->id, 'class_code' => 'CSE0400101NNA', 'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => 2,
        ], $extra));
    }

    private function user(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => uniqid($role) . '@x.local', 'password' => 'senha1234', 'role' => $role, 'is_active' => true]);
    }

    public function test_workload_seeder_applies_ha_classis_and_modality(): void
    {
        $es = $this->offering('ENGENHARIA DE SOFTWARE E GESTÃO DE TIMES ÁGEIS');
        $mat = $this->offering('MATEMÁTICA APLICADA');
        $dps = $this->offering('DESENVOLVIMENTO PESSOAL E TRABALHABILIDADE');

        $this->seed(CargaHorariaSeeder::class);

        // 80h presencial/prática → 4 aulas; 60h híbrida → 2 (regra do RM); DOL → 0 e modalidade a distância.
        $this->assertSame(4, $es->fresh()->classesToSchedule());
        $this->assertSame(80, $es->fresh()->matrixDiscipline->total_hours);
        $this->assertEquals(4, $es->fresh()->weekly_hours);
        $this->assertSame(2, $mat->fresh()->classesToSchedule());
        $this->assertSame('HÍBRIDA', $mat->fresh()->matrixDiscipline->modality);
        $this->assertTrue($dps->fresh()->isDistance());
        $this->assertSame(0, $dps->fresh()->classesToSchedule());
    }

    public function test_hybrid_rule_matches_sheet_for_all_hybrid_subjects(): void
    {
        $handle = fopen(database_path('seeders/data/oferta-ubiqua-2026-2/si-ads.csv'), 'r');
        $header = fgetcsv($handle, 0, ';');
        $rule = [40 => 2, 60 => 2, 80 => 3];
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $row = array_combine($header, $row);
            if (str_starts_with(CargaHorariaSeeder::modality($row['mod_2026_2']), 'HÍBRIDA')) {
                $this->assertSame($rule[(int) $row['ch_total']], (int) $row['ha_classis'], $row['disciplina']);
            }
        }
        fclose($handle);
    }

    public function test_validator_checks_classes_count_and_professor_limits(): void
    {
        $allocator = app(ScheduleAllocator::class);
        $professor = Professor::create(['name' => 'Ana', 'active' => true, 'max_classes_per_day' => 2, 'max_days_per_week' => 1]);
        $first = $this->offering('Banco de Dados', 3);
        $allocator->apply($first, ['professor_id' => $professor->id, 'weekdays' => [1], 'letters' => ['A', 'B']]);

        // 2 aulas distribuídas para carga 3.
        $this->assertStringContainsString('Distribuídas 2 aula(s) na semana; a carga da disciplina é 3', implode(' ', $allocator->validate($first->fresh(), ['professor_id' => $professor->id, 'weekdays' => [1], 'letters' => ['A', 'B']])['warnings']));

        $second = $this->offering('Redes', 2, ['class_code' => 'CSE0400103NNA']);
        $warnings = implode(' ', $allocator->validate($second, ['professor_id' => $professor->id, 'weekdays' => [1, 2], 'letters' => ['C', 'D']])['warnings']);
        $this->assertStringContainsString('Ana ficaria com 4 aulas na segunda (máximo 2)', $warnings);
        $this->assertStringContainsString('aulas em 2 dias (máximo 1)', $warnings);
    }

    public function test_only_coordination_sets_professor_limits_and_subject_abbreviation_is_saved(): void
    {
        $professor = Professor::create(['name' => 'Ana', 'active' => true]);
        $payload = ['name' => 'Ana', 'max_classes_per_day' => 4, 'max_days_per_week' => 3];

        $this->actingAs($this->user('staff'))->put(route('admin.professors.update', $professor), $payload);
        $this->assertNull($professor->fresh()->max_classes_per_day);

        $this->actingAs($this->user('coordinator'))->put(route('admin.professors.update', $professor), $payload)->assertRedirect();
        $this->assertSame(4, (int) $professor->fresh()->max_classes_per_day);
        $this->assertSame(3, (int) $professor->fresh()->max_days_per_week);

        $this->actingAs($this->user('coordinator'))->get(route('admin.subjects.create'))->assertOk()->assertSee('Sigla');
        $this->actingAs($this->user('coordinator'))->post(route('admin.subjects.store'), ['code' => 'UNI7T008', 'name' => 'Banco de Dados II', 'abbreviation' => 'BD2'])->assertRedirect();
        $this->assertSame('BD2', Subject::where('code', 'UNI7T008')->value('abbreviation'));
        $this->assertSame('SO', Subject::suggestAbbreviation('SISTEMAS OPERACIONAIS'));
    }

    public function test_publication_controls_visibility(): void
    {
        $student = $this->user('student');
        $teacher = $this->user('teacher');
        $coordinator = $this->user('coordinator');
        $admin = $this->user('admin');

        $this->actingAs($student)->get(route('schedules.index'))->assertSee('ainda não foram publicados');
        $this->actingAs($coordinator)->get(route('schedules.index'))->assertSee('Rascunho');

        $this->actingAs($student)->post(route('allocation.publish'))->assertForbidden();
        $this->actingAs($teacher)->post(route('allocation.publish'))->assertForbidden();

        $this->actingAs($admin)->post(route('allocation.publish'))->assertRedirect();
        $this->assertSame($admin->id, (int) $this->term->fresh()->schedule_published_by);
        $this->actingAs($student)->get(route('schedules.index'))->assertDontSee('ainda não foram publicados');

        $this->actingAs($coordinator)->post(route('allocation.unpublish'))->assertRedirect();
        $this->assertFalse($this->term->fresh()->isSchedulePublished());
    }
}
