<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\Professor;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AdminCatalogController extends Controller
{
    /** Cadastros do catálogo: Administrador de TI e Coordenador. */
    private function ensureAdmin(): void
    {
        abort_unless(Gate::allows('manage-catalog'), 403, 'Apenas administradores e coordenadores podem gerenciar o catálogo.');
    }

    /** Consulta do catálogo: Administrador de TI, Coordenador e Funcionário. */
    private function ensureCatalogAccess(): void
    {
        abort_unless(Gate::allows('access-catalog'), 403, 'Acesso negado. Você não tem permissão para acessar o catálogo.');
    }

    /** Gestão de usuários: somente Administrador de TI. */
    /**
     * Limites operacionais do professor (máx. aulas/dia e dias/semana): só a coordenação
     * (e o admin) definem; para os demais perfis os campos são ignorados.
     */
    private function professorLimits(Request $request): array
    {
        if (! Gate::allows('manage-planning')) {
            return [];
        }

        return $request->validate([
            'max_classes_per_day' => ['nullable', 'integer', 'between:1,10'],
            'max_days_per_week' => ['nullable', 'integer', 'between:1,6'],
        ]);
    }

    private function ensureUserManager(): void
    {
        abort_unless(Gate::allows('manage-users'), 403, 'Apenas administradores de TI podem gerenciar usuários.');
    }

    private function resolveProfessorEmail(string $name, ?string $chapa, ?string $email): ?string
    {
        $normalizedChapa = trim((string) $chapa);

        if ($normalizedChapa !== '') {
            return strtolower($normalizedChapa) . '@prof.uni7.edu.br';
        }

        if (! empty($email)) {
            return trim((string) $email);
        }

        $baseName = preg_replace('/[^a-zA-Z0-9]+/', '', strtolower(trim((string) $name))) ?? '';

        return $baseName !== '' ? $baseName . '@prof.uni7.edu.br' : null;
    }

    private function syncProfessorUser(Professor $professor): void
    {
        $email = $this->resolveProfessorEmail($professor->name, $professor->chapa, $professor->email);

        if (empty($email)) {
            return;
        }

        $payload = [
            'name' => $professor->name,
            'email' => $email,
            'role' => 'teacher',
            'password' => 'senha1234',
            'must_change_password' => true,
        ];

        if (method_exists(User::class, 'query')) {
            $user = User::query()->where('email', $email)->first();
        } else {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            $user->fill([
                'name' => $professor->name,
                'role' => 'teacher',
                'password' => 'senha1234',
                'must_change_password' => true,
            ]);

            if (Schema::hasColumn('users', 'registration_number') && ! empty($professor->registration)) {
                $user->registration_number = $professor->registration;
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $user->is_active = (bool) $professor->active;
            }

            $user->save();

            return;
        }

        $userData = $payload;

        if (Schema::hasColumn('users', 'registration_number') && ! empty($professor->registration)) {
            $userData['registration_number'] = $professor->registration;
            
            // Verificar se já existe usuário com este registration_number
            $existingUser = User::where('registration_number', $professor->registration)->first();
            if ($existingUser) {
                // Atualizar o usuário existente ao invés de criar novo
                $existingUser->fill([
                    'name' => $professor->name,
                    'email' => $email,
                    'role' => 'teacher',
                    'password' => 'senha1234',
                    'must_change_password' => true,
                ]);

                if (Schema::hasColumn('users', 'is_active')) {
                    $existingUser->is_active = (bool) $professor->active;
                }

                $existingUser->save();
                return;
            }
        }

        if (Schema::hasColumn('users', 'is_active')) {
            $userData['is_active'] = (bool) $professor->active;
        }

        User::create($userData);
    }

    public function planejamentoIndex()
    {
        $this->ensureAdmin();

        return view('admin.planejamento.index', [
            'usersCount' => User::count(),
            'coursesCount' => Course::count(),
            'subjectsCount' => Subject::count(),
            'professorsCount' => Professor::count(),
            'termsCount' => AcademicTerm::count(),
        ]);
    }

    public function coursesIndex(Request $request)
    {
        $this->ensureAdmin();

        $query = trim((string) $request->query('q', ''));

        $courses = Course::query()
            ->when($query !== '', function ($queryBuilder) use ($query) {
                $queryBuilder->where(function ($innerQuery) use ($query) {
                    $innerQuery->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%")
                        ->orWhere('degree', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->get();

        return view('admin.courses.index', [
            'courses' => $courses,
            'query' => $query,
        ]);
    }

    public function courseCreate()
    {
        $this->ensureAdmin();

        return view('admin.courses.form', [
            'course' => new Course(),
        ]);
    }

    public function courseStore(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:courses,code'],
            'name' => ['required', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        Course::create($validated);

        return redirect()->route('admin.courses.index')->with('success', 'Curso cadastrado com sucesso!');
    }

    public function courseEdit(Course $course)
    {
        $this->ensureAdmin();

        return view('admin.courses.form', [
            'course' => $course,
        ]);
    }

    public function courseUpdate(Request $request, Course $course)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:courses,code,' . $course->id],
            'name' => ['required', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        $course->update($validated);

        return redirect()->route('admin.courses.index')->with('success', 'Curso atualizado com sucesso!');
    }

    public function courseDestroy(Course $course)
    {
        $this->ensureAdmin();

        $course->delete();

        return redirect()->route('admin.courses.index')->with('success', 'Curso removido com sucesso!');
    }

    public function matricesIndex(Request $request)
    {
        $this->ensureAdmin();

        $query = trim((string) $request->query('q', ''));

        $matrices = CurriculumMatrix::with('course')->when($query !== '', function ($queryBuilder) use ($query) {
            $queryBuilder->where(function ($innerQuery) use ($query) {
                $innerQuery->where('name', 'like', "%{$query}%")
                    ->orWhere('code', 'like', "%{$query}%")
                    ->orWhere('version', 'like', "%{$query}%")
                    ->orWhereHas('course', function ($courseQuery) use ($query) {
                        $courseQuery->where('name', 'like', "%{$query}%")
                            ->orWhere('code', 'like', "%{$query}%");
                    });
            });
        })->orderBy('name')->get();

        return view('admin.matrices.index', [
            'matrices' => $matrices,
            'query' => $query,
        ]);
    }

    public function importScopesIndex(Request $request)
    {
        $this->ensureAdmin();

        $query = trim((string) $request->query('q', ''));

        $courses = Course::with('matrices')
            ->when($query !== '', function ($queryBuilder) use ($query) {
                $queryBuilder->where(function ($innerQuery) use ($query) {
                    $innerQuery->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%")
                        ->orWhereHas('matrices', function ($matrixQuery) use ($query) {
                            $matrixQuery->where('code', 'like', "%{$query}%")
                                ->orWhere('name', 'like', "%{$query}%");
                        });
                });
            })
            ->orderBy('name')
            ->get();

        return view('admin.import-scope.index', [
            'courses' => $courses,
            'query' => $query,
        ]);
    }

    public function importScopeEdit(Course $course)
    {
        $this->ensureAdmin();

        $course->load('matrices');

        return view('admin.import-scope.form', [
            'course' => $course,
            'matrices' => $course->matrices()->orderBy('code')->get(),
        ]);
    }

    public function importScopeUpdate(Request $request, Course $course)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'matrix_ids' => ['nullable', 'array'],
            'matrix_ids.*' => ['integer', 'exists:curriculum_matrices,id'],
        ]);

        $selectedIds = collect($validated['matrix_ids'] ?? [])->map(fn($id) => (int) $id)->all();

        $course->matrices()->update(['allowed_for_import' => false]);

        if (! empty($selectedIds)) {
            $course->matrices()->whereIn('id', $selectedIds)->update(['allowed_for_import' => true]);
        }

        return redirect()->route('admin.import-scopes.index')->with('success', 'Matrizes permitidas atualizadas com sucesso!');
    }

    public function matrixCreate()
    {
        $this->ensureAdmin();

        return view('admin.matrices.form', [
            'matrix' => new CurriculumMatrix(),
            'courses' => Course::orderBy('name')->get(),
        ]);
    }

    public function matrixStore(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:Atual,Ativa,Inativa'],
            'effective_from' => ['nullable', 'date'],
            'allowed_for_import' => ['nullable', 'boolean'],
        ]);

        $validated['allowed_for_import'] = $validated['allowed_for_import'] ?? true;

        CurriculumMatrix::create($validated);

        return redirect()->route('admin.matrices.index')->with('success', 'Matriz cadastrada com sucesso!');
    }

    public function matrixEdit(CurriculumMatrix $matrix)
    {
        $this->ensureAdmin();

        return view('admin.matrices.form', [
            'matrix' => $matrix,
            'courses' => Course::orderBy('name')->get(),
        ]);
    }

    public function matrixUpdate(Request $request, CurriculumMatrix $matrix)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:Atual,Ativa,Inativa'],
            'effective_from' => ['nullable', 'date'],
            'allowed_for_import' => ['nullable', 'boolean'],
        ]);

        $validated['allowed_for_import'] = $validated['allowed_for_import'] ?? $matrix->allowed_for_import ?? true;

        $matrix->update($validated);

        return redirect()->route('admin.matrices.index')->with('success', 'Matriz atualizada com sucesso!');
    }

    public function matrixDestroy(CurriculumMatrix $matrix)
    {
        $this->ensureAdmin();

        $matrix->delete();

        return redirect()->route('admin.matrices.index')->with('success', 'Matriz removida com sucesso!');
    }

    public function termsIndex(Request $request)
    {
        $this->ensureAdmin();

        $query = trim((string) $request->query('q', ''));

        $terms = AcademicTerm::query()
            ->when($query !== '', function ($queryBuilder) use ($query) {
                $queryBuilder->where(function ($innerQuery) use ($query) {
                    $innerQuery->where('code', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%");
                });
            })
            ->orderBy('code', 'desc')
            ->get();

        return view('admin.semesters.index', [
            'terms' => $terms,
            'query' => $query,
        ]);
    }

    public function termCreate()
    {
        $this->ensureAdmin();

        return view('admin.semesters.form', [
            'term' => new AcademicTerm(),
        ]);
    }

    public function termStore(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:academic_terms,code'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:planning,active,closed'],
        ]);

        AcademicTerm::create($validated);

        return redirect()->route('admin.semestres.index')->with('success', 'Semestre cadastrado com sucesso!');
    }

    public function termEdit(AcademicTerm $term)
    {
        $this->ensureAdmin();

        return view('admin.semesters.form', [
            'term' => $term,
        ]);
    }

    public function termUpdate(Request $request, AcademicTerm $term)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:academic_terms,code,' . $term->id],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:planning,active,closed'],
        ]);

        $term->update($validated);

        return redirect()->route('admin.semestres.index')->with('success', 'Semestre atualizado com sucesso!');
    }

    public function termDestroy(AcademicTerm $term)
    {
        $this->ensureAdmin();

        $term->delete();

        return redirect()->route('admin.semestres.index')->with('success', 'Semestre removido com sucesso!');
    }

    public function subjectsIndex(Request $request)
    {
        $this->ensureAdmin();

        $query = trim((string) $request->query('q', ''));

        $subjects = Subject::query()
            ->when($query !== '', function ($queryBuilder) use ($query) {
                $queryBuilder->where(function ($innerQuery) use ($query) {
                    $innerQuery->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->get();

        return view('admin.subjects.index', [
            'subjects' => $subjects,
            'query' => $query,
        ]);
    }

    public function subjectCreate()
    {
        $this->ensureAdmin();

        return view('admin.subjects.form', [
            'subject' => new Subject(),
        ]);
    }

    public function subjectStore(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['nullable', 'string', 'max:12'],
            'syllabus' => ['nullable', 'string'],
            'total_hours' => ['nullable', 'integer', 'min:0'],
            'presential_hours' => ['nullable', 'integer', 'min:0'],
            'online_hours' => ['nullable', 'integer', 'min:0'],
            'practice_hours' => ['nullable', 'integer', 'min:0'],
            'extension_hours' => ['nullable', 'integer', 'min:0'],
        ]);

        Subject::create($validated);

        return redirect()->route('admin.subjects.index')->with('success', 'Disciplina cadastrada com sucesso!');
    }

    public function subjectEdit(Subject $subject)
    {
        $this->ensureAdmin();

        return view('admin.subjects.form', [
            'subject' => $subject,
        ]);
    }

    public function subjectUpdate(Request $request, Subject $subject)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['nullable', 'string', 'max:12'],
            'syllabus' => ['nullable', 'string'],
            'total_hours' => ['nullable', 'integer', 'min:0'],
            'presential_hours' => ['nullable', 'integer', 'min:0'],
            'online_hours' => ['nullable', 'integer', 'min:0'],
            'practice_hours' => ['nullable', 'integer', 'min:0'],
            'extension_hours' => ['nullable', 'integer', 'min:0'],
        ]);

        $subject->update($validated);

        return redirect()->route('admin.subjects.index')->with('success', 'Disciplina atualizada com sucesso!');
    }

    public function subjectDestroy(Subject $subject)
    {
        $this->ensureAdmin();

        $subject->delete();

        return redirect()->route('admin.subjects.index')->with('success', 'Disciplina removida com sucesso!');
    }

    public function professorsIndex(Request $request)
    {
        $this->ensureCatalogAccess();

        $query = trim((string) $request->query('q', ''));

        $professors = Professor::query()
            ->when($query !== '', function ($queryBuilder) use ($query) {
                $queryBuilder->where(function ($innerQuery) use ($query) {
                    $innerQuery->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('qualification', 'like', "%{$query}%")
                        ->orWhere('registration', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->get();

        return view('admin.professors.index', [
            'professors' => $professors,
            'query' => $query,
        ]);
    }

    public function professorCreate()
    {
        $this->ensureAdmin();

        return view('admin.professors.form', [
            'professor' => new Professor(),
        ]);
    }

    public function professorStore(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'registration' => ['nullable', 'string', 'max:50', 'unique:professors,registration'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:professors,email'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'chapa' => ['nullable', 'string', 'max:100'],
            'lattes' => ['nullable', 'string', 'max:255'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['nullable', 'integer', 'exists:courses,id'],
            'active' => ['nullable', 'boolean'],
        ]);

        $courseIds = $validated['course_ids'] ?? [];
        unset($validated['course_ids']);

        $chapa = trim((string) ($validated['chapa'] ?? ''));
        $generatedEmail = $this->resolveProfessorEmail($validated['name'] ?? '', $chapa, $validated['email'] ?? null);

        if (! empty($generatedEmail)) {
            $validated['email'] = $generatedEmail;
        }

        $validated += $this->professorLimits($request);
        $professor = Professor::create($validated);
        $this->syncProfessorUser($professor);
        $professor->courses()->sync($courseIds);

        return redirect()->route('admin.professors.index')->with('success', 'Professor cadastrado com sucesso!');
    }

    public function professorEdit(Professor $professor)
    {
        $this->ensureCatalogAccess();

        return view('admin.professors.form', [
            'professor' => $professor,
        ]);
    }

    public function professorUpdate(Request $request, Professor $professor)
    {
        $this->ensureCatalogAccess();

        $validated = $request->validate([
            'registration' => ['nullable', 'string', 'max:50', 'unique:professors,registration,' . $professor->id],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:professors,email,' . $professor->id],
            'qualification' => ['nullable', 'string', 'max:255'],
            'chapa' => ['nullable', 'string', 'max:100'],
            'lattes' => ['nullable', 'string', 'max:255'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['nullable', 'integer', 'exists:courses,id'],
            'active' => ['nullable', 'boolean'],
        ]);

        $courseIds = $validated['course_ids'] ?? [];
        unset($validated['course_ids']);

        $chapa = trim((string) ($validated['chapa'] ?? $professor->chapa ?? ''));
        $generatedEmail = $this->resolveProfessorEmail($validated['name'] ?? $professor->name, $chapa, $validated['email'] ?? $professor->email);

        if (! empty($generatedEmail)) {
            $validated['email'] = $generatedEmail;
        }

        $validated += $this->professorLimits($request);
        $professor->update($validated);
        $this->syncProfessorUser($professor);
        $professor->courses()->sync($courseIds);

        return redirect()->route('admin.professors.index')->with('success', 'Professor atualizado com sucesso!');
    }

    public function professorDestroy(Professor $professor)
    {
        $this->ensureAdmin();

        $professor->delete();

        return redirect()->route('admin.professors.index')->with('success', 'Professor removido com sucesso!');
    }

    public function usersIndex(Request $request)
    {
        $this->ensureUserManager();

        $query = trim((string) $request->query('q', ''));
        $roleFilter = trim((string) $request->query('role', ''));

        $users = User::query()
            ->when($query !== '', function ($queryBuilder) use ($query) {
                $queryBuilder->where(function ($innerQuery) use ($query) {
                    $innerQuery->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('registration_number', 'like', "%{$query}%");
                });
            })
            ->when($roleFilter !== '', function ($queryBuilder) use ($roleFilter) {
                $queryBuilder->where('role', $roleFilter);
            })
            ->orderBy('name')
            ->get();

        return view('admin.users-index', [
            'users' => $users,
            'query' => $query,
            'roleFilter' => $roleFilter,
            'roleOptions' => User::roleOptions(),
        ]);
    }

    public function userEdit(User $user)
    {
        $this->ensureUserManager();

        return view('admin.users-form', [
            'user' => $user,
            'roleOptions' => User::roleOptions(),
        ]);
    }

    public function userUpdate(Request $request, User $user)
    {
        $this->ensureUserManager();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['required', 'in:admin,coordinator,teacher,student,staff'],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ];

        if (Schema::hasColumn('users', 'registration_number')) {
            $rules['registration_number'] = ['nullable', 'string', 'max:20', 'unique:users,registration_number,' . $user->id];
        }

        if (Schema::hasColumn('users', 'is_active')) {
            $rules['is_active'] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ];

        if (Schema::hasColumn('users', 'registration_number')) {
            $userData['registration_number'] = $validated['registration_number'] ?? null;
        }

        if (Schema::hasColumn('users', 'is_active')) {
            $userData['is_active'] = $validated['is_active'] ?? false;
        }

        if (! empty($validated['password'])) {
            $userData['password'] = $validated['password'];
            $userData['must_change_password'] = true;
        }

        $user->update($userData);

        if ($user->role === 'teacher') {
            $professor = Professor::where('email', $user->email)->first();
            if (! $professor) {
                $professor = Professor::create([
                    'name' => $user->name,
                    'email' => $user->email,
                    'registration' => $user->registration_number,
                    'active' => true,
                ]);
            } else {
                $professor->update([
                    'name' => $user->name,
                    'email' => $user->email,
                    'registration' => $user->registration_number,
                ]);
            }
        }

        return redirect()->route('admin.users.index')->with('success', 'Usuário atualizado com sucesso!');
    }

    public function userDestroy(User $user)
    {
        $this->ensureUserManager();

        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users.index')->with('error', 'Você não pode deletar sua própria conta!');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Usuário removido com sucesso!');
    }
}
