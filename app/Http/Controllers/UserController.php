<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Subject;
use App\Models\User;
use App\Services\TeacherDashboard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'lattes' => ['nullable', 'url', 'max:255'],
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

        return redirect()->route('profile')->with('success', 'Dados funcionais atualizados.');
    }

    private function selectedTerm(): ?AcademicTerm
    {
        $selectedTermId = session('selected_academic_term_id');
        $term = $selectedTermId ? AcademicTerm::find($selectedTermId) : null;

        return $term ?? AcademicTerm::latest('id')->first();
    }

    public function editProfile()
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $selectedTermId = session('selected_academic_term_id');
        $term = $selectedTermId ? AcademicTerm::find($selectedTermId) : null;
        $term ??= AcademicTerm::latest('id')->first();

        $professor = Professor::where('email', $user->email)->orWhere('name', $user->name)->first();
        $assignedSubjects = $this->assignedSubjectsForProfessor($professor, $term);
        $allSubjects = $assignedSubjects;
        $selectedSubjectIds = $professor ? $professor->subjects()->pluck('subjects.id')->all() : [];

        $availability = $professor && $term
            ? ProfessorAvailability::where('professor_id', $professor->id)
            ->where('academic_term_id', $term->id)
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get()
            : collect();

        if ($availability->isEmpty()) {
            $availability = collect([
                (object) ['weekday' => 1, 'starts_at' => '07:30', 'ends_at' => '08:20', 'preference' => 'preferred'],
                (object) ['weekday' => 1, 'starts_at' => '10:50', 'ends_at' => '11:40', 'preference' => 'preferred'],
                (object) ['weekday' => 2, 'starts_at' => '18:30', 'ends_at' => '19:20', 'preference' => 'preferred'],
                (object) ['weekday' => 2, 'starts_at' => '21:00', 'ends_at' => '21:50', 'preference' => 'preferred'],
            ]);
        }

        return view('users.profile-edit', [
            'user' => $user,
            'roles' => User::roleOptions(),
            'professor' => $professor,
            'term' => $term,
            'subjects' => $assignedSubjects,
            'allSubjects' => $allSubjects,
            'selectedSubjectIds' => $selectedSubjectIds,
            'availability' => $availability,
        ]);
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

        $term = AcademicTerm::latest('id')->first();
        $professor = Professor::where('email', $user->email)
            ->orWhere('name', $user->name)
            ->first();

        $slots = $term && $professor
            ? ProfessorAvailability::where('professor_id', $professor->id)
            ->where('academic_term_id', $term->id)
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get()
            : collect();

        $weekdays = [
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
        ];

        $grouped = collect(range(1, 6))->mapWithKeys(function ($day) use ($weekdays, $slots) {
            return [$day => $slots->filter(fn($slot) => (int) $slot->weekday === $day)->values()];
        })->filter(fn($items) => $items->isNotEmpty());

        $html = view('users.ccg-for-02-document', [
            'user' => $user,
            'professor' => $professor,
            'term' => $term,
            'slots' => $slots,
            'weekdays' => $weekdays,
            'grouped' => $grouped,
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait');

        $filename = 'CCG-FOR-02-Disponibilidade-' . ($term ? $term->code : 'semestre') . '.pdf';

        return $pdf->download($filename);
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
