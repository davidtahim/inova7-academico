<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use Illuminate\Support\Str;

/**
 * Verifica a consistência do catálogo (cursos × matrizes × turmas) e diz como corrigir.
 * Mostrado em Cadastros › Visão geral.
 */
class CatalogConsistency
{
    /**
     * @return array<int, array{level: string, title: string, detail: string, links: array<int, array{label: string, url: string}>}>
     */
    public function check(): array
    {
        $issues = [];
        $courses = Course::withCount('matrices')->orderBy('name')->get();
        $matrices = CurriculumMatrix::with('course')->withCount('disciplinesForOffering')->orderBy('code')->get();

        // Cursos com o mesmo nome (ex.: "SI" e "GRA-PPC-0228-E" para Sistemas de Informação).
        foreach ($courses->groupBy(fn ($c) => self::key($c->name))->filter(fn ($g) => $g->count() > 1) as $group) {
            $issues[] = [
                'level' => 'danger',
                'title' => 'Curso cadastrado mais de uma vez: ' . $group->first()->name,
                'detail' => 'Códigos ' . $group->pluck('code')->implode(', ') . '. Matrizes e turmas podem ter ficado divididas entre eles. '
                    . 'Mova as matrizes para o curso oficial (tela do curso › "Trazer matriz de outro curso") e depois exclua o duplicado.',
                'links' => $group->map(fn ($c) => ['label' => "{$c->code} ({$c->matrices_count} matriz(es))", 'url' => route('admin.courses.edit', $c)])->values()->all(),
            ];
        }

        // Mesmo código de matriz em cursos diferentes.
        foreach ($matrices->groupBy('code')->filter(fn ($g) => $g->count() > 1) as $code => $group) {
            $issues[] = [
                'level' => 'danger',
                'title' => "Matriz {$code} cadastrada em mais de um curso",
                'detail' => 'Está em: ' . $group->map(fn ($m) => ($m->course?->name ?? 'sem curso') . " ({$m->disciplines_for_offering_count} disciplinas)")->implode('; ')
                    . '. Mantenha só a do curso certo: confira a grade e exclua a outra (se tiver disciplinas, refaça-as na certa antes).',
                'links' => $group->map(fn ($m) => ['label' => 'Editar em ' . ($m->course?->code ?? '?'), 'url' => route('admin.matrices.edit', $m)])->values()->all(),
            ];
        }

        // Curso ativo sem matriz: não aparece na habilitação nem na importação.
        foreach ($courses->where('active', true)->where('matrices_count', 0) as $course) {
            $issues[] = [
                'level' => 'warning',
                'title' => "Curso sem matriz: {$course->name}",
                'detail' => 'Sem matriz, o curso não tem grade curricular: as disciplinas dele não aparecem na habilitação docente, na montagem nem na importação.',
                'links' => [
                    ['label' => 'Vincular matriz', 'url' => route('admin.courses.edit', $course) . '#matrizes'],
                    ['label' => 'Nova matriz para este curso', 'url' => route('admin.matrices.create', ['course' => $course->id])],
                ],
            ];
        }

        // Matriz sem disciplinas.
        foreach ($matrices->where('disciplines_for_offering_count', 0) as $matrix) {
            $issues[] = [
                'level' => 'warning',
                'title' => "Matriz sem disciplinas: {$matrix->name} ({$matrix->code})",
                'detail' => 'Curso ' . ($matrix->course?->name ?? '—') . '. Monte a grade por período para ela ser usada.',
                'links' => [['label' => 'Montar grade', 'url' => route('admin.subjects.index', ['matriz' => $matrix->id])]],
            ];
        }

        // Turmas do semestre ligadas a uma matriz de outro curso.
        if ($term = AcademicTerm::current()) {
            $mismatch = ClassOffering::query()
                ->join('curriculum_matrices', 'curriculum_matrices.id', '=', 'class_offerings.curriculum_matrix_id')
                ->where('class_offerings.academic_term_id', $term->id)
                ->whereColumn('curriculum_matrices.course_id', '!=', 'class_offerings.course_id')
                ->select('class_offerings.class_code')->distinct()->pluck('class_code');
            if ($mismatch->isNotEmpty()) {
                $issues[] = [
                    'level' => 'danger',
                    'title' => "{$mismatch->count()} turma(s) de {$term->code} ligada(s) a matriz de outro curso",
                    'detail' => 'Ex.: ' . $mismatch->take(6)->implode(', ') . '. Geralmente acontece com curso ou matriz duplicados; corrija-os e reimporte a oferta.',
                    'links' => [['label' => 'Importação da oferta', 'url' => route('imports.ubiqua.index')]],
                ];
            }
        }

        return $issues;
    }

    public static function key(?string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $text)));
    }
}
