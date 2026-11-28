<?php

namespace App\Services;

use App\Models\CurriculumMatrix;
use App\Models\StudentSubjectStatus;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa o histórico escolar normalizado (CSV extraído dos PDFs de histórico):
 * matricula;nome;curriculo;turno;periodo_letivo;periodo_matriz;codigo;disciplina;carga_horaria;situacao;resultado
 *
 * Só guarda o necessário para a enturmação (sem CPF, RG ou data de nascimento).
 */
class StudentHistoryImporter
{
    /** Código do currículo no histórico → código da matriz no sistema. */
    public const CURRICULUM_TO_MATRIX = ['GSI2019.1' => 'SI-2019.1'];

    /** Situação mais favorável prevalece quando a disciplina aparece mais de uma vez. */
    private const PRIORITY = ['aprovada' => 6, 'dispensada' => 5, 'matriculada' => 4, 'reprovada' => 3, 'trancada' => 2, 'falta_cursar' => 1];

    public const EMAIL_DOMAIN = 'aluno.uni7.edu.br';

    /** @return array{students: int, statuses: int, unknown_curricula: array<int, string>} */
    public function importCsv(string $path, string $source): array
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => trim($h, "\xEF\xBB\xBF \t"), fgetcsv($handle, 0, ';'));
        $rows = [];
        while (($values = fgetcsv($handle, 0, ';')) !== false) {
            if (count($values) >= count($header)) {
                $rows[] = array_combine($header, array_slice($values, 0, count($header)));
            }
        }
        fclose($handle);

        return $this->import($rows, $source);
    }

    /** @param CurriculumMatrix|null $target Matriz escolhida na tela: vale para todos os alunos do lote. */
    public function import(array $rows, string $source, ?CurriculumMatrix $target = null): array
    {
        $students = [];
        $statuses = 0;
        $unknown = [];

        DB::transaction(function () use ($rows, $source, $target, &$students, &$statuses, &$unknown) {
            foreach (collect($rows)->groupBy('matricula') as $registration => $items) {
                $first = $items->first();
                $matrixCode = self::CURRICULUM_TO_MATRIX[$first['curriculo']] ?? null;
                $matrix = $target ?? ($matrixCode ? CurriculumMatrix::where('code', $matrixCode)->first() : null);
                if (! $matrix) {
                    $unknown[$first['curriculo']] = true;
                }

                $user = User::where('registration_number', $registration)->first();
                $attributes = [
                    'name' => Str::title(Str::lower(trim($first['nome']))),
                    'curriculum_matrix_id' => $matrix?->id,
                    'shift' => $this->shift($first['turno']),
                ];
                if ($user) {
                    // Aluno já cadastrado: atualiza matriz/turno; senha, perfil e status ficam como estão.
                    $user->fill($attributes)->save();
                } else {
                    $user = User::create($attributes + [
                        'role' => 'student',
                        'registration_number' => $registration,
                        'email' => $registration . '@' . self::EMAIL_DOMAIN,
                        'password' => 'senha1234',
                        'is_active' => true,
                        'must_change_password' => true,
                    ]);
                }
                $students[$registration] = true;

                $best = [];
                foreach ($items as $item) {
                    $code = Str::upper(trim($item['codigo']));
                    if (! isset($best[$code]) || self::PRIORITY[$item['situacao']] > self::PRIORITY[$best[$code]['situacao']]) {
                        $best[$code] = $item;
                    }
                }

                foreach ($best as $code => $item) {
                    $subject = Subject::firstOrCreate(['code' => $code], ['name' => trim($item['disciplina'])]);
                    $grade = preg_match('/(\d{1,2},\d{1,2})/', (string) $item['resultado'], $m) ? (float) str_replace(',', '.', $m[1]) : null;

                    StudentSubjectStatus::updateOrCreate(
                        ['user_id' => $user->id, 'subject_id' => $subject->id],
                        [
                            'status' => $item['situacao'],
                            'term_code' => $item['periodo_letivo'] ?: null,
                            'matrix_period' => is_numeric($item['periodo_matriz']) ? (int) $item['periodo_matriz'] : null,
                            'grade' => $grade,
                            'source' => $source,
                        ]
                    );
                    $statuses++;
                }
            }
        });

        return ['students' => count($students), 'statuses' => $statuses, 'unknown_curricula' => array_keys($unknown)];
    }

    private function shift(string $value): ?string
    {
        return match (Str::upper(Str::ascii(trim($value)))) {
            'MANHA' => 'MANHÃ',
            'TARDE' => 'TARDE',
            'NOITE' => 'NOITE',
            default => null,
        };
    }
}
