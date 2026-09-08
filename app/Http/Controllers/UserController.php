<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Subject;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function create()
    {
        return view('users.create');
    }

    public function profile()
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        return view('users.profile', [
            'user' => $user,
            'roles' => User::roleOptions(),
        ]);
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

        return view('users.profile-edit', [
            'user' => $user,
            'roles' => User::roleOptions(),
            'professor' => $professor,
            'term' => $term,
            'subjects' => $assignedSubjects,
            'availability' => $professor && $term
                ? ProfessorAvailability::where('professor_id', $professor->id)
                ->where('academic_term_id', $term->id)
                ->orderBy('weekday')
                ->orderBy('starts_at')
                ->get()
                : collect(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'registration_number' => ['nullable', 'string', 'max:20', 'unique:users,registration_number,' . $user->id],
            'role' => 'required|in:admin,coordinator,teacher,student,staff',
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
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
            'role' => $validated['role'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        if ($user->role === 'teacher') {
            $professor = Professor::firstOrCreate(
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

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => 'required|in:admin,coordinator,teacher,student,staff',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];

        if (Schema::hasColumn('users', 'registration_number')) {
            $rules['registration_number'] = 'nullable|string|max:20|unique:users';
        }

        $validated = $request->validate($rules);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => $validated['password'],
        ];

        if (Schema::hasColumn('users', 'is_active')) {
            $payload['is_active'] = $request->has('is_active');
        }

        if (Schema::hasColumn('users', 'registration_number') && ! empty($validated['registration_number'])) {
            $payload['registration_number'] = $validated['registration_number'];
        }

        User::create($payload);

        return redirect()->route('dashboard')->with('success', 'Novo usuário cadastrado com sucesso!');
    }
}
