<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Professor;
use App\Models\Room;
use App\Models\ScheduleSlot;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\TimeBlocks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Oferta 2026.1 já montada (planilha "Disciplinas 2026.1"): professor, alunos, Estudo Dirigido,
 * Hora Aula, sala, dia e blocos (AB/CD). É gravada como foi feita — sem validação — para servir
 * de histórico e de base para montar a 2027.1 (ScheduleAllocator::copyFromTerm).
 *
 * Depende dos cursos/matrizes/professores do Planilha20271Seeder (chamado se faltar).
 */
class Planilha20261Seeder extends Seeder
{
    private const DATA_DIR = __DIR__ . '/data/planilha-2026-1';

    public function run(): void
    {
        $hasBase = Course::where('code', 'GRA-PPC-0240-G')->exists()
            && CurriculumMatrix::whereIn('code', ['GRA-MAT-0228-E', 'SI-2019.1', 'GRA-MAT-2140-H'])->count() === 3;
        if (! $hasBase) {
            $this->call(Planilha20271Seeder::class);
        }
        $this->call(RoomsSeeder::class);

        $summary = DB::transaction(function () {
            $term = AcademicTerm::updateOrCreate(['code' => '2026.1'], ['status' => 'closed']);
            $professors = $this->seedProfessors();

            $courses = ['SI' => Course::where('code', 'GRA-PPC-0228-E')->firstOrFail(), 'ADS' => Course::where('code', 'GRA-PPC-0240-G')->firstOrFail()];
            $matrices = CurriculumMatrix::whereIn('code', ['GRA-MAT-0228-E', 'SI-2019.1', 'GRA-MAT-2140-H'])->get()->keyBy('code');

            $offerings = 0;
            $slots = 0;
            $assigned = 0;
            foreach ($this->readCsv('oferta-2026-1.csv') as $row) {
                $classCode = trim($row['Código da Turma']);
                $code = Str::upper(trim($row['Cód Disciplina']));
                $subject = Subject::firstOrCreate(['code' => $code], ['name' => trim($row['Nome Disciplina'])]);
                $shift = $this->shift($row['Turno'], $classCode);
                $period = $this->period($classCode);
                $professorName = trim(preg_replace('/\s+/', ' ', $row['Professor']));
                $isDistance = Str::upper($professorName) === 'DOL';

                $matrixCode = $row['Curso'] === 'ADS' ? 'GRA-MAT-2140-H' : (str_starts_with($code, 'UNI7') ? 'SI-2019.1' : 'GRA-MAT-0228-E');
                $matrix = $matrices[$matrixCode];
                $link = CurriculumMatrixDiscipline::firstOrCreate(
                    ['curriculum_matrix_id' => $matrix->id, 'subject_id' => $subject->id],
                    ['period' => max(1, min(8, $period)), 'shift' => $shift, 'weekly_hours' => 2, 'offering_type' => 'OBRIGATORIA', 'max_consecutive_classes' => 2]
                );

                $offering = ClassOffering::updateOrCreate(
                    ['academic_term_id' => $term->id, 'class_code' => $classCode, 'subject_id' => $subject->id],
                    [
                        'course_id' => $courses[$row['Curso']]->id,
                        'curriculum_matrix_id' => $matrix->id,
                        'curriculum_matrix_discipline_id' => $link->id,
                        'period' => $period,
                        'shift' => $shift,
                        'modality' => $isDistance ? 'DOL' : 'PRESENCIAL',
                        'student_count' => is_numeric($row['Quantidade de Alunos']) ? (int) $row['Quantidade de Alunos'] : null,
                        'directed_study' => Str::lower(Str::ascii(trim($row['Estudo Dirigido']))) === 'sim',
                        'occurs' => true,
                        'weekly_hours' => is_numeric($row['Hora Aula']) ? (float) $row['Hora Aula'] : 0,
                        'totvs_hours' => is_numeric($row['Hora Aula']) ? (float) $row['Hora Aula'] : 0,
                        'status' => 'confirmed',
                    ]
                );
                $offerings++;

                $professor = $isDistance || $professorName === '' ? null : $this->matchProfessor($professorName, $professors);
                if ($professor) {
                    TeachingAssignment::updateOrCreate(
                        ['class_offering_id' => $offering->id, 'professor_id' => $professor->id],
                        ['weekly_hours' => $offering->weekly_hours, 'status' => 'confirmed']
                    );
                    $professor->subjects()->syncWithoutDetaching([$subject->id]);
                    $assigned++;
                }

                $room = trim($row['Sala']);
                if ($room !== '') {
                    Room::firstOrCreate(['code' => $room], ['name' => $room, 'type' => Room::guessType($room), 'active' => true]);
                }

                ScheduleSlot::where('class_offering_id', $offering->id)->delete();
                $letters = TimeBlocks::parseLetters($row['Horário'], $shift);
                foreach (TimeBlocks::parseWeekdays($row['Dia']) as $weekday) {
                    foreach (TimeBlocks::intervals($letters, $shift) as $interval) {
                        ScheduleSlot::create([
                            'class_offering_id' => $offering->id,
                            'professor_id' => $professor?->id,
                            'weekday' => $weekday,
                            'starts_at' => $interval['start'],
                            'ends_at' => $interval['end'],
                            'room' => $room ?: null,
                            'block' => $interval['letters'],
                        ]);
                        $slots++;
                    }
                }
            }

            return "Oferta 2026.1: {$offerings} disciplinas/turma, {$assigned} com professor, {$slots} horários na grade";
        });

        $this->command?->info($summary);
    }

