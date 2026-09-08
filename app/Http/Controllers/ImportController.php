<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\Professor;
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

        $totalRows = count($rows);
        $imported = 0;

        session([
            'ubiqua_import_total_rows' => $totalRows,
        ]);

        foreach ($rows as $index => $row) {
            $normalized = $this->normalizeRow($row);
            if ($normalized === null) {
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

            $classCode = $this->resolveClassCode(
                trim((string) ($normalized['turma'] ?? '')) ?: trim((string) ($normalized['codigo'] ?? '')),
                $term,
                $subject,
                $normalized,
                $imported + 1
            );

            $offering = ClassOffering::updateOrCreate(
                [
                    'academic_term_id' => $term->id,
                    'course_id' => $course->id,
                    'class_code' => $classCode,
                    'subject_id' => $subject->id,
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

            if (! empty($normalized['professor'])) {
                $professor = $this->ensureProfessor($normalized['professor']);
                TeachingAssignment::updateOrCreate(
                    [
                        'class_offering_id' => $offering->id,
                        'professor_id' => $professor->id,
                    ],
                    [
                        'weekly_hours' => (float) ($normalized['carga_horaria'] ?? 2),
                        'status' => 'planned',
                    ]
                );
            }

            $allocationService->allocateForTerm($term->id);
            $imported++;
        }

        $allocationService->allocateForTerm($term->id);

        session([
            'ubiqua_import_progress' => 100,
            'ubiqua_import_status' => 'Importação concluída.',
            'ubiqua_import_finished' => true,
        ]);

        $message = "Importação concluída: {$imported} ofertas processadas. A importação foi limitada ao grupo Uni7; registros de outros grupos foram ignorados.";

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
                if (in_array($normalized, ['curso', 'periodo', 'disciplina', 'matriz', 'carga_horaria', 'modalidade'], true)) {
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
            'CODIGO_DA_DISCIPLINA' => 'codigo',
            'CODIGO_DISCIPLINA' => 'codigo',
            'DISCIPLINA_CODIGO' => 'codigo',
            'CODIGO_DO_CURSO' => 'codigo_curso',
            'CODIGO_CURSO' => 'codigo_curso',
            'CURSO_CODIGO' => 'codigo_curso',
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
            'NOME_DA_MATRIZ' => 'matriz',
            'CARGA_HORARIA' => 'carga_horaria',
            'CH' => 'carga_horaria',
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
        ];

        return $variantMatches[$normalized] ?? Str::lower($normalized);
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

        $curso = trim((string) ($row['curso'] ?? $row['CURSO'] ?? ''));
        $codigoCurso = trim((string) ($row['codigo_curso'] ?? $row['CODIGO_CURSO'] ?? $row['CODIGO_DO_CURSO'] ?? $row['CURSO_CODIGO'] ?? ''));
        $disciplina = trim((string) ($row['disciplina'] ?? $row['DISCIPLINA'] ?? ''));
        $codigo = trim((string) ($row['codigo'] ?? $row['CODIGO'] ?? ''));
        $matriz = trim((string) ($row['matriz'] ?? $row['MATRIZ'] ?? ''));
        $turma = trim((string) ($row['turma'] ?? $row['TURMA'] ?? ''));
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

        $periodo = $this->toNumericValue($row['periodo'] ?? $row['PERIODO'] ?? '1');
        $cargaHoraria = $this->toNumericValue($row['carga_horaria'] ?? $row['CARGA_HORARIA'] ?? $row['CH'] ?? $row['HA_CLASSIS_PAGAMENTO'] ?? $row['H_A_CLASSIS_PAGAMENTO'] ?? '2');

        return [
            'curso' => $curso,
            'codigo_curso' => $codigoCurso,
            'matriz' => $matriz,
            'disciplina' => $disciplina ?: $codigo,
            'codigo' => $codigo,
            'periodo' => (int) $periodo,
            'turma' => $turma,
            'turno' => $turno,
            'modalidade' => $modalidade,
            'professor' => $professor,
            'grupo' => $grupo,
            'carga_horaria' => (float) $cargaHoraria,
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
        $code = $explicitCode !== '' ? $explicitCode : (strtoupper(Str::slug($name, '')) ?: 'SI');

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

    private function ensureMatrix(Course $course, string $matrixCode): ?CurriculumMatrix
    {
        if (trim($matrixCode) === '') {
            return null;
        }

        return CurriculumMatrix::firstOrCreate(
            ['course_id' => $course->id, 'code' => trim($matrixCode)],
            ['name' => trim($matrixCode), 'version' => 'importado', 'status' => 'Atual']
        );
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

    private function resolveClassCode(string $fallbackCode, AcademicTerm $term, Subject $subject, array $normalized, int $rowIndex): string
    {
        $candidate = trim($fallbackCode);

        if ($candidate === '') {
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

    private function resolveShift(string $value): string
    {
        $value = Str::upper(trim($value));
        $map = [
            'MANHA' => 'MANHÃ',
            'MANHÃ' => 'MANHÃ',
            'TARDE' => 'TARDE',
            'NOITE' => 'NOITE',
            'NOTURNO' => 'NOITE',
        ];

        return $map[$value] ?? 'MANHÃ';
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
