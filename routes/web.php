<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuditDocumentController;
use App\Http\Controllers\AuditTemplateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
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
    Route::get('/auditoria', [AuditController::class, 'index'])->name('audit.index');
    Route::get('/auditoria/templates/novo', [AuditTemplateController::class, 'create'])->name('audit.templates.create');
    Route::post('/auditoria/templates', [AuditTemplateController::class, 'store'])->name('audit.templates.store');
    Route::get('/auditoria/documentos/emitir', [AuditDocumentController::class, 'create'])->name('audit.documents.create');
    Route::post('/auditoria/documentos', [AuditDocumentController::class, 'store'])->name('audit.documents.store');
    Route::get('/perfil', [UserController::class, 'profile'])->name('profile');
    Route::get('/perfil/editar', [UserController::class, 'editProfile'])->name('profile.edit');
    Route::put('/perfil', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
