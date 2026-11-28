<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Quem entrou como professor não altera os dados de acesso dele (e-mail e senha). */
class BlockWhileImpersonating
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->session()->has('impersonator_id')) {
            return back()->with('error', 'Você está agindo como outro usuário: e-mail e senha só podem ser alterados pelo próprio professor.');
        }

        return $next($request);
    }
}
