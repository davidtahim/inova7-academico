<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Subject;
use App\Models\User;
use App\Services\AvailabilityGrid;
use App\Services\TeacherDashboard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function profile(TeacherDashboard $dashboard)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isTeacher()) {
            // O painel docente mora em /docente; /perfil continua valendo (links antigos).
            if (request()->routeIs('profile')) {
                $query = request()->getQueryString();

                return redirect(route('teacher.dashboard') . ($query ? '?' . $query : ''));
            }

            return $this->teacherDashboard($user, $dashboard);
        }

        return view('users.profile', [
            'user' => $user,
            'roles' => User::roleOptions(),
        ]);
    }

    private function teacherDashboard(User $user, TeacherDashboard $dashboard)
    {
        $term = $this->selectedTerm();
        $professor = $user->resolveProfessor();

        return view('users.teacher-dashboard', array_merge([
            'user' => $user,
            'professor' => $professor,
            'term' => $term,
            'qualificationOptions' => TeacherDashboard::QUALIFICATIONS,
            'statusLabels' => TeacherDashboard::ASSIGNMENT_STATUS,
            'weekdays' => TeacherDashboard::WEEKDAYS,
        ], $professor ? $dashboard->build($professor, $term) : [
            'assignments' => collect(),
            'qualifiedIds' => [],
            'stats' => ['classes' => 0, 'weekly_hours' => 0, 'students' => 0, 'confirmed' => 0],
            'missingFields' => [],
            'checklist' => [],
        ]));
    }

    /**
     * Dados funcionais informados pelo próprio professor (mesmos campos da lista de
     * professores da coordenação). O status (ativo/inativo) continua com a coordenação.
     */
    public function updateProfessionalData(Request $request)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isTeacher(), 403);

        $professor = $user->resolveProfessor();
        abort_unless($professor !== null, 404, 'Seu usuário ainda não está vinculado a um cadastro de professor. Procure a coordenação.');

        $validated = $request->validateWithBag('professional', [
            'name' => ['required', 'string', 'max:255'],
            'registration' => ['required', 'string', 'max:30', Rule::unique('professors', 'registration')->ignore($professor->id)],
            'chapa' => ['required', 'string', 'max:30'],
            'qualification' => ['required', Rule::in(TeacherDashboard::QUALIFICATIONS)],
            // Chega sem "http(s)://" (ver Professor::normalizeLattes); aceita endereço ou número.
            'lattes' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/)?[\w.-]+\.[a-z]{2,}(\/\S*)?$|^\d{10,20}$/i'],
        ], [
            'registration.unique' => 'Esta matrícula já pertence a outro professor.',
            'qualification.in' => 'Selecione uma formação da lista.',
        ], [
            'name' => 'nome',
            'registration' => 'matrícula',
            'chapa' => 'chapa',
            'qualification' => 'formação',
            'lattes' => 'currículo Lattes',
        ]);

        $professor->fill([
            'name' => trim($validated['name']),
            'registration' => trim($validated['registration']),
            'chapa' => trim($validated['chapa']),
            'qualification' => $validated['qualification'],
            'lattes' => $validated['lattes'] ?? null,
        ])->save();

        $user->name = $professor->name;
        $registrationTaken = User::where('registration_number', $professor->registration)->whereKeyNot($user->id)->exists();
        if (! $registrationTaken) {
            $user->registration_number = $professor->registration;
        }
        $user->save();

        return redirect()->route('teacher.dashboard')->with('success', 'Dados funcionais atualizados.');
    }

    private function selectedTerm(): ?AcademicTerm
    {
        return AcademicTerm::current();
    }

    public function editProfile(AvailabilityGrid $grid)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $term = AvailabilityGrid::selectedTerm();

        $professor = $user->resolveProfessor()
            ?? ($user->isTeacher() ? Professor::where('email', $user->email)->orWhere('name', $user->name)->first() : null);
        $assignedSubjects = $this->assignedSubjectsForProfessor($professor, $term);
        $selectedSubjectIds = $professor ? $professor->subjects()->pluck('subjects.id')->all() : [];

        $availabilityGrid = $professor ? $grid->build($professor, $term) : [];

        return view('users.profile-edit', array_merge($availabilityGrid, [
            'user' => $user,
            'roles' => User::roleOptions(),
            'professor' => $professor,
            'term' => $term,
            'subjects' => $assignedSubjects,
            'allSubjects' => $assignedSubjects,
            'selectedSubjectIds' => $selectedSubjectIds,
            'hasAvailabilityGrid' => $professor !== null,
            'isEditable' => $professor !== null && Gate::allows('updateAvailability', $professor) && ! $term?->isClosed(),
        ]));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $teacherMustSetPassword = $user->role === 'teacher' && ($user->must_change_password || $request->filled('password'));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'registration_number' => ['nullable', 'string', 'max:20', 'unique:users,registration_number,' . $user->id],
            'password' => [
                $teacherMustSetPassword || $request->filled('password') ? 'required' : 'nullable',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['integer', 'exists:subjects,id'],
            'availability' => ['nullable', 'array'],
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('profile_photos', 'public');
            $user->photo_path = $path;
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'registration_number' => $validated['registration_number'] ?? null,
            // O perfil (role) só é alterado pelo Administrador de TI na gestão de usuários.
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
            $user->must_change_password = false;
        }

        $user->save();

        if ($user->role === 'teacher') {
            $professor = $user->resolveProfessor() ?? Professor::firstOrCreate(
                ['email' => $user->email],
                [
                    'name' => $user->name,
                    'registration' => $user->registration_number,
                    'qualification' => 'Informado pelo perfil',
                    'active' => true,
                ]
            );

            $professor->name = $user->name;
            $professor->email = $user->email;
            $professor->registration = $user->registration_number ?? $professor->registration;
            $professor->save();

            if ((int) $user->professor_id !== (int) $professor->id) {
                $user->forceFill(['professor_id' => $professor->id])->save();
            }

            $selectedSubjectIds = collect($validated['subjects'] ?? [])
                ->filter(fn($subjectId) => is_numeric($subjectId))
                ->map(fn($subjectId) => (int) $subjectId)
                ->unique()
                ->values()
                ->all();

            $professor->subjects()->sync($selectedSubjectIds);

            // A disponibilidade do professor é ingerida pela importação da oferta Ubíqua.
            // O professor não deve sobrescrever esses dados pelo próprio perfil.
            // Quando a importação falhar, a correção deve ser feita pela equipe de TI.
        }

        return redirect()->route('profile')->with('success', 'Perfil atualizado com sucesso!');
    }

    public function exportTeacherAvailabilityDocument()
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->role !== 'teacher') {
            abort(403, 'Apenas professores podem exportar a disponibilidade.');
        }

        // Mesmo semestre selecionado e mesmo vínculo usados no painel e na grade.
        $term = $this->selectedTerm();
        $professor = $user->resolveProfessor();

        $html = view('users.ccg-for-02-document', $this->ccgFor02Data($professor, $term, $user->name))->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait');

        $filename = 'CCG-FOR-02 - DISPONIBILIDADE DE HORARIO - ' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::ascii($professor?->name ?? $user->name)) . ' - ' . ($term ? $term->code : 'semestre') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Dados do CCG-FOR-02 no modelo oficial (versão 06): grade com "x" nos horários disponíveis
     * e lista das disciplinas em que o professor está habilitado.
     */
    private function ccgFor02Data(?Professor $professor, ?AcademicTerm $term, string $fallbackName): array
    {
        $marks = $term && $professor
            ? ProfessorAvailability::where('professor_id', $professor->id)->where('academic_term_id', $term->id)
                ->where('preference', '!=', 'unavailable')->get()
                ->mapWithKeys(fn ($a) => [$a->weekday . '|' . substr((string) $a->starts_at, 0, 5) => $a->preference])->all()
            : [];

        // Cursos do professor; sem cursos marcados, os cursos das matrizes das disciplinas em que é habilitado.
        $subjectIds = $professor ? $professor->subjects()->pluck('subjects.id') : collect();
        $courses = $professor ? $professor->courses()->orderBy('name')->get() : collect();
        if ($courses->isEmpty() && $subjectIds->isNotEmpty()) {
            $courses = \App\Models\Course::whereIn('id', \Illuminate\Support\Facades\DB::table('curriculum_matrix_discipline')
                ->join('curriculum_matrices', 'curriculum_matrices.id', '=', 'curriculum_matrix_discipline.curriculum_matrix_id')
                ->whereIn('curriculum_matrix_discipline.subject_id', $subjectIds)->pluck('curriculum_matrices.course_id'))
                ->orderBy('name')->get();
        }
        $upper = fn (?string $v) => mb_strtoupper(trim((string) $v));
        $coordination = $courses->map(function ($course) use ($upper) {
            $coordinator = preg_replace('/^prof(a|\.ª|ª)?\.?\s+/iu', '', trim((string) $course->coordinatorDisplayName()));
            return $upper($course->name) . ($coordinator !== '' ? ' – PROF. ' . $upper($coordinator) : '');
        })->implode(' / ');

        $subjects = $professor ? $professor->subjects()->orderBy('name')->pluck('name')
            ->map(fn ($name) => self::titleCase($name))->values()->all() : [];
        // O formulário tem 10 linhas; com mais disciplinas, a lista cresce.
        $subjects = array_pad($subjects, max(10, count($subjects)), '');

        $label = fn ($s, $e, $night) => $night
            ? str_replace(':', 'h', $s) . ' às ' . str_replace(':', 'h', $e)
            : $s . ' às ' . $e;
        $rows = fn (array $slots, bool $night = false) => array_map(fn ($p) => [$label($p[0], $p[1], $night), $p[0]], $slots);

        $logoPath = resource_path('images/ccg-for-02-logo.png');

        return [
            'professorName' => $upper($professor?->name ?? $fallbackName),
            'termCode' => $term?->code ?? '',
            'coordination' => $coordination,
            'courseLabel' => $courses->pluck('name')->implode(', ') ?: 'Sistemas de Informação',
            'shifts' => [
                'MANHÃ' => $rows([['07:30', '08:20'], ['08:20', '09:10'], ['09:10', '10:00'], ['10:10', '11:00'], ['11:00', '11:50'], ['11:50', '12:40']]),
                'TARDE' => $rows([['13:10', '14:00'], ['14:00', '14:50'], ['14:50', '15:40'], ['15:50', '16:40'], ['16:40', '17:30']]),
                'NOITE' => $rows([['18:30', '19:20'], ['19:20', '20:10'], ['20:20', '21:10'], ['21:10', '22:00']], true),
            ],
            'marks' => $marks,
            'hasPreferred' => in_array('preferred', $marks, true),
            'subjects' => $subjects,
            'logo' => is_file($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null,
        ];
    }

    /** "SISTEMAS DE APOIO À DECISÃO" → "Sistemas de Apoio à Decisão" (siglas e algarismos romanos mantidos). */
    private static function titleCase(string $name): string
    {
        $small = ['de', 'da', 'do', 'das', 'dos', 'e', 'a', 'à', 'o', 'em', 'para', 'com', 'na', 'no', 'por'];
        $words = preg_split('/\s+/u', mb_strtolower(trim($name)));

        return implode(' ', array_map(function ($w, $i) use ($small) {
            if (preg_match('/^\(?(i{1,3}|iv|v|vi{0,3}|ix|x)\)?$/u', $w)) {
                return mb_strtoupper($w);
            }
            return $i > 0 && in_array($w, $small, true) ? $w : mb_convert_case($w, MB_CASE_TITLE);
        }, $words, array_keys($words)));
    }

    private function assignedSubjectsForProfessor(?Professor $professor, ?AcademicTerm $term = null)
    {
        if (! $professor) {
            return collect();
        }

        $subjectIds = \Illuminate\Support\Facades\DB::table('teaching_assignments')
            ->join('class_offerings', 'teaching_assignments.class_offering_id', '=', 'class_offerings.id')
            ->where('teaching_assignments.professor_id', $professor->id)
            ->when($term, fn($query) => $query->where('class_offerings.academic_term_id', $term->id))
            ->select('class_offerings.subject_id')
            ->distinct()
            ->pluck('class_offerings.subject_id');

        return Subject::whereIn('id', $subjectIds)->orderBy('name')->get();
    }
}
