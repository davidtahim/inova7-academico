<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuditDocumentController;
use App\Http\Controllers\AuditTemplateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
    Route::get('/usuarios/novo', [UserController::class, 'create'])->name('users.create');
    Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/planejamento', [PlanningController::class, 'index'])->name('planning.index');

    Route::middleware('can:access-catalog')->group(function () {
        Route::get('/cursos', [CatalogController::class, 'courses'])->name('catalog.courses');
        Route::get('/professores', [CatalogController::class, 'professors'])->name('catalog.professors');
        Route::get('/disciplinas', [CatalogController::class, 'subjects'])->name('catalog.subjects');
        Route::get('/alunos', [CatalogController::class, 'students'])->name('catalog.students');
    });

    Route::middleware('can:access-imports')->group(function () {
        Route::get('/importacoes/oferta-ubiqua', [ImportController::class, 'index'])->name('imports.ubiqua.index');
        Route::post('/importacoes/oferta-ubiqua', [ImportController::class, 'storeOfertaUbiqua'])->name('imports.ubiqua.store');
    });

    Route::middleware('can:access-audit')->group(function () {
        Route::get('/auditoria', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/auditoria/templates/novo', [AuditTemplateController::class, 'create'])->name('audit.templates.create');
        Route::post('/auditoria/templates', [AuditTemplateController::class, 'store'])->name('audit.templates.store');
        Route::get('/auditoria/documentos/emitir', [AuditDocumentController::class, 'create'])->name('audit.documents.create');
        Route::post('/auditoria/documentos', [AuditDocumentController::class, 'store'])->name('audit.documents.store');
    });

    Route::get('/perfil', [UserController::class, 'profile'])->name('profile');
    Route::get('/perfil/editar', [UserController::class, 'editProfile'])->name('profile.edit');
    Route::get('/perfil/disponibilidade/pdf', [UserController::class, 'exportTeacherAvailabilityDocument'])->name('teacher.availability.pdf');
    Route::put('/perfil', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
