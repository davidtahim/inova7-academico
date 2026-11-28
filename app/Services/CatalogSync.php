<?php

namespace App\Services;

use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza o catálogo acadêmico (cursos, matrizes, disciplinas e a grade de cada
 * matriz) entre ambientes, por códigos e não por ids.
 *
 * Uso: no ambiente correto (ex.: banco local), "php artisan catalogo:exportar" gera
 * um arquivo JSON e uma migration que o aplica; na produção, aplicar a migration
 * (Sistema › Manutenção) deixa as matrizes e disciplinas iguais.
 *
 * Segurança dos dados: nada é excluído além de vínculos matriz × disciplina que não
 * estão no arquivo e que nenhuma turma usa. Disciplinas, cursos e matrizes ausentes
 * no arquivo ficam como estão (excluir uma disciplina apagaria turmas em cascata).
 */
class CatalogSync
{
    public const FORMAT = 'inova7-catalogo';

    public function export(): array
    {
        $matrices = CurriculumMatrix::with('course')->orderBy('code')->get();

        return [
            'format' => self::FORMAT,
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'courses' => Course::orderBy('code')->get()
                ->map(fn ($c) => $c->only(['code', 'name', 'degree', 'active']))->values()->all(),
            'subjects' => Subject::orderBy('code')->get()
                ->map(fn ($s) => $s->only(['code', 'name', 'abbreviation', 'syllabus', 'total_hours', 'presential_hours', 'online_hours', 'practice_hours', 'extension_hours']))->values()->all(),
            'matrices' => $matrices->map(fn ($m) => [
                'code' => $m->code,
                'course_code' => $m->course?->code,
                'name' => $m->name,
                'version' => $m->version,
                'status' => $m->status,
                'effective_from' => $m->effective_from ? substr((string) $m->effective_from, 0, 10) : null,
                'allowed_for_import' => (bool) $m->allowed_for_import,
                'disciplines' => CurriculumMatrixDiscipline::with('subject')->where('curriculum_matrix_id', $m->id)
                    ->orderBy('period')->get()
                    ->map(fn ($l) => [
                        'subject_code' => $l->subject?->code,
                        'period' => (int) $l->period,
                        'shift' => $l->shift,
                        'weekly_hours' => (int) $l->weekly_hours,
                        'total_hours' => $l->total_hours,
                        'modality' => $l->modality,
                        'offering_type' => $l->offering_type,
                        'allows_shared_class' => (bool) $l->allows_shared_class,
                        'required_room_type' => $l->required_room_type,
                        'max_consecutive_classes' => $l->max_consecutive_classes,
                    ])->filter(fn ($l) => $l['subject_code'])->values()->all(),
                // Estrutura antiga (consultas): disciplina × período × ordem.
                'subjects' => DB::table('curriculum_subject')->join('subjects', 'subjects.id', '=', 'curriculum_subject.subject_id')
                    ->where('curriculum_matrix_id', $m->id)->orderBy('period')->orderBy('sequence')
                    ->get(['subjects.code', 'period', 'sequence'])
                    ->map(fn ($r) => ['subject_code' => $r->code, 'period' => (int) $r->period, 'sequence' => (int) $r->sequence])->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{courses: int, subjects: int, matrices: int, links: int, removed: int, kept: array<int, string>}
     */
    public function apply(array $data): array
    {
        if (($data['format'] ?? null) !== self::FORMAT) {
            throw new \InvalidArgumentException('Arquivo de catálogo inválido.');
        }

        $report = ['courses' => 0, 'subjects' => 0, 'matrices' => 0, 'links' => 0, 'removed' => 0, 'kept' => []];

        DB::transaction(function () use ($data, &$report) {
            $courses = [];
            foreach ($data['courses'] ?? [] as $c) {
                $course = Course::updateOrCreate(['code' => $c['code']], array_intersect_key($c, array_flip(['name', 'degree', 'active'])));
                $courses[$c['code']] = $course->id;
                $report['courses']++;
            }

            $subjects = [];
            foreach ($data['subjects'] ?? [] as $s) {
                if (empty($s['code'])) {
                    continue;
                }
                $subject = Subject::updateOrCreate(['code' => $s['code']], array_diff_key($s, ['code' => true]));
                $subjects[$s['code']] = $subject->id;
                $report['subjects']++;
            }
            $subjectId = fn (?string $code) => $code ? ($subjects[$code] ?? Subject::where('code', $code)->value('id')) : null;

            foreach ($data['matrices'] ?? [] as $m) {
                $courseId = $courses[$m['course_code'] ?? ''] ?? Course::where('code', $m['course_code'] ?? '')->value('id');
                $matrix = CurriculumMatrix::updateOrCreate(['code' => $m['code']], array_filter([
                    'course_id' => $courseId,
                    'name' => $m['name'] ?? null,
                    'version' => $m['version'] ?? null,
                    'status' => $m['status'] ?? null,
                    'effective_from' => $m['effective_from'] ?? null,
                    'allowed_for_import' => $m['allowed_for_import'] ?? null,
                ], fn ($v) => $v !== null));
                $report['matrices']++;

                // Grade da matriz (vínculos usados pela oferta, montagem e resolvedor).
                $keep = [];
                foreach ($m['disciplines'] ?? [] as $l) {
                    $sid = $subjectId($l['subject_code']);
                    if (! $sid) {
                        continue;
                    }
                    $link = CurriculumMatrixDiscipline::updateOrCreate(
                        ['curriculum_matrix_id' => $matrix->id, 'subject_id' => $sid, 'period' => $l['period']],
                        array_diff_key($l, ['subject_code' => true, 'period' => true])
                    );
                    $keep[] = $link->id;
                    $report['links']++;
                }
                $extra = CurriculumMatrixDiscipline::with('subject')->where('curriculum_matrix_id', $matrix->id)->whereNotIn('id', $keep)->get();
                foreach ($extra as $link) {
                    if (ClassOffering::where('curriculum_matrix_discipline_id', $link->id)->exists()) {
                        $report['kept'][] = "{$matrix->name}: {$link->subject?->name} ({$link->period}º) mantida porque há turmas ligadas a ela.";

                        continue;
                    }
                    $link->delete();
                    $report['removed']++;
                }

                // Estrutura antiga (consultas): substitui pela do arquivo só quando ele a traz
                // preenchida; vazia no arquivo não apaga a do destino.
                if (! empty($m['subjects'])) {
                    DB::table('curriculum_subject')->where('curriculum_matrix_id', $matrix->id)->delete();
                    foreach ($m['subjects'] as $row) {
                        if ($sid = $subjectId($row['subject_code'])) {
                            DB::table('curriculum_subject')->insert(['curriculum_matrix_id' => $matrix->id, 'subject_id' => $sid, 'period' => $row['period'], 'sequence' => $row['sequence']]);
                        }
                    }
                }
            }
        });

        return $report;
    }

    public function applyFile(string $path): array
    {
        if (! is_readable($path)) {
            throw new \RuntimeException("Arquivo de catálogo não encontrado: {$path}");
        }

        return $this->apply(json_decode((string) file_get_contents($path), true) ?: []);
    }
}
