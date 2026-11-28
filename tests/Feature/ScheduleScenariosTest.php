<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Room;
use App\Models\ScheduleScenario;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleAllocator;
use App\Services\ScheduleDiagnostics;
use App\Services\ScheduleScenarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Etapa 5: versões da grade (RF07), mapa de disputa e ajuda integrada.
 */
class ScheduleScenariosTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private Course $course;

    private CurriculumMatrix $matrix;

    private Professor $ana;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        session(['selected_academic_term_id' => $this->term->id]);
        $this->course = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $this->matrix = CurriculumMatrix::create(['course_id' => $this->course->id, 'code' => 'GRA-MAT-0228-F', 'name' => 'Matriz 15', 'status' => 'Atual']);
        $this->ana = Professor::create(['name' => 'Ana Lima', 'registration' => 'UNI001', 'active' => true]);
        Room::create(['code' => 'Sala 40', 'name' => 'Sala 40']);
        $this->coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
    }

    private function offering(string $classCode, string $name, int $classes = 2): ClassOffering
    {
        $subject = Subject::firstOrCreate(['name' => $name], ['code' => 'S' . substr(md5($name), 0, 6)]);
        $link = CurriculumMatrixDiscipline::firstOrCreate(
            ['curriculum_matrix_id' => $this->matrix->id, 'subject_id' => $subject->id],
            ['period' => 1, 'shift' => 'NOITE', 'weekly_hours' => $classes]
        );

        return ClassOffering::create([
            'academic_term_id' => $this->term->id, 'course_id' => $this->course->id, 'curriculum_matrix_id' => $this->matrix->id,
            'curriculum_matrix_discipline_id' => $link->id, 'subject_id' => $subject->id, 'class_code' => $classCode,
            'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => $classes,
        ]);
    }

    private function placements(ClassOffering $o): array
    {
        return app(ScheduleAllocator::class)->currentInput($o->fresh(['slots', 'assignments']))['placements'];
    }

    public function test_save_change_and_restore_with_automatic_backup(): void
    {
        $lp = $this->offering('CSE0280101NNA', 'Lógica de Programação');
        app(ScheduleAllocator::class)->apply($lp, ['professor_id' => $this->ana->id, 'placements' => [1 => ['A', 'B']], 'room' => 'Sala 40'], 'confirmed');

        $this->actingAs($this->coordinator)->post(route('allocation.scenarios.store'), ['name' => 'Proposta 1'])
            ->assertRedirect(route('allocation.scenarios'));
        $saved = ScheduleScenario::firstOrFail();
        $this->assertSame(2, $saved->classes_count);
        $this->assertSame('manual', $saved->kind);

        // Muda a grade e restaura.
        app(ScheduleAllocator::class)->apply($lp->fresh(), ['placements' => [3 => ['C']]], 'planned');
        $this->actingAs($this->coordinator)->post(route('allocation.scenarios.restore', $saved))
            ->assertRedirect(route('allocation.scenarios'))->assertSessionHas('success');

        $this->assertSame([1 => ['A', 'B']], $this->placements($lp));
        $this->assertSame('confirmed', $lp->assignments()->first()->status, 'O professor volta confirmado');
        $backup = ScheduleScenario::where('kind', 'auto')->firstOrFail();
        $this->assertSame('Antes de restaurar "Proposta 1"', $backup->name);
        $this->assertSame([3 => ['C']], $backup->payload['items'][0]['placements']);
    }

    public function test_export_and_import_match_by_codes_not_database_ids(): void
    {
        $lp = $this->offering('CSE0280101NNA', 'Lógica de Programação');
        app(ScheduleAllocator::class)->apply($lp, ['professor_id' => $this->ana->id, 'placements' => [2 => ['A', 'B']], 'room' => 'Sala 40'], 'planned');

        $download = $this->actingAs($this->coordinator)->get(route('allocation.scenarios.export'))->assertOk();
        $this->assertStringContainsString('grade-20271-atual', $download->headers->get('content-disposition'));
        $file = json_decode($download->streamedContent(), true);
        $this->assertSame('inova7-grade', $file['format']);
        $this->assertSame('UNI001', $file['items'][0]['professor']['registration']);
        $this->assertArrayNotHasKey('id', $file['items'][0]);

        // Outro ambiente: a mesma professora tem outro id; uma turma do arquivo não existe aqui.
        app(ScheduleAllocator::class)->apply($lp->fresh(), ['placements' => []], 'planned');
        $this->ana->delete();
        $ana2 = Professor::create(['name' => 'Ana Lima', 'registration' => 'UNI001', 'active' => true]);
        $file['items'][] = ['class' => 'CSE9999999NNA', 'subject_code' => 'X', 'subject' => 'Inexistente', 'placements' => ['1' => ['A']]];
        $upload = UploadedFile::fake()->createWithContent('grade.json', json_encode($file));

        $this->actingAs($this->coordinator)->post(route('allocation.scenarios.import'), ['file' => $upload])
            ->assertRedirect(route('allocation.scenarios'));
        $imported = ScheduleScenario::where('kind', 'import')->firstOrFail();
        $this->assertSame('grade', $imported->name);
        $this->assertSame([], $this->placements($lp), 'Importar não altera a grade');

        $this->actingAs($this->coordinator)->post(route('allocation.scenarios.restore', $imported))
            ->assertSessionHas('warning')
            ->assertSessionHas('restore_problems', fn ($p) => str_contains($p[0], 'CSE9999999NNA'));
        $this->assertSame([2 => ['A', 'B']], $this->placements($lp));
        $this->assertSame($ana2->id, $lp->assignments()->first()->professor_id);
    }

    public function test_invalid_file_is_refused_without_creating_a_version(): void
    {
        $upload = UploadedFile::fake()->createWithContent('planilha.json', '{"foo": 1}');
        $this->actingAs($this->coordinator)->from(route('allocation.scenarios'))
            ->post(route('allocation.scenarios.import'), ['file' => $upload])
            ->assertRedirect(route('allocation.scenarios'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'não é uma versão de grade'));
        $this->assertSame(0, ScheduleScenario::count());
    }

    public function test_solver_apply_keeps_an_automatic_backup_and_prunes_old_ones(): void
    {
        $lp = $this->offering('CSE0280101NNA', 'Lógica de Programação');
        foreach (range(1, ScheduleScenarios::KEEP_AUTO + 2) as $i) {
            $this->actingAs($this->coordinator)->postJson(route('allocation.solver.apply'), ['solution' => [
                ['id' => $lp->id, 'professor_id' => $this->ana->id, 'room' => 'Sala 40', 'placements' => [(string) (($i % 6) + 1) => ['A', 'B']]],
            ]])->assertOk();
        }

        $this->assertSame(ScheduleScenarios::KEEP_AUTO, ScheduleScenario::where('kind', 'auto')->count());
        $this->assertSame('Antes de aplicar o resolvedor', ScheduleScenario::latest('id')->first()->name);
    }

    public function test_versions_are_for_planners_and_scoped_to_the_selected_term(): void
    {
        $other = AcademicTerm::create(['code' => '2026.1', 'status' => 'closed']);
        $foreign = ScheduleScenario::create(['academic_term_id' => $other->id, 'name' => 'Outro', 'payload' => ['format' => 'inova7-grade', 'items' => []]]);
        $teacher = User::create(['name' => 'Prof', 'email' => 'p@x.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true]);

        $this->actingAs($teacher)->get(route('allocation.scenarios'))->assertForbidden();
        $this->actingAs($this->coordinator)->get(route('allocation.scenarios'))->assertOk()->assertSee('Versões da grade — 2027.1')->assertDontSee('Outro</div>', false);
        $this->actingAs($this->coordinator)->post(route('allocation.scenarios.restore', $foreign))->assertNotFound();
    }

    public function test_contention_map_compares_free_professors_with_classes(): void
    {
        $bruno = Professor::create(['name' => 'Bruno', 'active' => true]);
        $a = $this->offering('CSE0280101NNA', 'Lógica de Programação');
        $b = $this->offering('CSE0280103NNA', 'Banco de Dados');
        $this->ana->subjects()->attach($a->subject_id);
        $bruno->subjects()->attach($b->subject_id);
        // Ana livre só na segunda A; Bruno livre na segunda A e B.
        foreach ([[$this->ana, '1', '18:30'], [$bruno, '1', '18:30'], [$bruno, '1', '19:20']] as [$p, $day, $start]) {
            ProfessorAvailability::create(['academic_term_id' => $this->term->id, 'professor_id' => $p->id, 'weekday' => $day, 'starts_at' => $start, 'ends_at' => '00:00', 'preference' => 'preferred']);
        }

        $map = app(ScheduleDiagnostics::class)->diagnose($this->term)['contention']['NOITE'];

        $this->assertSame(2, $map['turmas']);
        $this->assertSame('tight', $map['cells']['1|18:30']['level'], '2 livres para 2 turmas: sem folga');
        $this->assertSame(2, $map['cells']['1|18:30']['supply']);
        $this->assertSame('short', $map['cells']['1|19:20']['level']);
        $this->assertSame(['Bruno'], $map['cells']['1|19:20']['names']);
        $this->assertSame('none', $map['cells']['2|18:30']['level']);

        $this->actingAs($this->coordinator)->get(route('allocation.diagnosis'))->assertOk()->assertSee('Mapa de disputa por horário');
    }

    public function test_board_offers_help_and_versions(): void
    {
        $this->actingAs($this->coordinator)->get(route('allocation.board'))->assertOk()
            ->assertSee('Como usar a grade global')
            ->assertSee('Janelas vagas de professores')
            ->assertSee(route('allocation.scenarios'));
    }
}
