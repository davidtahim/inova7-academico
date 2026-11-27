<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Professor;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carga inicial a partir da planilha "Disciplinas 2027.1" (Google Sheets), exportada para
 * database/seeders/data/planilha-2027-1/*.csv.
 *
 * Uso (zera o banco e importa):
 *   php artisan migrate:fresh --seeder=Planilha20271Seeder
 *
 * Abas utilizadas:
 *   - Sistemas de Informação / ADS / Bacharelado em Inteligência Artificial → oferta 2027.1
 *   - Professores → professores e usuários docentes
 *   - Matriz Grupo Ser E → currículo completo da Matriz 14 (GRA-MAT-0228-E)
 *   - Matriz Uni7 → currículo completo da matriz "Sistemas de Informação 2019.1"
 * Abas não utilizadas: "Página10" (colunas técnicas sem cabeçalho), "Disciplinas UNI7" (vazia)
 * e "Oferta Uni7" (rascunho da oferta da matriz 2019.1, sem turno; a aba SI já traz a oferta vigente).
 */
class Planilha20271Seeder extends Seeder
{
    private const DATA_DIR = __DIR__ . '/data/planilha-2027-1';

    /** Placeholder: a planilha não informa carga horária (coluna "Hora Aula Semanal" vazia). */
    private const DEFAULT_WEEKLY_CLASSES = 2;

    private const TERM_CODE = '2027.1';

    private const DEFAULT_PASSWORD = 'senha1234';

    /** @var array<string, string> log de resumo exibido no console */
    private array $summary = [];

    public function run(): void
    {
        DB::transaction(function () {
            $this->seedAdmin();
            $term = AcademicTerm::updateOrCreate(['code' => self::TERM_CODE], ['status' => 'planning']);

            $courses = $this->seedCourses();
            $matrices = $this->seedMatrices($courses);
            $professors = $this->seedProfessors();

            $offerRows = $this->offerRows();
            $sharedCodes = $this->codesOfferedInMoreThanOneCourse($offerRows);

            $this->seedFullCurriculum($matrices['SI_M14'], 'matriz-grupo-ser-e.csv', $sharedCodes);
            $this->seedFullCurriculum($matrices['SI_2019'], 'matriz-uni7.csv', $sharedCodes);

            $this->seedOfferings($term, $courses, $matrices, $offerRows, $professors, $sharedCodes);
        });

        foreach ($this->summary as $line) {
            $this->command?->info($line);
        }
    }

