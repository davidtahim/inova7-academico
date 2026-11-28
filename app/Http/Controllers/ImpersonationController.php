<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * "Entrar como professor": a coordenação ou o Administrador de TI preenchem disponibilidade e
 * disciplinas pelo professor, sem a senha dele. A sessão guarda quem entrou, para voltar.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, User $user)
    {
        Gate::authorize('impersonate', $user);

        $impersonator = $request->user();
        $back = url()->previous();
        Log::info('Entrar como', ['por' => $impersonator->id, 'como' => $user->id]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $impersonator->id);
        $request->session()->put('impersonator_name', $impersonator->name);
        $request->session()->put('impersonator_back', $back);

        return redirect()->route('teacher.dashboard')
            ->with('success', "Você está agindo como {$user->name}. Use \"Voltar para minha conta\" no topo ao terminar.");
    }

    public function stop(Request $request)
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        $back = $request->session()->pull('impersonator_back');
        $request->session()->forget('impersonator_name');
        $impersonator = $impersonatorId ? User::find($impersonatorId) : null;

        if (! $impersonator) {
            return redirect()->route('dashboard');
        }

        $was = $request->user()?->name;
        Log::info('Voltar da conta', ['por' => $impersonator->id, 'como' => $request->user()?->id]);
        Auth::login($impersonator);
        $request->session()->regenerate();

        return redirect()->to($back ?: route('dashboard'))->with('success', "Você voltou para a sua conta. Saiu da conta de {$was}.");
    }
}
