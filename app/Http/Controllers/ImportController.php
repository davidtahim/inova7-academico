<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Professor;
use App\Models\ScheduleSlot;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\ProfessorAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController extends Controller
{
    public function index()
    {
        $recentLoads = AcademicTerm::select('academic_terms.*')
            ->leftJoin('class_offerings', 'class_offerings.academic_term_id', '=', 'academic_terms.id')
            ->selectRaw('MAX(class_offerings.created_at) as last_imported_at, COUNT(DISTINCT class_offerings.id) as offering_count')
            ->groupBy(
                'academic_terms.id',
                'academic_terms.code',
                'academic_terms.starts_at',
                'academic_terms.ends_at',
                'academic_terms.status',
                'academic_terms.created_at',
                'academic_terms.updated_at'
            )
            ->orderByDesc('last_imported_at')
            ->orderByDesc('academic_terms.id')
            ->get();

        return view('imports.ubiqua', [
            'terms' => AcademicTerm::orderBy('code', 'desc')->get(),
            'recentLoads' => $recentLoads,
        ]);
    }

    public function totvsIndex()
    {
        $recentLoads = AcademicTerm::select('academic_terms.*')
            ->leftJoin('class_offerings', 'class_offerings.academic_term_id', '=', 'academic_terms.id')
            ->selectRaw('MAX(class_offerings.created_at) as last_imported_at, COUNT(DISTINCT class_offerings.id) as offering_count')
            ->groupBy(
                'academic_terms.id',
                'academic_terms.code',
                'academic_terms.starts_at',
                'academic_terms.ends_at',
                'academic_terms.status',
                'academic_terms.created_at',
                'academic_terms.updated_at'
            )
            ->orderByDesc('last_imported_at')
            ->orderByDesc('academic_terms.id')
            ->get();

        return view('imports.totvs', [
            'terms' => AcademicTerm::orderBy('code', 'desc')->get(),
            'recentLoads' => $recentLoads,
        ]);
    }

    public function professorsIndex()
    {
        return view('imports.professors', [
            'terms' => AcademicTerm::orderBy('code', 'desc')->get(),
        ]);
    }

    public function storeProfessors(Request $request)
    {
        $validated = $request->validate([
            'arquivo' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ]);

        $file = $request->file('arquivo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        try {
            if ($extension === 'csv') {
                $rows = $this->parseCsv($file->getRealPath());
            } else {
                if (! class_exists(\ZipArchive::class)) {
                    throw ValidationException::withMessages([
                        'arquivo' => ['Para importar XLSX/XLS, ative a extensão ZIP do PHP (php_zip.dll no Windows ou php-zip no Linux/macOS) e reinicie o servidor.'],
                    ]);
                }

                $rows = $this->parseXlsx($file->getRealPath());
            }
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'arquivo' => ['A planilha de professores não pôde ser lida. Verifique se o arquivo é um CSV/XLSX válido e tente novamente.'],
            ]);
        }

        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages([
                'arquivo' => ['A planilha de professores não contém linhas válidas. Verifique as colunas e tente novamente.'],
            ]);
        }

        $imported = 0;

        foreach ($rows as $row) {
            $normalized = $this->normalizeProfessorRow($row);
            if ($normalized === null) {
                continue;
            }

            $name = trim((string) ($normalized['professor'] ?? $normalized['nome'] ?? ''));
            $registration = trim((string) ($normalized['matricula'] ?? ''));
            $chapa = trim((string) ($normalized['chapa'] ?? ''));
            $lattes = trim((string) ($normalized['lattes'] ?? ''));

            if ($name === '') {
                continue;
            }

            $professor = null;
            if ($registration !== '') {
                $professor = Professor::where('registration', $registration)->first();
            }

            if (! $professor) {
                $professor = Professor::where('name', $name)->first();
            }

            if (! $professor) {
                $professor = new Professor();
            }

            $professor->fill([
                'name' => $name,
                'registration' => $registration !== '' ? $registration : ($professor->registration ?? null),
                'email' => $professor->email ?? null,
                'qualification' => $professor->qualification ?? null,
                'active' => $professor->active ?? true,
                'chapa' => $chapa !== '' ? $chapa : ($professor->chapa ?? null),
                'lattes' => $lattes !== '' ? $lattes : ($professor->lattes ?? null),
            ]);

            $professor->save();
            $imported++;
        }

        return redirect()->route('imports.professors.index')->with('success', "Importação concluída: {$imported} professores registrados atualizados.");
    }

    public function resetTotvs(Request $request)
    {
        $validated = $request->validate([
            'academic_term_code' => ['required', 'string'],
        ]);

        $term = AcademicTerm::where('code', $validated['academic_term_code'])->firstOrFail();

        DB::transaction(function () use ($term) {
            $offeringIds = ClassOffering::where('academic_term_id', $term->id)->pluck('id');
            $courseIds = ClassOffering::where('academic_term_id', $term->id)->pluck('course_id')->unique();
            $subjectIds = ClassOffering::where('academic_term_id', $term->id)->pluck('subject_id')->unique();

            if ($offeringIds->isNotEmpty()) {
                ScheduleSlot::whereIn('class_offering_id', $offeringIds)->delete();
                TeachingAssignment::whereIn('class_offering_id', $offeringIds)->delete();
                ClassOffering::where('academic_term_id', $term->id)->delete();
            }

            $courseIdsToDelete = $courseIds->filter(function ($courseId) {
                return ! ClassOffering::where('course_id', $courseId)->exists();
            })->values();

            $subjectIdsToDelete = $subjectIds->filter(function ($subjectId) {
                return ! ClassOffering::where('subject_id', $subjectId)->exists();
            })->values();

            if ($courseIdsToDelete->isNotEmpty()) {
                CurriculumMatrix::whereIn('course_id', $courseIdsToDelete)->delete();
                Course::whereIn('id', $courseIdsToDelete)->delete();
            }

            if ($subjectIdsToDelete->isNotEmpty()) {
                Subject::whereIn('id', $subjectIdsToDelete)->delete();
            }
        });

        session([
            'totvs_import_progress' => 0,
            'totvs_import_status' => 'Base TOTVS zerada para o semestre selecionado.',
            'totvs_import_finished' => true,
            'totvs_import_started_at' => time(),
            'totvs_import_total_rows' => 0,
        ]);

        return redirect()->route('imports.totvs.index')->with('success', 'Base TOTVS do semestre ' . $term->code . ' zerada com sucesso.');
    }

    public function storeTotvs(Request $request)
    {
        $validated = $request->validate([
            'arquivo' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'academic_term_code' => ['required', 'string'],
        ]);

        session([
            'totvs_import_progress' => 0,
            'totvs_import_status' => 'Lendo planilha...',
            'totvs_import_finished' => false,
            'totvs_import_started_at' => time(),
            'totvs_import_total_rows' => 0,
        ]);

        $term = AcademicTerm::firstOrCreate(
            ['code' => $validated['academic_term_code']],
            ['starts_at' => now()->startOfMonth(), 'ends_at' => now()->addMonths(5), 'status' => 'planning']
        );

        $file = $request->file('arquivo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        try {
            if ($extension === 'csv') {
                $rows = $this->parseCsv($file->getRealPath());
            } else {
                if (! class_exists(\ZipArchive::class)) {
                    throw ValidationException::withMessages([
                        'arquivo' => ['Para importar XLSX/XLS, ative a extensão ZIP do PHP (php_zip.dll no Windows ou php-zip no Linux/macOS) e reinicie o servidor.'],
                    ]);
                }

                $rows = $this->parseXlsx($file->getRealPath());
            }
        } catch (\Throwable $exception) {
            session([
                'totvs_import_progress' => 0,
                'totvs_import_status' => 'Falha ao ler a planilha.',
                'totvs_import_finished' => true,
            ]);

            throw ValidationException::withMessages([
                'arquivo' => ['A base TOTVS não pôde ser lida. Verifique se o arquivo é um CSV/XLSX válido e tente novamente.'],
            ]);
        }

        if (! is_array($rows) || $rows === []) {
            session([
                'totvs_import_progress' => 0,
                'totvs_import_status' => 'Falha ao ler a planilha.',
                'totvs_import_finished' => true,
            ]);

            throw ValidationException::withMessages([
                'arquivo' => ['A base TOTVS não contém linhas válidas. Verifique a estrutura da planilha e tente novamente.'],
            ]);
        }

        $totalRows = count($rows);
        $imported = 0;

        session([
            'totvs_import_total_rows' => $totalRows,
        ]);

        foreach ($rows as $index => $row) {
            $normalized = $this->normalizeTotvsRow($row);
            if ($normalized === null) {
                continue;
            }

            $progress = $totalRows > 0 ? (int) round((($index + 1) / $totalRows) * 100) : 100;
            session([
                'totvs_import_progress' => $progress,
                'totvs_import_status' => 'Processando linhas da planilha...',
                'totvs_import_finished' => false,
            ]);

            $course = $this->ensureCourse($normalized['curso'], $normalized['codigo_curso'] ?? '');
            $matrix = $this->ensureMatrix($course, $normalized['matriz']);
            $subject = $this->ensureSubject($normalized['codigo'] ?? $normalized['disciplina'], $normalized['disciplina']);

            $classCode = $this->resolveTotvsClassCode($normalized, $term, $subject, $index);

            ClassOffering::updateOrCreate(
                [
                    'academic_term_id' => $term->id,
                    'course_id' => $course->id,
                    'subject_id' => $subject->id,
                    'class_code' => $classCode,
                ],
                [
                    'curriculum_matrix_id' => $matrix?->id,
                    'period' => (int) ($normalized['periodo'] ?? 1),
                    'shift' => $this->resolveShift($normalized['turno'] ?? 'MANHA'),
                    'modality' => $this->normalizeModality($normalized['modalidade'] ?? 'PRESENCIAL'),
                    'occurs' => true,
                    'weekly_hours' => (float) ($normalized['carga_horaria'] ?? 2),
                    'totvs_hours' => (float) ($normalized['carga_horaria'] ?? 2),
                    'status' => 'planned',
                ]
            );

            $imported++;
        }

        session([
            'totvs_import_progress' => 100,
            'totvs_import_status' => 'Importação concluída.',
            'totvs_import_finished' => true,
        ]);

        return redirect()->back()->with('success', "Base TOTVS importada com sucesso. {$imported} registros processados para o semestre {$term->code}.");
    }

    public function progressOfertaUbiqua()
    {
        $progress = min((int) session('ubiqua_import_progress', 0), 100);
        $startedAt = session('ubiqua_import_started_at');
        $totalRows = max((int) session('ubiqua_import_total_rows', 0), 1);

        $elapsedSeconds = $startedAt ? max(1, time() - (int) $startedAt) : 1;
        $estimatedRemaining = 0;

        if ($progress > 0 && $progress < 100) {
            $remainingPercent = 100 - $progress;
            $estimatedRemaining = (int) round(($elapsedSeconds / max($progress, 1)) * $remainingPercent);
        }

        return response()->json([
            'progress' => $progress,
            'status' => session('ubiqua_import_status', 'Aguardando início...'),
            'finished' => (bool) session('ubiqua_import_finished', false),
            'estimated_remaining_seconds' => $estimatedRemaining,
            'elapsed_seconds' => $elapsedSeconds,
            'total_rows' => $totalRows,
        ]);
    }

    public function progressTotvs()
    {
        $progress = min((int) session('totvs_import_progress', 0), 100);
        $startedAt = session('totvs_import_started_at');
        $totalRows = max((int) session('totvs_import_total_rows', 0), 1);

        $elapsedSeconds = $startedAt ? max(1, time() - (int) $startedAt) : 1;
        $estimatedRemaining = 0;

        if ($progress > 0 && $progress < 100) {
            $remainingPercent = 100 - $progress;
            $estimatedRemaining = (int) round(($elapsedSeconds / max($progress, 1)) * $remainingPercent);
        }

        return response()->json([
            'progress' => $progress,
            'status' => session('totvs_import_status', 'Aguardando início...'),
            'finished' => (bool) session('totvs_import_finished', false),
            'estimated_remaining_seconds' => $estimatedRemaining,
            'elapsed_seconds' => $elapsedSeconds,
            'total_rows' => $totalRows,
        ]);
    }

    public function resetOfertaUbiqua(Request $request)
    {
        $validated = $request->validate([
            'academic_term_code' => ['required', 'string'],
        ]);

        $term = AcademicTerm::where('code', $validated['academic_term_code'])->firstOrFail();

        DB::transaction(function () use ($term) {
            $offeringIds = ClassOffering::where('academic_term_id', $term->id)->pluck('id');
            $courseIds = ClassOffering::where('academic_term_id', $term->id)->pluck('course_id')->unique();
            $subjectIds = ClassOffering::where('academic_term_id', $term->id)->pluck('subject_id')->unique();

            if ($offeringIds->isNotEmpty()) {
                ScheduleSlot::whereIn('class_offering_id', $offeringIds)->delete();
                TeachingAssignment::whereIn('class_offering_id', $offeringIds)->delete();
                ClassOffering::where('academic_term_id', $term->id)->delete();
            }

            $courseIdsToDelete = $courseIds->filter(function ($courseId) {
                return ! ClassOffering::where('course_id', $courseId)->exists();
            })->values();

            $subjectIdsToDelete = $subjectIds->filter(function ($subjectId) {
                return ! ClassOffering::where('subject_id', $subjectId)->exists();
            })->values();

            if ($courseIdsToDelete->isNotEmpty()) {
                CurriculumMatrix::whereIn('course_id', $courseIdsToDelete)->delete();
                Course::whereIn('id', $courseIdsToDelete)->delete();
            }

            if ($subjectIdsToDelete->isNotEmpty()) {
                Subject::whereIn('id', $subjectIdsToDelete)->delete();
            }
        });

        session([
            'ubiqua_import_progress' => 0,
            'ubiqua_import_status' => 'Oferta zerada para o semestre selecionado.',
            'ubiqua_import_finished' => true,
            'ubiqua_import_started_at' => time(),
            'ubiqua_import_total_rows' => 0,
        ]);

        return redirect()->route('imports.ubiqua.index')->with('success', 'Oferta do semestre ' . $term->code . ' zerada com sucesso.');
    }

    public function storeOfertaUbiqua(Request $request, ProfessorAllocationService $allocationService)
    {
        $validated = $request->validate([
            'arquivo' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'academic_term_code' => ['required', 'string'],
        ]);

        session([
            'ubiqua_import_progress' => 0,
            'ubiqua_import_status' => 'Lendo planilha...',
            'ubiqua_import_finished' => false,
            'ubiqua_import_started_at' => time(),
            'ubiqua_import_total_rows' => 0,
        ]);

        $term = AcademicTerm::firstOrCreate(
            ['code' => $validated['academic_term_code']],
            ['starts_at' => now()->startOfMonth(), 'ends_at' => now()->addMonths(5), 'status' => 'planning']
        );

        $file = $request->file('arquivo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        try {
            if ($extension === 'csv') {
                $rows = $this->parseCsv($file->getRealPath());
            } else {
                if (! class_exists(\ZipArchive::class)) {
                    throw ValidationException::withMessages([
                        'arquivo' => ['Para importar XLSX/XLS, ative a extensão ZIP do PHP (php_zip.dll no Windows ou php-zip no Linux/macOS) e reinicie o servidor.'],
                    ]);
                }

                $rows = $this->parseXlsx($file->getRealPath());
            }
        } catch (\Throwable $exception) {
            session([
                'ubiqua_import_progress' => 0,
                'ubiqua_import_status' => 'Falha ao ler a planilha.',
                'ubiqua_import_finished' => true,
            ]);

            throw ValidationException::withMessages([
                'arquivo' => ['A planilha não pôde ser lida. Verifique se o arquivo é um XLSX/CSV válido e tente novamente.'],
            ]);
        }

        if (! is_array($rows) || $rows === []) {
            session([
                'ubiqua_import_progress' => 0,
                'ubiqua_import_status' => 'Falha ao ler a planilha.',
                'ubiqua_import_finished' => true,
            ]);

            throw ValidationException::withMessages([
                'arquivo' => ['A planilha não pôde ser lida. Verifique se o arquivo é um XLSX/CSV válido e tente novamente.'],
            ]);
        }

        $totalRows = count($rows);
        $imported = 0;
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped_group' => 0,
            'skipped_matrix' => 0,
            'without_matrix_link' => [],
            'unknown_shift' => [],
        ];

        session([
            'ubiqua_import_total_rows' => $totalRows,
        ]);

        foreach ($rows as $index => $row) {
            $normalized = $this->normalizeRow($row);
            if ($normalized === null) {
                continue;
            }

            if ($this->shouldSkipGroup($normalized)) {
                $stats['skipped_group']++;
                continue;
            }

            // "15" ou "Matriz 15" na planilha → código cadastrado (ex.: GRA-MAT-0228-F).
            $knownCourse = $this->findExistingCourse($normalized);
            if ($knownCourse && ($knownMatrix = $this->findExistingMatrix($knownCourse, (string) $normalized['matriz']))) {
                $normalized['matriz'] = $knownMatrix->code;
            }

            $allowedMatrixCodes = $this->loadAllowedMatrixCodesForCourse($normalized);
            if (! empty($allowedMatrixCodes) && $this->shouldSkipMatrix($normalized, $allowedMatrixCodes)) {
                $stats['skipped_matrix']++;
                continue;
            }

            $progress = $totalRows > 0 ? (int) round((($index + 1) / $totalRows) * 100) : 100;
            session([
                'ubiqua_import_progress' => $progress,
                'ubiqua_import_status' => 'Processando linhas da planilha...',
                'ubiqua_import_finished' => false,
            ]);

            $course = $this->ensureCourse($normalized['curso'], $normalized['codigo_curso'] ?? '');
            $matrix = $this->ensureMatrix($course, $normalized['matriz']);
            $subject = $this->ensureSubject($normalized['codigo'], $normalized['disciplina']);
            $matrixLink = $this->findMatrixLink($matrix, $subject, $normalized);

            if ($matrix && ! $matrixLink) {
                $stats['without_matrix_link'][$subject->code] = true;
            }

            $shift = $this->resolveShiftOrNull($normalized['turno'] ?? '');
            if ($shift === null) {
                if (trim((string) ($normalized['turno'] ?? '')) !== '') {
                    $stats['unknown_shift'][Str::upper(trim($normalized['turno']))] = true;
                }
                $shift = $matrixLink?->shift ?? 'MANHÃ';
            }

            $period = ! $normalized['periodo_informado'] && $matrixLink
                ? (int) $matrixLink->period
                : (int) ($normalized['periodo'] ?? 1);

            // "Hora Aula Semanal" da planilha manda; sem ela, usa a carga do vínculo na matriz ou a H.A. Classis.
            $weeklyHours = $normalized['hora_aula_semanal']
                ?? ($matrixLink?->weekly_hours ? (float) $matrixLink->weekly_hours : null)
                ?? (float) ($normalized['carga_horaria'] ?? 2);

            $explicitClassCode = trim((string) ($normalized['turma'] ?? ''));
            $classCode = $this->resolveClassCode(
                $explicitClassCode ?: trim((string) ($normalized['codigo'] ?? '')),
                $term,
                $subject,
                $normalized,
                $imported + 1,
                $explicitClassCode !== ''
            );

            $attributes = [
                'curriculum_matrix_id' => $matrix?->id,
                'curriculum_matrix_discipline_id' => $matrixLink?->id,
                'period' => $period,
                'shift' => $shift,
                'modality' => $this->normalizeModality($normalized['modalidade'] ?? 'PRESENCIAL'),
                'occurs' => true,
                'weekly_hours' => $weeklyHours,
                'totvs_hours' => (float) ($normalized['carga_horaria'] ?? $weeklyHours),
                'status' => 'planned',
            ];

            if ($normalized['quantidade_alunos'] !== null) {
                $attributes['student_count'] = $normalized['quantidade_alunos'];
            }

            $offering = ClassOffering::updateOrCreate(
                [
                    'academic_term_id' => $term->id,
                    'course_id' => $course->id,
                    'class_code' => $classCode,
                    'subject_id' => $subject->id,
                ],
                $attributes
            );

            $offering->wasRecentlyCreated ? $stats['created']++ : $stats['updated']++;

            if (! empty($normalized['professor'])) {
                $professor = $this->ensureProfessor($normalized['professor']);
                TeachingAssignment::updateOrCreate(
                    [
                        'class_offering_id' => $offering->id,
                        'professor_id' => $professor->id,
                    ],
                    [
                        'weekly_hours' => $weeklyHours,
                        'status' => 'planned',
                    ]
                );
            }

            $imported++;
        }

        // Sugestão automática de professores: uma única vez, ao final da importação.
        $allocationService->allocateForTerm($term->id);

        session([
            'ubiqua_import_progress' => 100,
            'ubiqua_import_status' => 'Importação concluída.',
            'ubiqua_import_finished' => true,
        ]);

        $message = $this->buildUbiquaSummary($imported, $stats);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'redirect' => route('imports.ubiqua.index'),
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('warning', $message);
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        $firstLine = fgets($handle);
        $delimiter = ';';

        if ($firstLine !== false) {
            $score = [
                ';' => substr_count($firstLine, ';'),
                ',' => substr_count($firstLine, ','),
                '\t' => substr_count($firstLine, "\t"),
            ];

            $delimiter = array_search(max($score), $score, true) ?: ';';
        }

        rewind($handle);

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null] || count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }

            $rows[] = array_map(fn($value) => trim((string) $value), $data);
        }

        fclose($handle);

        return $this->buildNormalizedRows($rows);
    }

    private function parseXlsx(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();

                if (is_object($value) && method_exists($value, 'getPlainText')) {
                    $value = $value->getPlainText();
                }

                $cells[] = trim((string) $value);
            }

            $values = array_values($cells);
            if ($values === []) {
                continue;
            }

            $rows[] = $values;
        }

        $spreadsheet->garbageCollect();

        return $this->buildNormalizedRows($rows);
    }

    private function buildNormalizedRows(array $rows): array
    {
        $headerIndex = null;
        foreach ($rows as $index => $values) {
            if (! is_array($values) || count($values) < 2) {
                continue;
            }

            $matchedHeaders = 0;
            foreach ($values as $value) {
                $normalized = $this->normalizeHeader((string) $value);
                if (in_array($normalized, ['curso', 'periodo', 'disciplina', 'matriz', 'carga_horaria', 'hora_aula_semanal', 'quantidade_alunos', 'codigo_turma', 'modalidade', 'professor', 'nome', 'matricula', 'chapa', 'lattes'], true)) {
                    $matchedHeaders++;
                }
            }

            if ($matchedHeaders >= 2) {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            return [];
        }

        $header = array_map(fn($value) => $this->normalizeHeader($value), $rows[$headerIndex]);
        $headerCount = count($header);
        $normalizedRows = [];

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $values = array_values($rows[$i]);
            if ($values === []) {
                continue;
            }

            $values = array_slice($values, 0, $headerCount);
            if (count($values) < $headerCount) {
                $values = array_pad($values, $headerCount, '');
            }

            if (count($values) !== $headerCount) {
                continue;
            }

            $row = array_combine($header, $values);
            if (is_array($row) && ! empty($row)) {
                $normalizedRows[] = $row;
            }
        }

        return array_values(array_filter($normalizedRows, fn($row) => is_array($row) && ! empty($row)));
    }

    private function normalizeHeader(string $value): string
    {
        $normalized = Str::ascii(Str::upper(trim((string) $value)));
        $normalized = preg_replace('/[^A-Z0-9]+/', '_', $normalized);
        $normalized = trim((string) preg_replace('/_+/', '_', (string) $normalized), '_');

        $aliases = [
            'CURSO' => 'curso',
            'PERIODO' => 'periodo',
            'DISCIPLINA' => 'disciplina',
            'CODIGO' => 'codigo',
            'COD_DA_DISCIPLINA' => 'codigo',
            'COD_DISCIPLINA' => 'codigo',
            'CODIGO_DA_DISCIPLINA' => 'codigo',
            'CODIGO_DISCIPLINA' => 'codigo',
            'DISCIPLINA_CODIGO' => 'codigo',
            'CODIGO_DO_CURSO' => 'codigo_curso',
            'CODIGO_CURSO' => 'codigo_curso',
            'CURSO_CODIGO' => 'codigo_curso',
            'CODIGO_DA_TURMA' => 'codigo_turma',
            'CODIGO_TURMA' => 'codigo_turma',
            'COD_DA_TURMA' => 'codigo_turma',
            'TURMA_CODIGO' => 'codigo_turma',
            'CLASS_CODE' => 'codigo_turma',
            'TURMA' => 'turma',
            'MATRIZ' => 'matriz',
            'GRUPO' => 'grupo',
            'GRUPO_ACADEMICO' => 'grupo',
            'NOME_DO_GRUPO' => 'grupo',
            'UNIDADE' => 'grupo',
            'UNIDADE_ACADEMICA' => 'grupo',
            'NOME_DA_UNIDADE' => 'grupo',
            'H_A_CLASSIS_PAGAMENTO' => 'carga_horaria',
            'HA_CLASSIS_PAGAMENTO' => 'carga_horaria',
            'HA_CLASSIS' => 'carga_horaria',
            'H_A_CLASSIS' => 'carga_horaria',
            'MODALIDADE' => 'modalidade',
            'NOME_DO_CURSO' => 'curso',
            'NOME_DA_DISCIPLINA' => 'disciplina',
            'NOME_DISCIPLINA' => 'disciplina',
            'NOME_DA_MATRIZ' => 'matriz',
            'CARGA_HORARIA' => 'carga_horaria',
            'CH' => 'carga_horaria',
            'HORA_AULA_SEMANAL' => 'hora_aula_semanal',
            'HORAS_AULA_SEMANAL' => 'hora_aula_semanal',
            'HORA_AULA_SEMANAIS' => 'hora_aula_semanal',
            'HORAS_AULA_SEMANAIS' => 'hora_aula_semanal',
            'H_A_SEMANAL' => 'hora_aula_semanal',
            'HA_SEMANAL' => 'hora_aula_semanal',
            'AULAS_SEMANAIS' => 'hora_aula_semanal',
            'AULAS_POR_SEMANA' => 'hora_aula_semanal',
            'CH_SEMANAL' => 'hora_aula_semanal',
            'CARGA_HORARIA_SEMANAL' => 'hora_aula_semanal',
            'QUANTIDADE_DE_ALUNOS' => 'quantidade_alunos',
            'QUANTIDADE_ALUNOS' => 'quantidade_alunos',
            'QTD_ALUNOS' => 'quantidade_alunos',
            'QTD_DE_ALUNOS' => 'quantidade_alunos',
            'QTDE_ALUNOS' => 'quantidade_alunos',
            'QTDE_DE_ALUNOS' => 'quantidade_alunos',
            'N_DE_ALUNOS' => 'quantidade_alunos',
            'NUMERO_DE_ALUNOS' => 'quantidade_alunos',
            'ALUNOS' => 'quantidade_alunos',
            'MATRICULADOS' => 'quantidade_alunos',
            'VAGAS' => 'quantidade_alunos',
            'HABILITACAO' => 'habilitacao',
            'HABILITAÇÃO' => 'habilitacao',
            'PROFESSOR' => 'professor',
            'NOME' => 'nome',
            'NOME_PROFESSOR' => 'professor',
            'MATRICULA' => 'matricula',
            'MATRICULA_PROFESSOR' => 'matricula',
            'CHAPA' => 'chapa',
            'LATTES' => 'lattes',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        $variantMatches = [
            'NOME_DA_DISCIPLINA' => 'disciplina',
            'DISCIPLINA_NOME' => 'disciplina',
            'NOME_DISCIPLINA' => 'disciplina',
            'NOME_CURSO' => 'curso',
            'CURSO_NOME' => 'curso',
            'NOME_MATRIZ' => 'matriz',
            'MATRIZ_NOME' => 'matriz',
            'PERIODO_DISCIPLINA' => 'periodo',
            'PERIODO_DA_DISCIPLINA' => 'periodo',
            'PERIODO_CURSO' => 'periodo',
            'CODIGO_DO_CURSO' => 'codigo_curso',
            'CODIGO_CURSO' => 'codigo_curso',
            'CURSO_CODIGO' => 'codigo_curso',
            'H_A_CLASSIS_PAGAMENTO' => 'carga_horaria',
            'HA_CLASSIS_PAGAMENTO' => 'carga_horaria',
            'H_A_CLASSIS' => 'carga_horaria',
            'HA_CLASSIS' => 'carga_horaria',
            'COD_DISCIPLINA' => 'codigo',
            'COD_DA_DISCIPLINA' => 'codigo',
            'COD_DA_TURMA' => 'codigo_turma',
            'COD_TURMA' => 'codigo_turma',
            'NOME_DA_TURMA' => 'turma',
            'HABILITACAO' => 'habilitacao',
        ];

        return $variantMatches[$normalized] ?? Str::lower($normalized);
    }

    private function normalizeProfessorRow(?array $row): ?array
    {
        if (! is_array($row) || empty($row)) {
            return null;
        }

        $keys = [
            'professor' => ['professor', 'PROFESSOR', 'nome', 'NOME', 'nome_professor', 'NOME_PROFESSOR'],
            'matricula' => ['matricula', 'MATRICULA', 'matrícula', 'MATRICULA_PROFESSOR', 'registration'],
            'chapa' => ['chapa', 'CHAPA', 'numero_chapa', 'NUMERO_CHAPA'],
            'lattes' => ['lattes', 'LATTES', 'url_lattes', 'URL_LATTES'],
        ];

        $normalized = [];
        foreach ($keys as $key => $candidates) {
            foreach ($candidates as $candidate) {
                if (array_key_exists($candidate, $row)) {
                    $value = trim((string) $row[$candidate]);
                    if ($value !== '') {
                        $normalized[$key] = $value;
                        break;
                    }
                }
            }
        }

        $name = trim((string) ($normalized['professor'] ?? ''));
        if ($name === '') {
            return null;
        }

        return [
            'professor' => $name,
            'matricula' => trim((string) ($normalized['matricula'] ?? '')),
            'chapa' => trim((string) ($normalized['chapa'] ?? '')),
            'lattes' => trim((string) ($normalized['lattes'] ?? '')),
        ];
    }

    private function normalizeTotvsRow(?array $row): ?array
    {
        if (! is_array($row) || empty($row)) {
            return null;
        }

        $normalized = $this->normalizeRow($row);
        if ($normalized === null) {
            return null;
        }

        $normalized['codigo_curso'] = trim((string) ($row['codigo_curso'] ?? $row['CODIGO_CURSO'] ?? $row['CODIGO_DO_CURSO'] ?? $row['CURSO_CODIGO'] ?? ''));
        $normalized['turma'] = trim((string) ($row['turma'] ?? $row['TURMA'] ?? $row['codigo_turma'] ?? $row['CODIGO_TURMA'] ?? $row['CODIGO_DA_TURMA'] ?? $row['CLASS_CODE'] ?? $normalized['turma'] ?? ''));
        $normalized['turno'] = trim((string) ($row['turno'] ?? $row['TURNO'] ?? $row['SHIFT'] ?? ''));
        $normalized['modalidade'] = trim((string) ($row['modalidade'] ?? $row['MODALIDADE'] ?? ''));

        if ($normalized['curso'] === '') {
            return null;
        }

        if ($normalized['disciplina'] === '') {
            return null;
        }

        return $normalized;
    }

    private function resolveTotvsClassCode(array $normalized, AcademicTerm $term, Subject $subject, int $rowIndex): string
    {
        $candidate = trim((string) ($normalized['turma'] ?? ''));

        if ($candidate === '' || $this->looksLikeCourseName($candidate)) {
            $candidate = sprintf(
                'TOTVS-%s-%s-%s',
                $term->code,
                $subject->id,
                $rowIndex + 1
            );
        }

        $exists = ClassOffering::where('academic_term_id', $term->id)
            ->where('subject_id', $subject->id)
            ->where('class_code', $candidate)
            ->exists();

        if (! $exists) {
            return $candidate;
        }

        return sprintf('%s-%s', $candidate, $rowIndex + 1);
    }

    private function normalizeRow(?array $row): ?array
    {
        if (! is_array($row) || empty($row)) {
            return null;
        }

        $row = array_filter($row, fn($value) => $value !== null && trim((string) $value) !== '');
        if ($row === []) {
            return null;
        }

        $codigoTurma = trim((string) ($row['codigo_turma'] ?? $row['CODIGO_TURMA'] ?? $row['CODIGO_DA_TURMA'] ?? $row['CLASS_CODE'] ?? ''));
        if ($codigoTurma !== '') {
            $row['turma'] = $codigoTurma;
        }

        $curso = trim((string) ($row['curso'] ?? $row['CURSO'] ?? ''));
        $codigoCurso = trim((string) ($row['codigo_curso'] ?? $row['CODIGO_CURSO'] ?? $row['CODIGO_DO_CURSO'] ?? $row['CURSO_CODIGO'] ?? ''));
        $disciplina = trim((string) ($row['disciplina'] ?? $row['DISCIPLINA'] ?? ''));
        $codigo = trim((string) ($row['codigo'] ?? $row['CODIGO'] ?? ''));
        $matriz = trim((string) ($row['matriz'] ?? $row['MATRIZ'] ?? ''));
        $turma = trim((string) ($row['turma'] ?? $row['TURMA'] ?? $row['codigo_turma'] ?? $row['CODIGO_TURMA'] ?? $row['CODIGO_DA_TURMA'] ?? $row['CLASS_CODE'] ?? ''));
        $turno = trim((string) ($row['turno'] ?? $row['TURNO'] ?? ''));
        $modalidade = trim((string) ($row['modalidade'] ?? $row['MODALIDADE'] ?? ''));
        $professor = trim((string) ($row['professor'] ?? $row['PROFESSOR'] ?? ''));
        $grupo = trim((string) ($row['grupo'] ?? $row['GRUPO'] ?? $row['UNIDADE'] ?? $row['UNIDADE_ACADEMICA'] ?? $row['NOME_DO_GRUPO'] ?? ''));

        if ($periodo = ($row['periodo'] ?? $row['PERIODO'] ?? null)) {
            $row['periodo'] = $periodo;
        }
        if ($cargaHoraria = ($row['carga_horaria'] ?? $row['CARGA_HORARIA'] ?? $row['HA_CLASSIS_PAGAMENTO'] ?? $row['H_A_CLASSIS_PAGAMENTO'] ?? null)) {
            $row['carga_horaria'] = $cargaHoraria;
        }

        if ($curso === '' && isset($row['CURSO'])) {
            $curso = trim((string) $row['CURSO']);
        }
        if ($disciplina === '' && isset($row['DISCIPLINA'])) {
            $disciplina = trim((string) $row['DISCIPLINA']);
        }
        if ($codigo === '' && isset($row['CODIGO'])) {
            $codigo = trim((string) $row['CODIGO']);
        }
        if ($matriz === '' && isset($row['MATRIZ'])) {
            $matriz = trim((string) $row['MATRIZ']);
        }

        if ($curso === '' && $disciplina === '' && $codigo === '' && $matriz === '') {
            return null;
        }

        $periodoInformado = trim((string) ($row['periodo'] ?? $row['PERIODO'] ?? '')) !== '';
        $periodo = $this->toNumericValue($row['periodo'] ?? $row['PERIODO'] ?? '1');
        $horaAulaSemanal = isset($row['hora_aula_semanal']) ? $this->toNumericValue($row['hora_aula_semanal']) : null;
        $quantidadeAlunos = isset($row['quantidade_alunos']) ? (int) $this->toNumericValue($row['quantidade_alunos']) : null;
        $cargaHoraria = $this->toNumericValue($row['carga_horaria'] ?? $row['CARGA_HORARIA'] ?? $row['CH'] ?? $row['HA_CLASSIS_PAGAMENTO'] ?? $row['H_A_CLASSIS_PAGAMENTO'] ?? '2');

        return [
            'curso' => $curso,
            'codigo_curso' => $codigoCurso,
            'matriz' => $matriz,
            'disciplina' => $disciplina ?: $codigo,
            'codigo' => $codigo,
            'periodo' => (int) $periodo,
            'periodo_informado' => $periodoInformado,
            'turma' => $turma,
            'turno' => $turno,
            'modalidade' => $modalidade,
            'professor' => $professor,
            'grupo' => $grupo,
            'carga_horaria' => (float) $cargaHoraria,
            'hora_aula_semanal' => $horaAulaSemanal !== null && $horaAulaSemanal > 0 ? (float) $horaAulaSemanal : null,
            'quantidade_alunos' => $quantidadeAlunos !== null && $quantidadeAlunos > 0 ? $quantidadeAlunos : null,
        ];
    }

    private function shouldSkipGroup(array $normalized): bool
    {
        $grupo = strtolower(trim((string) ($normalized['grupo'] ?? '')));
        if ($grupo === '') {
            return false;
        }

        $matchesUni7 = preg_match('/\buni\s*7\b|\buni7\b|\binova7\b|\buniversidade\s*inova\b|\buni-7\b/i', $grupo) === 1;

        return ! $matchesUni7;
    }

    private function shouldSkipMatrix(array $normalized, ?array $allowedMatrixCodes = null): bool
    {
        $matrixCode = $this->normalizeImportCode((string) ($normalized['matriz'] ?? ''));
        if ($matrixCode === '') {
            return false;
        }

        $allowedCodes = array_values(array_unique(array_filter(array_map(function ($value) {
            return $this->normalizeImportCode((string) $value);
        }, $allowedMatrixCodes ?? []), fn($value) => $value !== '')));

        if ($allowedCodes === []) {
            return false;
        }

        $hasGraMatrixCode = collect($allowedCodes)->contains(fn($code) => preg_match('/^GRA.*MAT/i', $code) === 1);
        if (! $hasGraMatrixCode) {
            return false;
        }

        return ! in_array($matrixCode, $allowedCodes, true);
    }

    private function normalizeImportCode(string $value): string
    {
        $normalized = Str::upper(trim($value));

        return preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? '';
    }

    private function findExistingCourse(array $normalized): ?Course
    {
        $courseCode = $this->normalizeImportCode((string) ($normalized['codigo_curso'] ?? ''));
        $courseName = trim((string) ($normalized['curso'] ?? ''));

        if ($courseCode !== '') {
            $course = Course::all()->first(fn (Course $c) => $this->normalizeImportCode((string) $c->code) === $courseCode);
            if ($course) {
                return $course;
            }
        }

        if ($courseName !== '') {
            $wanted = Str::lower(Str::ascii($courseName));

            return Course::all()->first(fn (Course $c) => Str::lower(Str::ascii(trim((string) $c->name))) === $wanted);
        }

        return null;
    }

    private function loadAllowedMatrixCodesForCourse(array $normalized): array
    {
        $course = $this->findExistingCourse($normalized);

        if (! $course) {
            return [];
        }

        return CurriculumMatrix::where('course_id', $course->id)
            ->where('allowed_for_import', true)
            ->pluck('code')
            ->map(fn($code) => strtoupper(trim((string) $code)))
            ->filter(fn($code) => $code !== '')
            ->values()
            ->all();
    }

    private function toNumericValue(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9,\.]/', '', (string) $value);

        if ($clean === '') {
            return 2.0;
        }

        return (float) str_replace(',', '.', $clean);
    }

    private function ensureCourse(string $name, string $codeFromSheet = ''): Course
    {
        $explicitCode = strtoupper(trim($codeFromSheet));

        // Sem código na planilha: reaproveita o curso já cadastrado com o mesmo nome
        // (ignorando maiúsculas e acentos) antes de derivar um código a partir do nome.
        if ($explicitCode === '' && trim($name) !== '') {
            $wanted = Str::lower(Str::ascii(trim($name)));
            $existing = Course::all()->first(fn (Course $course) => Str::lower(Str::ascii(trim((string) $course->name))) === $wanted);
            if ($existing) {
                return $existing;
            }
        }

        $code = $explicitCode !== '' ? $explicitCode : $this->deriveCourseCodeFromName($name);

        $course = Course::firstOrCreate(['code' => $code], [
            'name' => $name ?: 'Sistemas de Informação',
            'degree' => 'Bacharelado',
            'active' => true,
        ]);

        if ($course->name !== $name && trim($name) !== '') {
            $course->name = $name;
            $course->save();
        }

        return $course;
    }

    private function deriveCourseCodeFromName(string $name): string
    {
        $slug = strtoupper(Str::slug($name, ''));

        if ($slug === '') {
            return 'SI';
        }

        if (preg_match('/^[A-Z]+$/', $slug) === 1 && strlen($slug) >= 8) {
            return 'SI';
        }

        return $slug ?: 'SI';
    }

    private function ensureMatrix(Course $course, string $matrixCode): ?CurriculumMatrix
    {
        if (trim($matrixCode) === '') {
            return null;
        }

        $existing = $this->findExistingMatrix($course, $matrixCode);
        if ($existing) {
            return $existing;
        }

        return CurriculumMatrix::firstOrCreate(
            ['course_id' => $course->id, 'code' => trim($matrixCode)],
            ['name' => trim($matrixCode), 'version' => 'importado', 'status' => 'Atual']
        );
    }

    /**
     * Localiza a matriz já cadastrada do curso a partir do valor da planilha, aceitando
     * o código completo ("GRA-MAT-0228-F", com ou sem separadores), o nome ("Matriz 15")
     * ou apenas o número ("15").
     */
    private function findExistingMatrix(Course $course, string $value): ?CurriculumMatrix
    {
        $wanted = $this->normalizeImportCode($value);
        if ($wanted === '') {
            return null;
        }

        $matrices = CurriculumMatrix::where('course_id', $course->id)->get();

        $byCode = $matrices->first(fn (CurriculumMatrix $m) => $this->normalizeImportCode((string) $m->code) === $wanted);
        if ($byCode) {
            return $byCode;
        }

        $byName = $matrices->first(fn (CurriculumMatrix $m) => $this->normalizeImportCode((string) $m->name) === $wanted);
        if ($byName) {
            return $byName;
        }

        $number = preg_match('/^(?:MATRIZ)?0*(\d+)$/', $wanted, $match) === 1 ? $match[1] : null;
        if ($number === null) {
            return null;
        }

        $byNumber = $matrices->filter(function (CurriculumMatrix $m) use ($number) {
            return preg_match('/^MATRIZ0*(\d+)$/', $this->normalizeImportCode((string) $m->name), $nameMatch) === 1
                && $nameMatch[1] === $number;
        });

        return $byNumber->count() === 1 ? $byNumber->first() : null;
    }

    /**
     * Vínculo matriz ↔ disciplina que originou a oferta (tipo de sala, aula compartilhada,
     * máximo de aulas consecutivas). Com período informado, prefere o vínculo daquele período.
     */
    private function findMatrixLink(?CurriculumMatrix $matrix, Subject $subject, array $normalized): ?CurriculumMatrixDiscipline
    {
        if (! $matrix) {
            return null;
        }

        $links = CurriculumMatrixDiscipline::where('curriculum_matrix_id', $matrix->id)
            ->where('subject_id', $subject->id)
            ->orderBy('period')
            ->get();

        if ($links->isEmpty()) {
            return null;
        }

        if (! empty($normalized['periodo_informado'])) {
            return $links->firstWhere('period', (int) $normalized['periodo']) ?? $links->first();
        }

        return $links->first();
    }

    private function buildUbiquaSummary(int $imported, array $stats): string
    {
        $parts = ["Importação concluída: {$imported} ofertas processadas ({$stats['created']} novas, {$stats['updated']} atualizadas)."];

        if ($stats['skipped_group'] > 0) {
            $parts[] = "{$stats['skipped_group']} linha(s) de outros grupos ignoradas (importação limitada ao grupo Uni7).";
        }
        if ($stats['skipped_matrix'] > 0) {
            $parts[] = "{$stats['skipped_matrix']} linha(s) de matrizes não permitidas ignoradas.";
        }
        if ($stats['without_matrix_link'] !== []) {
            $codes = array_keys($stats['without_matrix_link']);
            $sample = implode(', ', array_slice($codes, 0, 5)) . (count($codes) > 5 ? '…' : '');
            $parts[] = count($codes) . " disciplina(s) sem vínculo na matriz curricular ({$sample}): tipo de sala e regras de grade não serão aplicados até o vínculo ser cadastrado.";
        }
        if ($stats['unknown_shift'] !== []) {
            $parts[] = 'Turno(s) não reconhecido(s): ' . implode(', ', array_keys($stats['unknown_shift'])) . ' — foi usado o turno da matriz (ou MANHÃ).';
        }

        return implode(' ', $parts);
    }

    private function ensureSubject(string $code, string $name): Subject
    {
        $subjectCode = trim($code) !== '' ? $code : strtoupper(Str::slug($name, ''));
        $subject = Subject::firstOrCreate(['code' => $subjectCode], [
            'name' => $name ?: 'Disciplina importada',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        return $subject;
    }

    private function ensureProfessor(string $name): Professor
    {
        $cleanName = trim($name);

        return Professor::firstOrCreate(
            ['name' => $cleanName],
            ['registration' => null, 'email' => null, 'qualification' => 'Importado', 'active' => true]
        );
    }

    private function resolveClassCode(string $fallbackCode, AcademicTerm $term, Subject $subject, array $normalized, int $rowIndex, bool $explicit = false): string
    {
        $candidate = trim($fallbackCode);

        // Código de turma real (coluna "Código da Turma"): identifica a oferta. Reimportar a
        // mesma planilha atualiza a turma em vez de criar uma cópia com sufixo.
        if ($explicit && $candidate !== '' && ! $this->looksLikeCourseName($candidate)) {
            return $candidate;
        }

        if ($candidate === '' || $this->looksLikeCourseName($candidate)) {
            $candidate = sprintf(
                'IMPORTADO-%s-%s-%s-%s',
                $subject->id,
                $term->id,
                (int) ($normalized['periodo'] ?? 1),
                $rowIndex
            );
        }

        $exists = ClassOffering::where('academic_term_id', $term->id)
            ->where('subject_id', $subject->id)
            ->where('class_code', $candidate)
            ->exists();

        if (! $exists) {
            return $candidate;
        }

        return sprintf('%s-%s', $candidate, $rowIndex);
    }

    private function looksLikeCourseName(string $value): bool
    {
        $value = strtoupper(trim($value));

        if ($value === '') {
            return true;
        }

        if (preg_match('/[0-9]/', $value) === 1) {
            return false;
        }

        return preg_match('/^[A-Z]+$/', $value) === 1 && strlen($value) >= 8;
    }

    private function resolveShiftOrNull(string $value): ?string
    {
        $key = Str::upper(Str::ascii(trim($value)));
        $map = [
            'MANHA' => 'MANHÃ',
            'MATUTINO' => 'MANHÃ',
            'M' => 'MANHÃ',
            'TARDE' => 'TARDE',
            'VESPERTINO' => 'TARDE',
            'T' => 'TARDE',
            'NOITE' => 'NOITE',
            'NOTURNO' => 'NOITE',
            'N' => 'NOITE',
            'INTEGRAL' => 'INTEGRAL',
            'MANHA_E_TARDE' => 'INTEGRAL',
            'MANHA/TARDE' => 'INTEGRAL',
            'MANHA E TARDE' => 'INTEGRAL',
            'DIURNO' => 'INTEGRAL',
        ];

        return $map[$key] ?? null;
    }

    private function resolveShift(string $value): string
    {
        return $this->resolveShiftOrNull($value) ?? 'MANHÃ';
    }

    private function normalizeModality(string $value): string
    {
        $value = Str::upper(trim($value));
        $map = [
            'PRESENCIAL' => 'PRESENCIAL',
            'HÍBRIDA' => 'HÍBRIDA',
            'HIBRIDA' => 'HÍBRIDA',
            'DOL' => 'DOL',
            'NAVEGA' => 'NAVEGA',
            'NOTAVEL MESTRE' => 'NOTÁVEL MESTRE',
            'NOTÁVEL MESTRE' => 'NOTÁVEL MESTRE',
            'EXTENSAO' => 'EXTENSÃO',
            'EXTENSÃO' => 'EXTENSÃO',
            'ESTAGIO' => 'ESTÁGIO',
            'ESTÁGIO' => 'ESTÁGIO',
        ];

        return $map[$value] ?? 'PRESENCIAL';
    }
}
