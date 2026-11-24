<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $email = strtolower(trim($credentials['email']));

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user || ! Auth::attempt(['email' => $email, 'password' => $credentials['password']])) {
            return back()->withErrors([
                'email' => 'Credenciais inválidas.',
            ])->onlyInput('email');
        }

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Este usuário está inativo. Consulte a coordenação.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        // Redirecionamento baseado em perfil
        if ($user->isAdminTi()) {
            if ($user->must_change_password) {
                return redirect()->route('profile.edit')->with('warning', 'Defina uma nova senha para continuar.');
            }
            return redirect()->route('admin.users.index');
        }

        if ($user->isCoordinator()) {
            if ($user->must_change_password) {
                return redirect()->route('profile.edit')->with('warning', 'Defina uma nova senha para continuar.');
            }
            return redirect()->route('admin.planejamento.index');
        }

        if ($user->isTeacher()) {
            if ($user->must_change_password) {
                return redirect()->route('profile.edit')->with('warning', 'Defina uma nova senha e confirme suas disciplinas para continuar.');
            }
            
            \Log::info('Teacher login', [
                'user_id' => $user->id,
                'professor_id' => $user->professor_id,
                'has_professor_relation' => $user->professor ? 'yes' : 'no',
                'professor' => $user->professor?->toArray(),
            ]);
            
            // Se professor tem registro vinculado, redirecionar para disponibilidade
            if ($user->professor_id && $user->professor) {
                \Log::info('Redirecting teacher to availability', [
                    'user_id' => $user->id,
                    'professor_id' => $user->professor_id,
                ]);
                return redirect()->route('admin.professors.availability', $user->professor);
            }
            
            // Caso contrário, ir para perfil para vincular professor
            \Log::warning('Teacher has no professor relation', [
                'user_id' => $user->id,
                'professor_id' => $user->professor_id,
            ]);
            return redirect()->route('profile.edit')->with('info', 'Complete seu perfil de professor para continuar.');
        }

        if ($user->isStaff()) {
            if ($user->must_change_password) {
                return redirect()->route('profile.edit')->with('warning', 'Defina uma nova senha para continuar.');
            }
            return redirect()->route('dashboard');
        }

        // Alunos e outros perfis
        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
