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
use App\Models\ScheduleSlot;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleAllocator;
use App\Services\TimeBlocks;
use Database\Seeders\CargaPlanilhasSeeder;
use Database\Seeders\Planilha20261Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Motor de alocação (lógica da planilha de oferta 2026.1) e telas de montagem/consulta de horários.
 */
class ScheduleAllocationTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private Course $course;

    private Professor $ana;

    private Professor $bruno;

    private ScheduleAllocator $allocator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->allocator = app(ScheduleAllocator::class);
        $this->term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $this->course = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $this->ana = Professor::create(['name' => 'Ana', 'active' => true]);
        $this->bruno = Professor::create(['name' => 'Bruno', 'active' => true]);
        Room::create(['code' => 'Sala 40', 'name' => 'Sala 40', 'type' => 'SALA_COMUM']);
        Room::create(['code' => 'Li41', 'name' => 'Li41', 'type' => 'LABORATORIO_INFORMATICA']);
    }

    private function offering(string $classCode, string $subjectName, array $extra = [], array $linkExtra = []): ClassOffering
    {
        $subject = Subject::firstOrCreate(['code' => strtoupper(substr(md5($subjectName), 0, 8))], ['name' => $subjectName]);
        $matrix = CurriculumMatrix::firstOrCreate(['course_id' => $this->course->id, 'code' => 'GRA-MAT-0228-F'], ['name' => 'Matriz 15', 'status' => 'Atual']);
        $link = CurriculumMatrixDiscipline::firstOrCreate(
            ['curriculum_matrix_id' => $matrix->id, 'subject_id' => $subject->id],
            array_merge(['period' => 1, 'shift' => 'NOITE', 'weekly_hours' => 2, 'max_consecutive_classes' => 2], $linkExtra)
        );

        return ClassOffering::create(array_merge([
            'academic_term_id' => $this->term->id,
            'course_id' => $this->course->id,
            'curriculum_matrix_id' => $matrix->id,
            'curriculum_matrix_discipline_id' => $link->id,
            'subject_id' => $subject->id,
            'class_code' => $classCode,
            'period' => 1,
            'shift' => 'NOITE',
            'weekly_hours' => 2,
        ], $extra));
    }

    public function test_time_blocks_follow_sheet_notation(): void
    {
        $this->assertSame([1, 3], TimeBlocks::parseWeekdays('segunda-feira, quarta-feira'));
        $this->assertSame([4], TimeBlocks::parseWeekdays('Quinta'));
        $this->assertSame(['A', 'B', 'C', 'D'], TimeBlocks::parseLetters('AB, CD', 'NOITE'));
        $this->assertSame(
            [['start' => '18:30', 'end' => '20:10', 'letters' => 'AB'], ['start' => '20:20', 'end' => '22:00', 'letters' => 'CD']],
            TimeBlocks::intervals(['A', 'B', 'C', 'D'], 'NOITE')
        );
        $this->assertSame('07:30', TimeBlocks::forShift('MANHÃ')['A']['start']);
        $this->assertSame('09:10', TimeBlocks::forShift('MANHÃ')['C']['start']);
        $this->assertSame([], TimeBlocks::parseLetters('E', 'NOITE'), 'Noite tem só A–D');
    }

    public function test_apply_saves_professor_slots_and_room(): void
    {
        $offering = $this->offering('CSE0280101NNA', 'Lógica de Programação');

        $result = $this->allocator->apply($offering, ['professor_id' => $this->ana->id, 'weekdays' => [1, 3], 'letters' => ['A', 'B'], 'room' => 'Sala 40']);

        $this->assertTrue($result['saved'], implode(' ', $result['errors']));
        $this->assertSame(2, ScheduleSlot::count());
        $this->assertSame(['18:30', '20:10'], [substr(ScheduleSlot::first()->starts_at, 0, 5), substr(ScheduleSlot::first()->ends_at, 0, 5)]);
        $this->assertSame($this->ana->id, $offering->assignments()->first()->professor_id);
        $this->assertSame(['professor_id' => $this->ana->id, 'placements' => [1 => ['A', 'B'], 3 => ['A', 'B']], 'weekdays' => [1, 3], 'letters' => ['A', 'B'], 'room' => 'Sala 40'], $this->allocator->currentInput($offering->fresh(['slots', 'assignments'])));
    }

    public function test_per_day_placements_allow_different_blocks_each_day(): void
    {
        $offering = $this->offering('CSE0280101NNA', 'Banco de Dados', [], ['weekly_hours' => 3]);

        $result = $this->allocator->apply($offering, ['professor_id' => $this->ana->id, 'placements' => [1 => ['A', 'B'], 3 => ['C']], 'room' => 'Sala 40']);

        $this->assertTrue($result['saved'], implode(' ', $result['errors']));
        $this->assertStringNotContainsString('Distribuídas', implode(' ', $result['warnings']), '3 aulas = carga 3');
        $this->assertSame([1 => ['A', 'B'], 3 => ['C']], $this->allocator->currentInput($offering->fresh(['slots', 'assignments']))['placements']);
        $this->assertTrue($this->allocator->validate($offering, ['placements' => [7 => ['A']]])['errors'] !== []);
    }

    public function test_blocks_professor_room_and_class_clashes(): void
    {
        $first = $this->offering('CSE0280101NNA', 'Lógica de Programação');
        $this->allocator->apply($first, ['professor_id' => $this->ana->id, 'weekdays' => [1], 'letters' => ['A', 'B'], 'room' => 'Sala 40']);

        $otherClass = $this->offering('CSE0280103NNA', 'Banco de Dados');
        $errors = $this->allocator->validate($otherClass, ['professor_id' => $this->ana->id, 'weekdays' => [1], 'letters' => ['B'], 'room' => 'Sala 40'])['errors'];
        $this->assertStringContainsString('Ana já leciona Lógica de Programação', implode(' ', $errors));
        $this->assertStringContainsString('Sala Sala 40 ocupada', implode(' ', $errors));

        $sameClass = $this->offering('CSE0280101NNA', 'Matemática Aplicada');
        $errors = $this->allocator->validate($sameClass, ['professor_id' => $this->bruno->id, 'weekdays' => [1], 'letters' => ['A'], 'room' => 'Li41'])['errors'];
        $this->assertStringContainsString('A turma CSE0280101NNA já tem Lógica de Programação', implode(' ', $errors));

        // Outro dia/bloco: livre.
        $this->assertSame([], $this->allocator->validate($otherClass, ['professor_id' => $this->ana->id, 'weekdays' => [1], 'letters' => ['C', 'D'], 'room' => 'Sala 40'])['errors']);
    }

    public function test_joint_class_same_subject_and_professor_is_allowed(): void
    {
        // Como na 2026.1: UX/UI de SI e de ADS juntas, mesma professora e horário.
        $si = $this->offering('CSE0280101NNA', 'UX/UI Design');
        $this->allocator->apply($si, ['professor_id' => $this->ana->id, 'weekdays' => [1], 'letters' => ['C', 'D'], 'room' => 'Sala 40']);
        $ads = $this->offering('CSE0400101NNA', 'UX/UI Design');

        $result = $this->allocator->validate($ads, ['professor_id' => $this->ana->id, 'weekdays' => [1], 'letters' => ['C', 'D'], 'room' => 'Sala 40']);

        $this->assertSame([], $result['errors']);
        $this->assertStringContainsString('Aula conjunta', implode(' ', $result['warnings']));
    }

    public function test_availability_room_type_and_qualification_rules(): void
    {
        $offering = $this->offering('CSE0280103NNA', 'Database Application', [], ['required_room_type' => 'LABORATORIO_INFORMATICA']);

        // Sem disponibilidade cadastrada: só aviso. Sem habilitação: aviso. Sala comum para disciplina de laboratório: erro.
        $result = $this->allocator->validate($offering, ['professor_id' => $this->ana->id, 'weekdays' => [2], 'letters' => ['A', 'B'], 'room' => 'Sala 40']);
        $this->assertStringContainsString('exige Laboratório de Informática', implode(' ', $result['errors']));
        $this->assertStringContainsString('ainda não informou disponibilidade', implode(' ', $result['warnings']));
        $this->assertStringContainsString('não está habilitado', implode(' ', $result['warnings']));

        // Com grade preenchida: fora dela é erro.
        ProfessorAvailability::create(['academic_term_id' => $this->term->id, 'professor_id' => $this->ana->id, 'weekday' => 2, 'starts_at' => '18:30', 'ends_at' => '19:20', 'preference' => 'preferred']);
        $result = $this->allocator->validate($offering, ['professor_id' => $this->ana->id, 'weekdays' => [2], 'letters' => ['A', 'B'], 'room' => 'Li41']);
        $this->assertSame(['Ana está indisponível em: Ter 19:20.'], $result['errors']);

        ProfessorAvailability::create(['academic_term_id' => $this->term->id, 'professor_id' => $this->ana->id, 'weekday' => 2, 'starts_at' => '19:20', 'ends_at' => '20:10', 'preference' => 'available']);
        $this->assertSame([], $this->allocator->validate($offering, ['professor_id' => $this->ana->id, 'weekdays' => [2], 'letters' => ['A', 'B'], 'room' => 'Li41'])['errors']);

        // Bloco maior que o máximo de aulas seguidas: aviso.
        $this->assertStringContainsString('passa de 2 aulas seguidas', implode(' ', $this->allocator->validate($offering, ['weekdays' => [3], 'letters' => ['A', 'B', 'C'], 'room' => 'Li41'])['warnings']) . ' '
            . implode(' ', $this->allocator->validate($this->offering('X1', 'Outra', ['shift' => 'MANHÃ']), ['weekdays' => [3], 'letters' => ['A', 'B', 'C']])['warnings']));
    }

    public function test_directed_study_groups_may_overlap_regular_classes_may_not(): void
    {
        // Como Reginaldo na 2026.1: Cálculo e Estatística (2 alunos cada, ED) na mesma noite e sala.
        $calculo = $this->offering('CSE0280101NNB', 'Cálculo', ['directed_study' => true]);
        $this->allocator->apply($calculo, ['professor_id' => $this->ana->id, 'weekdays' => [3], 'letters' => ['A', 'B'], 'room' => 'Sala 40']);
        $estatistica = $this->offering('CSE0280102NNB', 'Estatística', ['directed_study' => true]);

        $result = $this->allocator->validate($estatistica, ['professor_id' => $this->ana->id, 'weekdays' => [3], 'letters' => ['A', 'B'], 'room' => 'Sala 40']);
        $this->assertSame([], $result['errors']);
        $this->assertStringContainsString('Estudo dirigido simultâneo', implode(' ', $result['warnings']));

        // Turma regular no mesmo horário continua bloqueada.
        $regular = $this->offering('CSE0280103NNA', 'Banco de Dados');
        $this->assertNotSame([], $this->allocator->validate($regular, ['professor_id' => $this->ana->id, 'weekdays' => [3], 'letters' => ['A'], 'room' => 'Li41'])['errors']);
    }

    public function test_directed_study_suggestion_follows_2026_sheet(): void
    {
        $offering = $this->offering('CSE0280108NNA', 'TCC II');

        $this->assertSame(['directed_study' => true, 'weekly_hours' => 1.0], $this->allocator->suggestLoad($offering, 8));
        $this->assertSame(['directed_study' => true, 'weekly_hours' => 0.0], $this->allocator->suggestLoad($offering, 0));
        $this->assertSame(['directed_study' => false, 'weekly_hours' => 2.0], $this->allocator->suggestLoad($offering, 9));
    }

    public function test_copy_from_previous_term_reuses_schedule_without_clashes(): void
    {
        $previous = AcademicTerm::create(['code' => '2026.1', 'status' => 'closed']);
        $old = $this->offering('CSE0280101NNA', 'Lógica de Programação', ['academic_term_id' => $previous->id]);
        $this->allocator->apply($old, ['professor_id' => $this->ana->id, 'weekdays' => [2], 'letters' => ['C', 'D'], 'room' => 'Sala 40']);

        $new = $this->offering('CSE0280101NNA', 'Lógica de Programação');
        $report = $this->allocator->copyFromTerm($previous, $this->term);

        $this->assertSame(1, $report['copied']);
        $this->assertSame(['professor_id' => $this->ana->id, 'placements' => [2 => ['C', 'D']], 'weekdays' => [2], 'letters' => ['C', 'D'], 'room' => 'Sala 40'], $this->allocator->currentInput($new->fresh(['slots', 'assignments'])));
        $this->assertSame('planned', $new->assignments()->first()->status);
    }

    public function test_coordinator_saves_allocation_and_student_sees_schedule(): void
    {
        $offering = $this->offering('CSE0280101NNA', 'Lógica de Programação');
        $coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
        $student = User::create(['name' => 'Aluno', 'email' => 'a@x.local', 'password' => 'senha1234', 'role' => 'student', 'is_active' => true]);

        $this->actingAs($coordinator)->get(route('allocation.index'))->assertOk()->assertSee('Turma CSE0280101NNA');

        $this->actingAs($coordinator)
            ->from(route('allocation.index'))
            ->put(route('allocation.update', $offering), [
                'professor_id' => $this->ana->id, 'student_count' => 5, 'directed_study' => '1', 'weekly_hours' => 1,
                'weekdays' => [1], 'letters' => ['A', 'B'], 'room' => 'Sala 40',
            ])
            ->assertRedirect()
            ->assertSessionHas('allocation', fn ($f) => $f['saved'] === true);

        $offering->refresh();
        $this->assertTrue($offering->directed_study);
        $this->assertSame(5, (int) $offering->student_count);

        $this->actingAs($student)->get(route('schedules.index'))->assertOk()->assertSee('ainda não foram publicados')->assertDontSee('Lógica de Programação');
        $this->actingAs($coordinator)->post(route('allocation.publish'))->assertRedirect();
        $this->assertTrue($this->term->fresh()->isSchedulePublished());

        $this->actingAs($student)
            ->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Lógica de Programação')
            ->assertSee('Ana')
            ->assertSee('Sala 40')
            ->assertSee('18:30–20:10');

        $this->actingAs($student)->get(route('allocation.index'))->assertForbidden();
        $this->actingAs($student)->put(route('allocation.update', $offering), [])->assertForbidden();
    }

    public function test_invalid_allocation_is_not_saved(): void
    {
        $first = $this->offering('CSE0280101NNA', 'Lógica de Programação');
        $this->allocator->apply($first, ['professor_id' => $this->ana->id, 'weekdays' => [1], 'letters' => ['A', 'B'], 'room' => 'Sala 40']);
        $second = $this->offering('CSE0280103NNA', 'Banco de Dados');
        $coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);

        $this->actingAs($coordinator)
            ->from(route('allocation.index'))
            ->put(route('allocation.update', $second), ['professor_id' => $this->ana->id, 'weekdays' => [1], 'letters' => ['A'], 'room' => 'Li41'])
            ->assertSessionHas('allocation', fn ($f) => ! isset($f['saved']) && collect($f['errors'])->contains(fn ($e) => str_contains($e, 'Ana já leciona')));

        $this->assertSame(0, $second->slots()->count());
    }

    public function test_2026_sheet_is_imported_with_schedule(): void
    {
        $this->seed(Planilha20261Seeder::class);

        $term = AcademicTerm::where('code', '2026.1')->firstOrFail();
        $offerings = ClassOffering::where('academic_term_id', $term->id);
        $this->assertSame(105, (clone $offerings)->count());

        // "ANÁLISE E PROJETO DE SISTEMAS I": segunda e quarta, AB, Sala 46, Francisco, 5 alunos, ED.
        $aps = (clone $offerings)->whereHas('subject', fn ($q) => $q->where('code', 'UNI7T248'))->with('slots', 'assignments.professor')->firstOrFail();
        $this->assertSame([1, 3], $aps->slots->pluck('weekday')->map(fn ($d) => (int) $d)->sort()->values()->all());
        $this->assertSame(['AB'], $aps->slots->pluck('block')->unique()->values()->all());
        $this->assertSame('Sala 46', $aps->slots->first()->room);
        $this->assertSame('Francisco Antonio De Araujo E Souza', $aps->assignments->first()->professor->name);
        $this->assertTrue($aps->directed_study);
        $this->assertSame(5, (int) $aps->student_count);

        // DOL = a distância, sem professor.
        $dol = (clone $offerings)->where('modality', 'DOL')->with('assignments')->get();
        $this->assertNotEmpty($dol);
        $this->assertTrue($dol->every(fn ($o) => $o->assignments->isEmpty()));

        // "Felipe Mota" casa com FELIPE MOTA MARTINS; Neusa/Valbert/Helena são cadastrados.
        $this->assertTrue(Professor::where('name', 'FELIPE MOTA MARTINS')->first()->subjects()->where('code', 'UNI7T033')->exists());
        $this->assertSame(3, Professor::whereIn('name', ['Neusa Liberato Evangelista', 'Valbert Oliveira Costa', 'Helena Campelo'])->count());
        $this->assertTrue(Room::where('code', 'Li41')->where('type', 'LABORATORIO_INFORMATICA')->exists());
    }

    public function test_full_load_builds_2027_grid_from_2026(): void
    {
        $this->seed(CargaPlanilhasSeeder::class);

        $target = AcademicTerm::where('code', '2027.1')->firstOrFail();
        $withSchedule = ClassOffering::where('academic_term_id', $target->id)->whereHas('slots')->count();
        $this->assertGreaterThan(50, $withSchedule);

        // A grade montada automaticamente não tem choques de professor nem de sala
        // (exceto aulas conjuntas, permitidas).
        $this->assertSame([], app(\App\Services\ConflictDetector::class)->forTerm($target->id)->pluck('message')->all());
    }
}