    private function seedAdmin(): void
    {
        User::updateOrCreate(['email' => 'admin@inova7.local'], [
            'name' => 'Administrador de TI',
            'password' => self::DEFAULT_PASSWORD,
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $this->summary[] = 'Admin: admin@inova7.local / ' . self::DEFAULT_PASSWORD . ' (troca de senha no primeiro acesso)';
    }

    /** @return array<string, Course> */
    private function seedCourses(): array
    {
        $courses = [
            'SI' => Course::updateOrCreate(['code' => 'GRA-PPC-0228-E'], ['name' => 'Sistemas de Informação', 'degree' => 'Bacharelado', 'active' => true]),
            'ADS' => Course::updateOrCreate(['code' => 'GRA-PPC-0240-G'], ['name' => 'Análise e Desenvolvimento de Sistemas', 'degree' => 'Tecnólogo', 'active' => true]),
            // A planilha não traz o código do PPC de IA; usa o prefixo das turmas (CSE330…).
            'IA' => Course::updateOrCreate(['code' => 'CSE330'], ['name' => 'Bacharelado em Inteligência Artificial', 'degree' => 'Bacharelado', 'active' => true]),
        ];
        $this->summary[] = 'Cursos: ' . count($courses);

        return $courses;
    }

    /** @return array<string, CurriculumMatrix> */
    private function seedMatrices(array $courses): array
    {
        $matrix = fn (Course $course, string $code, string $name, string $version, string $status) => CurriculumMatrix::updateOrCreate(
            ['course_id' => $course->id, 'code' => $code],
            ['name' => $name, 'version' => $version, 'status' => $status, 'allowed_for_import' => true]
        );

        $matrices = [
            'SI_M15' => $matrix($courses['SI'], 'GRA-MAT-0228-F', 'Matriz 15', '15', 'Atual'),
            'SI_M14' => $matrix($courses['SI'], 'GRA-MAT-0228-E', 'Matriz 14', '14', 'Ativa'),
            'SI_2019' => $matrix($courses['SI'], 'SI-2019.1', 'Sistemas de Informação 2019.1', '2019.1', 'Ativa'),
            'ADS_M56' => $matrix($courses['ADS'], 'GRA-MAT-2140-H', 'Matriz 56', '56', 'Atual'),
        ];
        $this->summary[] = 'Matrizes: ' . count($matrices);

        return $matrices;
    }

    /** @return Collection<int, Professor> */
    private function seedProfessors(): Collection
    {
        $professors = collect();

        foreach ($this->readCsv('professores.csv') as $row) {
            $name = $this->clean($row['Professor'] ?? '');
            if ($name === '' || in_array(Str::upper($name), ['DOL', 'GOKURSO'], true)) {
                continue; // linhas vazias e modalidades que aparecem na coluna de professor
            }

            $registration = $this->clean($row['Matrícula'] ?? '');
            $email = Str::lower($this->clean($row['Email'] ?? ''));

            $professor = Professor::updateOrCreate(['registration' => $registration], [
                'name' => $name,
                'email' => $email ?: null,
                'chapa' => $this->clean($row['Chapa'] ?? '') ?: null,
                'lattes' => $this->clean($row['Lattes'] ?? '') ?: null,
                'active' => true,
            ]);

            if ($email !== '') {
                User::updateOrCreate(['email' => $email], [
                    'name' => $name,
                    'password' => self::DEFAULT_PASSWORD,
                    'role' => 'teacher',
                    'is_active' => true,
                    'must_change_password' => true,
                    'professor_id' => $professor->id,
                    'registration_number' => $registration ?: null,
                ]);
            }

            $professors->push($professor);
        }

        $this->summary[] = "Professores: {$professors->count()} (usuários com e-mail da chapa, senha inicial " . self::DEFAULT_PASSWORD . ')';

        return $professors;
    }

    private function seedFullCurriculum(CurriculumMatrix $matrix, string $file, array $sharedCodes): void
    {
        $count = 0;
        foreach ($this->readCsv($file) as $row) {
            $subject = $this->subject($row['Código da Disciplina'] ?? '', $row['Nome'] ?? '');
            if (! $subject) {
                continue;
            }
            $this->link($matrix, $subject, (int) ($row['Semestre'] ?? 1), 'NOITE', isset($sharedCodes[$subject->code]));
            $count++;
        }
        $this->summary[] = "Currículo {$matrix->name} ({$matrix->code}): {$count} disciplinas";
    }

    /**
     * Linhas das três abas de oferta, já com curso e chave da matriz.
     *
     * @return array<int, array{course: string, matrix: ?string, turma: string, codigo: string, nome: string, turno: string, professor: string}>
     */
    private function offerRows(): array
    {
        $rows = [];

        foreach ($this->readCsv('oferta-sistemas-de-informacao.csv') as $row) {
            $rows[] = $this->offerRow('SI', $this->siMatrixKey($row['Matriz'] ?? ''), $row);
        }
        foreach ($this->readCsv('oferta-ads.csv') as $row) {
            $rows[] = $this->offerRow('ADS', 'ADS_M56', $row);
        }
        foreach ($this->readCsv('oferta-inteligencia-artificial.csv') as $row) {
            $rows[] = $this->offerRow('IA', null, $row);
        }

        return array_values(array_filter($rows, fn ($row) => $row['turma'] !== '' && $row['codigo'] !== ''));
    }

    private function offerRow(string $course, ?string $matrixKey, array $row): array
    {
        return [
            'course' => $course,
            'matrix' => $matrixKey,
            'turma' => $this->clean($row['Código da Turma'] ?? ''),
            'codigo' => Str::upper($this->clean($row['Cód Disciplina'] ?? '')),
            'nome' => $this->clean($row['Nome Disciplina'] ?? ''),
            'turno' => $this->shift($row['Turno'] ?? ''),
            'professor' => $this->clean($row['Professor'] ?? ''),
        ];
    }

    private function siMatrixKey(string $value): ?string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii($value)));

