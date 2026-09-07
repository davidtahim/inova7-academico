<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuditDocumentController;
use App\Http\Controllers\AuditTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/planejamento', [PlanningController::class, 'index'])->name('planning.index');
Route::get('/auditoria', [AuditController::class, 'index'])->name('audit.index');
Route::get('/auditoria/templates/novo', [AuditTemplateController::class, 'create'])->name('audit.templates.create');
Route::post('/auditoria/templates', [AuditTemplateController::class, 'store'])->name('audit.templates.store');
Route::get('/auditoria/documentos/emitir', [AuditDocumentController::class, 'create'])->name('audit.documents.create');
Route::post('/auditoria/documentos', [AuditDocumentController::class, 'store'])->name('audit.documents.store');
Route::get('/usuarios/novo', [UserController::class, 'create'])->name('users.create');
Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
