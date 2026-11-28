<?php

namespace App\Http\Controllers;

use App\Models\Professor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    /**
     * Verificar se o usuário é Administrador de TI
     */
    private function ensureAdminTi(): void
    {
        if (!Auth::check() || !Auth::user()->isAdminTi()) {
            abort(403, 'Apenas administradores de TI podem acessar esta seção.');
        }
    }

    /**
     * Exibir listagem de usuários com filtros
     */
    public function index(Request $request)
    {
        $this->ensureAdminTi();

        $query = User::query();

        // Filtro por perfil (role)
        if ($request->filled('role') && $request->input('role') !== 'all') {
            $query->where('role', $request->input('role'));
        }

        // Filtro por status
        if ($request->filled('status')) {
            if ($request->input('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->input('status') === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Busca por nome, email ou matrícula
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
                
                if (Schema::hasColumn('users', 'registration_number')) {
                    $q->orWhere('registration_number', 'like', $search);
                }
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);
        $roles = User::roleOptions();

        return view('admin.users-index', [
            'users' => $users,
            'roles' => $roles,
            'selectedRole' => $request->input('role'),
            'selectedStatus' => $request->input('status'),
            'searchTerm' => $request->input('search'),
        ]);
    }

    /**
     * Exibir formulário de criação de usuário
     */
    public function create()
    {
        $this->ensureAdminTi();

        return view('admin.users-create', [
            'roles' => User::roleOptions(),
            'user' => null,
        ]);
    }

    /**
     * Armazenar novo usuário
     */
    public function store(Request $request)
    {
        $this->ensureAdminTi();

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'role' => 'required|in:admin,coordinator,teacher,student,staff,lab_manager',
            // Gestor de laboratórios: a área define os laboratórios que ele administra.
            'lab_area' => ['nullable', 'required_if:role,lab_manager', \Illuminate\Validation\Rule::in(array_keys(\App\Support\LabAreas::AREAS))],
        ];

        if (Schema::hasColumn('users', 'registration_number')) {
            $rules['registration_number'] = 'nullable|string|max:20|unique:users,registration_number';
        }

        $rules['password'] = ['required', 'confirmed', Password::min(8)->letters()->numbers()];
        $rules['is_active'] = 'nullable|boolean';

        $validated = $request->validate($rules);

        try {
            Log::info('UserManagementController::store - Iniciando criação de usuário', [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
            ]);

            DB::beginTransaction();

            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'lab_area' => $validated['role'] === 'lab_manager' ? $validated['lab_area'] : null,
                'must_change_password' => true,
            ];

            if (Schema::hasColumn('users', 'registration_number') && ! empty($validated['registration_number'])) {
                $userData['registration_number'] = $validated['registration_number'];
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $userData['is_active'] = $validated['is_active'] ?? true;
            }

            Log::info('UserManagementController::store - Dados do usuário preparados', \Illuminate\Support\Arr::except($userData, ['password']));

            $user = User::create($userData);

            Log::info('UserManagementController::store - Usuário criado', ['user_id' => $user->id]);

            // Se for professor, criar registro em professores
            if ($validated['role'] === 'teacher') {
                Log::info('UserManagementController::store - Criando registro de professor');
                $this->createOrUpdateProfessor($user);
            }

            DB::commit();

            Log::info('UserManagementController::store - Usuário salvo com sucesso', ['user_id' => $user->id]);

            return redirect()->route('admin.users.show', $user)
                ->with('success', 'Usuário criado com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UserManagementController::store - ERRO ao criar usuário', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withInput()
                ->with('error', 'Erro ao criar usuário: ' . $e->getMessage());
        }
    }

    /**
     * Exibir detalhes do usuário
     */
    public function show(User $user)
    {
        $this->ensureAdminTi();

        $user->load('professor');

        return view('admin.users-show', [
            'user' => $user,
            'roles' => User::roleOptions(),
        ]);
    }

    /**
     * Exibir formulário de edição
     */
    public function edit(User $user)
    {
        $this->ensureAdminTi();

        $user->load('professor.courses');

        return view('admin.users-edit', [
            'user' => $user,
            'roles' => User::roleOptions(),
            // Cursos do professor: limitam as matrizes que ele vê em "Minhas disciplinas".
            'courses' => \App\Models\Course::with('coordinator')->where('active', true)->orderBy('name')->get(),
            'professorCourseIds' => $user->professor?->courses->pluck('id')->all() ?? [],
            // Coordenação ou Administrador de TI que também coordena: cursos sob a coordenação dele.
            'coordinatedCourseIds' => $user->coordinatedCourses()->pluck('id')->all(),
            'assistedCourseIds' => $user->assistedCourses()->pluck('courses.id')->all(),
        ]);
    }

    /**
     * Atualizar usuário
     */
    public function update(Request $request, User $user)
    {
        $this->ensureAdminTi();

        // Impedir que o último admin de TI remova seus próprios privilégios
        if ($user->isAdminTi() && Auth::id() === $user->id && $request->input('role') !== 'admin') {
            return back()->with('error', 'Você não pode remover seus próprios direitos de administrador de TI.');
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => 'required|in:admin,coordinator,teacher,student,staff,lab_manager',
            // Gestor de laboratórios: a área define os laboratórios que ele administra.
            'lab_area' => ['nullable', 'required_if:role,lab_manager', \Illuminate\Validation\Rule::in(array_keys(\App\Support\LabAreas::AREAS))],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'coordinated_course_ids' => ['nullable', 'array'],
            'coordinated_course_ids.*' => ['integer', 'exists:courses,id'],
            'assisted_course_ids' => ['nullable', 'array'],
            'assisted_course_ids.*' => ['integer', 'exists:courses,id'],
        ];

        if (Schema::hasColumn('users', 'registration_number')) {
            $rules['registration_number'] = ['nullable', 'string', 'max:20', 'unique:users,registration_number,' . $user->id];
        }

        $rules['is_active'] = 'nullable|boolean';
        $rules['password'] = ['nullable', 'confirmed', Password::min(8)->letters()->numbers()];

        $validated = $request->validate($rules);

        try {
            DB::beginTransaction();

            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->role = $validated['role'];
            $user->lab_area = $validated['role'] === 'lab_manager' ? ($validated['lab_area'] ?? null) : null;

            if (Schema::hasColumn('users', 'registration_number') && ! empty($validated['registration_number'])) {
                $user->registration_number = $validated['registration_number'];
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $user->is_active = $validated['is_active'] ?? true;
            }

            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
                $user->must_change_password = false;
            }

            $user->save();

            // Sincronizar professor se mudou para teacher
            if ($validated['role'] === 'teacher') {
                $this->createOrUpdateProfessor($user);
                // Cursos em que o professor atua (vazio = todos).
                if ($request->has('courses_submitted') && ($professor = $user->fresh()->resolveProfessor())) {
                    $professor->courses()->sync($validated['course_ids'] ?? []);
                }
            } elseif ($validated['role'] !== 'teacher' && $user->professor_id) {
                // Remover vínculo se não é mais professor
                $user->professor_id = null;
                $user->save();
            }

            // Cursos que coordena (coordenação e Administrador de TI). Outro perfil deixa de coordenar.
            if ($request->has('coordinated_submitted') || ! $user->canCoordinate()) {
                $ids = $user->canCoordinate() ? array_map('intval', $validated['coordinated_course_ids'] ?? []) : [];
                \App\Models\Course::where('coordinator_user_id', $user->id)->when($user->canCoordinate(), fn ($q) => $q->where('active', true))->whereNotIn('id', $ids ?: [0])->update(['coordinator_user_id' => null]);
                foreach (\App\Models\Course::whereIn('id', $ids)->get() as $course) {
                    // Quem coordenava passa a assistente: não perde o vínculo com o curso.
                    if ($course->coordinator_user_id && $course->coordinator_user_id !== $user->id) {
                        $course->assistants()->syncWithoutDetaching([$course->coordinator_user_id]);
                    }
                    $course->update(['coordinator_user_id' => $user->id]);
                    $course->assistants()->detach($user->id);
                }
            }

            // Cursos em que é assistente (coordenação, Admin TI e funcionários).
            if ($request->has('assisted_submitted') || ! $user->canAssistCourses()) {
                $assisted = $user->canAssistCourses() ? array_map('intval', $validated['assisted_course_ids'] ?? []) : [];
                $coordinated = \App\Models\Course::where('coordinator_user_id', $user->id)->pluck('id')->all();
                $user->assistedCourses()->sync(array_values(array_diff($assisted, $coordinated)));
            }

            DB::commit();

            return redirect()->route('admin.users.show', $user)
                ->with('success', 'Usuário atualizado com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Erro ao atualizar usuário: ' . $e->getMessage());
        }
    }

    /**
     * Redefinir senha de um usuário
     */
    public function resetPassword(Request $request, User $user)
    {
        $this->ensureAdminTi();

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => true,
        ]);

        return back()->with('success', 'Senha redefinida com sucesso! O usuário deverá alterar na próxima autenticação.');
    }

    /**
     * Alternar status ativo/inativo
     */
    public function toggleStatus(User $user)
    {
        $this->ensureAdminTi();

        // Impedir que o último admin de TI seja desativado
        if ($user->isAdminTi() && Auth::id() === $user->id) {
            return back()->with('error', 'Você não pode desativar sua própria conta.');
        }

        if (!Schema::hasColumn('users', 'is_active')) {
            return back()->with('error', 'Coluna is_active não existe na tabela users.');
        }

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        $status = $user->is_active ? 'ativado' : 'desativado';
        return back()->with('success', "Usuário {$status} com sucesso!");
    }

    /**
     * Excluir usuário
     */
    public function destroy(Request $request, User $user)
    {
        $this->ensureAdminTi();

        // Impedir auto-exclusão
        if (Auth::id() === $user->id) {
            return back()->with('error', 'Você não pode deletar sua própria conta.');
        }

        // Impedir exclusão do último admin de TI
        $adminCount = User::where('role', 'admin')->count();
        if ($user->isAdminTi() && $adminCount <= 1) {
            return back()->with('error', 'Não é possível deletar o último administrador de TI do sistema.');
        }

        try {
            DB::beginTransaction();

            // Se for professor, desvincular mas manter registro para auditoria
            if ($user->professor_id && $user->professor) {
                // Apenas remover vínculo do usuário, não deletar o professor
                $user->professor_id = null;
                $user->save();
            }

            $userName = $user->name;
            $user->delete();

            DB::commit();

            return redirect()->route('admin.users.index')
                ->with('success', "Usuário \"{$userName}\" excluído com sucesso!");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erro ao excluir usuário: ' . $e->getMessage());
        }
    }

    /**
     * Criar ou atualizar registro de professor
     */
    private function createOrUpdateProfessor(User $user): void
    {
        if (!$user->isTeacher()) {
            return;
        }

        // Mantém o professor já vinculado (o e-mail do cadastro docente pode ser outro).
        $professor = ($user->professor_id ? Professor::find($user->professor_id) : null)
            ?? Professor::where('email', $user->email)->first();

        if ($professor) {
            $professor->update([
                'name' => $user->name,
                'registration' => $user->registration_number ?? $professor->registration,
            ]);
        } else {
            $professor = Professor::create([
                'name' => $user->name,
                'email' => $user->email,
                'registration' => $user->registration_number ?? 'TBD',
                'active' => true,
            ]);
        }

        // Vincular professor ao usuário
        $user->update([
            'professor_id' => $professor->id,
        ]);
    }
}
