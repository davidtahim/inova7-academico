<?php

namespace App\Http\Middleware;

use App\Models\AcademicTerm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Semestre concluído (status "closed") não aceita alterações: só consulta.
 * Aplicado às rotas de planejamento; leituras (GET) passam normalmente.
 */
class EnsureTermIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $term = AcademicTerm::current();
        if ($term?->isClosed()) {
            return self::refuse($request, $term);
        }

        return $next($request);
    }

    public static function message(AcademicTerm $term): string
    {
        return "O semestre {$term->code} está concluído e não aceita alterações. "
            . 'Para corrigir algo, a coordenação precisa reabri-lo em Cadastros › Semestres.';
    }

    public static function refuse(Request $request, AcademicTerm $term): Response
    {
        $message = self::message($term);

        return $request->expectsJson()
            ? response()->json(['success' => false, 'saved' => false, 'message' => $message, 'errors' => [$message]], 423)
            : back()->with('error', $message);
    }
}
