<?php

namespace App\Http\Middleware;

use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Support\CourseScope;
use Closure;
use Illuminate\Http\Request;

/**
 * Coordenação só vê e altera os próprios cursos: barra qualquer rota ou filtro que aponte para curso,
 * matriz ou turma de outro curso (parâmetros da rota e campos course, course_id e matrix).
 */
class EnsureCourseScope
{
    public function handle(Request $request, Closure $next)
    {
        if (! CourseScope::restricted()) {
            return $next($request);
        }

        $courseIds = [];
        $route = $request->route();
        foreach (['course' => Course::class, 'matrix' => CurriculumMatrix::class, 'offering' => ClassOffering::class, 'discipline' => CurriculumMatrixDiscipline::class] as $param => $model) {
            $value = $route?->parameter($param);
            if ($value === null) {
                continue;
            }
            $record = $value instanceof $model ? $value : $model::find($value);
            $courseIds[] = match ($param) {
                'course' => $record?->id,
                'matrix', 'offering' => $record?->course_id,
                'discipline' => $record ? CurriculumMatrix::whereKey($record->curriculum_matrix_id)->value('course_id') : null,
            };
        }
        foreach (['course', 'course_id'] as $field) {
            if (is_numeric($request->input($field))) {
                $courseIds[] = (int) $request->input($field);
            }
        }
        if (is_numeric($request->input('offering_id'))) {
            $courseIds[] = ClassOffering::whereKey((int) $request->input('offering_id'))->value('course_id');
        }
        if (is_numeric($request->input('matrix'))) {
            $courseIds[] = CurriculumMatrix::whereKey((int) $request->input('matrix'))->value('course_id');
        }

        foreach ($courseIds as $id) {
            if ($id !== null && ! CourseScope::allows((int) $id)) {
                abort(403, 'Este curso não está entre os seus. Peça ao Administrador de TI para vincular você ao curso.');
            }
        }

        return $next($request);
    }
}