    /** @return Collection<int, Professor> */
    private function seedProfessors(): Collection
    {
        foreach ($this->readCsv('professores-2026-1.csv') as $row) {
            $name = trim(preg_replace('/\s+/', ' ', $row['Professor']));
            if ($name === '' || $this->matchProfessor($name, Professor::all())) {
                continue;
            }
            Professor::create([
                'name' => $name,
                'registration' => trim($row['Matrícula']) ?: null,
                'lattes' => trim($row['Lattes']) ?: null,
                'active' => true,
            ]);
        }

        return Professor::all();
    }

    /** Casa nomes com/sem acento, espaços extras e abreviações ("Felipe Mota" → FELIPE MOTA MARTINS). */
    private function matchProfessor(string $name, Collection $professors): ?Professor
    {
        $wanted = Str::lower(Str::ascii(trim(preg_replace('/\s+/', ' ', $name))));
        $normalize = fn (Professor $p) => Str::lower(Str::ascii(trim(preg_replace('/\s+/', ' ', $p->name))));

        $exact = $professors->first(fn ($p) => $normalize($p) === $wanted);
        if ($exact) {
            return $exact;
        }
        $prefix = $professors->filter(fn ($p) => Str::startsWith($normalize($p), $wanted . ' '));

        return $prefix->count() === 1 ? $prefix->first() : null;
    }

    private function shift(string $value, string $classCode): string
    {
        $value = Str::upper(Str::ascii(trim($value)));
        if (in_array($value, ['MANHA', 'TARDE', 'NOITE'], true)) {
            return $value === 'MANHA' ? 'MANHÃ' : $value;
        }
        // CSE0280101NMA → N(ível) M(anhã) A(turma); CSE0400101TNA → T(arde)
        if (($classCode[10] ?? '') === 'T') {
            return 'TARDE';
        }

        return match ($classCode[11] ?? '') {
            'M' => 'MANHÃ',
            'T' => 'TARDE',
            default => 'NOITE',
        };
    }

    private function period(string $classCode): int
    {
        $digits = substr($classCode, 8, 2);

        return ctype_digit($digits) && (int) $digits > 0 ? (int) $digits : 1;
    }

    /** @return array<int, array<string, string>> */
    private function readCsv(string $file): array
    {
        $handle = fopen(self::DATA_DIR . '/' . $file, 'r');
        $header = null;
        $rows = [];
        while (($values = fgetcsv($handle, 0, ';')) !== false) {
            if ($header === null) {
                $header = array_map('trim', $values);
                continue;
            }
            $rows[] = array_combine($header, array_slice(array_pad($values, count($header), ''), 0, count($header)));
        }
        fclose($handle);

        return $rows;
    }
}
