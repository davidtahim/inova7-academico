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

        if ($user->role === 'teacher') {
            if ($user->must_change_password) {
                return redirect()->route('profile.edit')->with('warning', 'Defina uma nova senha e confirme suas disciplinas para continuar.');
            }

            return redirect()->route('profile.edit');
        }

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