        return match (true) {
            str_contains($normalized, 'GRAMAT0228F') => 'SI_M15',
            str_contains($normalized, 'GRAMAT0228E') => 'SI_M14',
            str_contains($normalized, '20191') => 'SI_2019',
            default => null,
        };
    }

    /** @return array<string, true> códigos de disciplina ofertados em mais de um curso */
    private function codesOfferedInMoreThanOneCourse(array $rows): array
    {
        return collect($rows)
            ->groupBy('codigo')
            ->filter(fn ($group) => $group->pluck('course')->unique()->count() > 1)
            ->map(fn () => true)
            ->all();
    }

    private function seedOfferings(AcademicTerm $term, array $courses, array $matrices, array $rows, Collection $professors, array $sharedCodes): void
    {
        $offerings = 0;
        $assignments = 0;
        $unmatchedProfessors = [];

        // Turno padrão do vínculo na matriz: NOITE quando a disciplina é ofertada à noite.
        $linkShift = collect($rows)->groupBy(fn ($r) => ($r['matrix'] ?? '-') . '|' . $r['codigo'])
            ->map(fn ($group) => $group->pluck('turno')->contains('NOITE') ? 'NOITE' : $group->first()['turno']);

        foreach ($rows as $row) {
            $subject = $this->subject($row['codigo'], $row['nome']);
            $matrix = $row['matrix'] ? $matrices[$row['matrix']] : null;
            $period = $this->periodFromClassCode($row['turma']);

            $link = $matrix
                ? $this->link($matrix, $subject, $period, $linkShift[$row['matrix'] . '|' . $row['codigo']], isset($sharedCodes[$subject->code]))
                : null;

            $weekly = $link?->weekly_hours ?: self::DEFAULT_WEEKLY_CLASSES;

            $offering = ClassOffering::updateOrCreate(
                ['academic_term_id' => $term->id, 'class_code' => $row['turma'], 'subject_id' => $subject->id],
                [
                    'course_id' => $courses[$row['course']]->id,
                    'curriculum_matrix_id' => $matrix?->id,
                    'curriculum_matrix_discipline_id' => $link?->id,
                    'period' => $period,
                    'shift' => $row['turno'],
                    'modality' => 'PRESENCIAL',
                    'occurs' => true,
                    'weekly_hours' => $weekly,
                    'totvs_hours' => $weekly,
                    'status' => 'planned',
                ]
            );
            $offerings++;

            if ($row['professor'] !== '') {
                $professor = $this->matchProfessor($row['professor'], $professors);
                if (! $professor) {
                    $unmatchedProfessors[$row['professor']] = true;
                    continue;
                }

                TeachingAssignment::updateOrCreate(
                    ['class_offering_id' => $offering->id, 'professor_id' => $professor->id],
                    ['weekly_hours' => $weekly, 'status' => 'planned']
                );
                // Quem foi indicado para a turma está apto a lecionar a disciplina.
                $professor->subjects()->syncWithoutDetaching([$subject->id]);
                $assignments++;
            }
        }

        $this->summary[] = "Turmas {$term->code}: {$offerings} | alocações sugeridas: {$assignments}";
        $this->summary[] = 'Disciplinas: ' . Subject::count() . ' | vínculos matriz × disciplina: ' . CurriculumMatrixDiscipline::count();
        if ($unmatchedProfessors !== []) {
            $this->summary[] = 'Professores da oferta sem cadastro: ' . implode(', ', array_keys($unmatchedProfessors));
        }
        $this->summary[] = 'Atenção: a planilha não informa carga horária nem quantidade de alunos; '
            . 'todas as turmas foram criadas com ' . self::DEFAULT_WEEKLY_CLASSES . ' aulas/semana (ajuste na matriz).';
    }

    private function link(CurriculumMatrix $matrix, Subject $subject, int $period, string $shift, bool $shared): CurriculumMatrixDiscipline
    {
        $existing = CurriculumMatrixDiscipline::where('curriculum_matrix_id', $matrix->id)
            ->where('subject_id', $subject->id)
            ->first();

        if ($existing) {
            if ($shared && ! $existing->allows_shared_class) {
                $existing->update(['allows_shared_class' => true]);
            }

            return $existing;
        }

        return CurriculumMatrixDiscipline::create([
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $subject->id,
            'period' => max(1, min(8, $period)),
            'shift' => $shift,
            'weekly_hours' => self::DEFAULT_WEEKLY_CLASSES,
            'offering_type' => 'OBRIGATORIA',
            'allows_shared_class' => $shared,
            'max_consecutive_classes' => 2,
        ]);
    }

    private function subject(string $code, string $name): ?Subject
    {
        $code = Str::upper($this->clean($code));
        $name = $this->clean($name);
        if ($code === '' || $name === '') {
            return null;
        }

        return Subject::firstOrCreate(['code' => $code], ['name' => $name]);
    }

    /** CSE0280103NMA → 3º período (dígitos 9 e 10 do código da turma). */
    private function periodFromClassCode(string $classCode): int
    {
        $digits = substr($classCode, 8, 2);

        return ctype_digit($digits) && (int) $digits > 0 ? (int) $digits : 1;
    }

    private function shift(string $value): string
    {
        return match (Str::upper(Str::ascii($this->clean($value)))) {
            'TARDE' => 'TARDE',
            'NOITE' => 'NOITE',
            'INTEGRAL' => 'INTEGRAL',
            default => 'MANHÃ',
        };
    }

    /** Casa "Larissa" ou "Luciano Comin Nunes " com o cadastro, ignorando acentos e espaços. */
    private function matchProfessor(string $name, Collection $professors): ?Professor
    {
        $wanted = Str::lower(Str::ascii($this->clean($name)));

        return $professors->first(fn (Professor $p) => Str::lower(Str::ascii($p->name)) === $wanted)
            ?? $professors->filter(fn (Professor $p) => Str::startsWith(Str::lower(Str::ascii($p->name)), $wanted . ' '))
                ->whenNotEmpty(fn ($matches) => $matches->count() === 1 ? $matches : collect())
                ->first();
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /** @return array<int, array<string, string>> */
    private function readCsv(string $file): array
    {
        $handle = fopen(self::DATA_DIR . '/' . $file, 'r');
        $header = null;
        $rows = [];

        while (($values = fgetcsv($handle, 0, ';')) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => $this->clean((string) $h), $values);
                continue;
            }
            $values = array_pad($values, count($header), '');
            $rows[] = array_combine($header, array_slice($values, 0, count($header)));
        }
        fclose($handle);

        return $rows;
    }
}
