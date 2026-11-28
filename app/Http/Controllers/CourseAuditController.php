<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseAudit;
use App\Models\CourseAuditDocument;
use App\Models\CourseAuditEntry;
use App\Models\User;
use App\Services\CourseAuditCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Auditoria de curso (QUA-INT-08): por curso e semestre, a nota prevista de cada indicador e a
 * situação de cada documento exigido, com o resultado previsto (Delegação, Gestão, Supervisão).
 */
class CourseAuditController extends Controller
{
    public function __construct(private CourseAuditCatalog $catalog) {}

    public function index()
    {
        $term = AcademicTerm::current();
        $courses = \App\Support\CourseScope::filter(Course::with(['coordinator:id,name', 'assistants:id,name'])->where('active', true), 'id')->orderBy('name')->get();
        $audits = $term ? CourseAudit::with(['entries', 'documents'])->where('academic_term_id', $term->id)->get()->keyBy('course_id') : collect();

        return view('audit.courses.index', [
            'term' => $term,
            'rows' => $courses->map(fn ($course) => [
                'course' => $course,
                'result' => ($audit = $audits->get($course->id)) ? $this->catalog->evaluate($audit) : null,
            ]),
            'catalog' => $this->catalog,
        ]);
    }

    public function show(Course $course)
    {
        $term = AcademicTerm::current();
        abort_unless($term, 404, 'Nenhum semestre cadastrado.');
        $audit = $this->audit($course, $term);
        $audit->load(['entries.editor:id,name', 'documents.responsible:id,name']);

        return view('audit.courses.show', [
            'course' => $course->load(['coordinator:id,name', 'assistants:id,name']),
            'term' => $term,
            'audit' => $audit,
            'catalog' => $this->catalog,
            'result' => $this->catalog->evaluate($audit),
            'entries' => $audit->entries->keyBy('indicator'),
            'documents' => $audit->documents->keyBy('doc_key'),
            'canEdit' => Gate::allows('edit-course-audit', $course) && ! $term->isClosed(),
            'people' => User::whereIn('role', ['admin', 'coordinator', 'staff'])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateHeader(Request $request, Course $course)
    {
        $audit = $this->editable($course);
        $audit->update($request->validate([
            'auditor' => ['nullable', 'string', 'max:255'],
            'audit_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]));

        return redirect()->route('audit.courses.show', $course)->with('success', 'Dados da auditoria salvos.');
    }

    /** Salva um indicador: nota prevista, "não se aplica", observação e os documentos dele. */
    public function updateIndicator(Request $request, Course $course, string $code)
    {
        $audit = $this->editable($course);
        $indicator = $this->catalog->indicator($code);
        abort_unless($indicator, 404);

        $data = $request->validate([
            'score' => ['nullable', 'integer', Rule::in($indicator['kind'] === 'simnao' ? [1, 5] : [1, 2, 3, 4, 5])],
            'not_applicable' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'docs' => ['nullable', 'array'],
            'docs.*.status' => ['nullable', Rule::in(array_keys(CourseAuditDocument::STATUSES))],
            'docs.*.link' => ['nullable', 'string', 'max:500'],
            'docs.*.responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'docs.*.due_date' => ['nullable', 'date'],
            'docs.*.remove_file' => ['nullable', 'boolean'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,jpg,jpeg,png,zip'],
        ], [
            'files.*.max' => 'Cada arquivo pode ter até 20 MB.',
            'files.*.mimes' => 'Envie PDF, Word, Excel, PowerPoint, imagem ou ZIP.',
        ]);

        CourseAuditEntry::updateOrCreate(
            ['course_audit_id' => $audit->id, 'indicator' => $code],
            [
                'score' => $request->boolean('not_applicable') ? null : ($data['score'] ?? null),
                'not_applicable' => $request->boolean('not_applicable'),
                'notes' => $data['notes'] ?? null,
                'updated_by' => $request->user()->id,
            ]
        );

        foreach ($indicator['documents'] as $name) {
            $key = CourseAuditDocument::keyFor($code, $name);
            $input = $data['docs'][$key] ?? [];
            $document = CourseAuditDocument::firstOrNew(['course_audit_id' => $audit->id, 'doc_key' => $key], ['indicator' => $code, 'name' => $name]);

            if (! empty($input['remove_file']) && $document->file_path) {
                Storage::disk('local')->delete($document->file_path);
                $document->file_path = $document->file_name = null;
            }
            if ($file = $request->file("files.{$key}")) {
                if ($document->file_path) {
                    Storage::disk('local')->delete($document->file_path);
                }
                $document->file_path = $file->store("auditoria/{$audit->id}", 'local');
                $document->file_name = $file->getClientOriginalName();
            }

            // Link: o formulário envia sem "https://" (Mod_Security da hospedagem); o protocolo volta aqui.
            $link = trim((string) ($input['link'] ?? ''));
            $document->link = $link === '' ? null : (preg_match('#^https?://#i', $link) ? $link : 'https://' . $link);

            $status = $input['status'] ?? $document->status ?? 'pending';
            // Arquivo ou link anexado conta como entregue.
            if ($status === 'pending' && ($document->file_path || $document->link)) {
                $status = 'delivered';
            }
            $document->delivered_at = $status === 'delivered' ? ($document->status === 'delivered' && $document->delivered_at ? $document->delivered_at : now()) : null;
            $document->status = $status;
            $document->responsible_id = $input['responsible_id'] ?? null;
            $document->due_date = $input['due_date'] ?? null;
            $document->name = $name;
            $document->indicator = $code;
            $document->updated_by = $request->user()->id;
            $document->save();
        }

        return redirect()->to(route('audit.courses.show', $course) . '#ind-' . str_replace('.', '-', $code))
            ->with('success', "Indicador {$code} salvo.");
    }

    public function download(Course $course, CourseAuditDocument $document)
    {
        $audit = CourseAudit::findOrFail($document->course_audit_id);
        abort_unless($audit->course_id === $course->id && $document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name ?: basename($document->file_path));
    }

    private function audit(Course $course, AcademicTerm $term): CourseAudit
    {
        return CourseAudit::firstOrCreate(['course_id' => $course->id, 'academic_term_id' => $term->id]);
    }

    private function editable(Course $course): CourseAudit
    {
        Gate::authorize('edit-course-audit', $course);
        $term = AcademicTerm::current();
        abort_unless($term, 404);
        abort_if($term->isClosed(), 403, 'Semestre concluído: a auditoria fica só para consulta.');

        return $this->audit($course, $term);
    }
}
