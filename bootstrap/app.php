<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // As regras de autenticação serão adicionadas na próxima etapa.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Mantém os tratamentos padrão do Laravel.
    })->create();
