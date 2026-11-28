<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuditDocumentController;
use App\Http\Controllers\AuditTemplateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfessorAvailabilityController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
});

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/semestre/selecionar', [DashboardController::class, 'selectAcademicTerm'])->name('academic-term.select');
    Route::get('/planejamento', [PlanningController::class, 'index'])->name('planning.index');
    Route::post('/planejamento/alocar/{offering}', [PlanningController::class, 'confirmProfessor'])->name('planning.confirm-professor');
    Route::post('/planejamento/alocar-lote', [PlanningController::class, 'confirmProfessorBulk'])->name('planning.confirm-professor-bulk');

    // Horários das turmas: consulta para todos os perfis (inclusive alunos).
    Route::get('/horarios', [\App\Http\Controllers\ScheduleController::class, 'index'])->name('schedules.index');

    // Montagem de horários (coordenação): professor, dia, blocos e sala com validação de choques.
    // Enturmação de alunos de matrizes antigas (históricos importados).
    Route::middleware('can:manage-planning')->prefix('enturmacao')->name('enrollment.')->group(function () {
        Route::get('/', [\App\Http\Controllers\EnrollmentController::class, 'index'])->name('index');
        Route::post('/gerar', [\App\Http\Controllers\EnrollmentController::class, 'plan'])->name('plan');
        Route::post('/abrir-turma', [\App\Http\Controllers\EnrollmentController::class, 'openClass'])->name('open-class');
    });

    Route::middleware('can:manage-planning')->prefix('alocacao')->name('allocation.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AllocationController::class, 'index'])->name('index');
        Route::post('/copiar', [\App\Http\Controllers\AllocationController::class, 'copy'])->name('copy');
        Route::put('/{offering}', [\App\Http\Controllers\AllocationController::class, 'update'])->name('update');
    });

    // Disponibilidade (CCG-FOR-02) e habilitação docente.
    // Ficam fora do grupo "access-catalog" para que o próprio professor tenha acesso;
    // a autorização é feita pela ProfessorPolicy.
    Route::prefix('admin/planejamento/professores/{professor}')
        ->name('admin.professors.')
        ->controller(ProfessorAvailabilityController::class)
        ->group(function () {
            Route::get('/disponibilidade', 'show')->name('availability')->can('view', 'professor');
            Route::put('/disponibilidade', 'updateAvailability')->name('update-availability')->can('updateAvailability', 'professor');
            Route::get('/disciplinas', 'showSubjects')->name('subjects')->can('view', 'professor');
            Route::put('/disciplinas', 'updateSubjects')->name('update-subjects')->can('updateSubjects', 'professor');
        });

    Route::middleware('can:access-catalog')->group(function () {
        Route::get('/admin', function () {
            return redirect()->route('admin.courses.index');
        })->name('admin.index');

        Route::get('/admin/planejamento', [\App\Http\Controllers\AdminCatalogController::class, 'planejamentoIndex'])->name('admin.planejamento.index');

        Route::prefix('admin/planejamento')->name('admin.')->group(function () {
            // Gestão de Usuários (Administrador de TI)
            Route::get('/usuarios', [\App\Http\Controllers\UserManagementController::class, 'index'])->name('users.index');
            Route::get('/usuarios/novo', [\App\Http\Controllers\UserManagementController::class, 'create'])->name('users.create');
            Route::post('/usuarios', [\App\Http\Controllers\UserManagementController::class, 'store'])->name('users.store');
            Route::get('/usuarios/{user}', [\App\Http\Controllers\UserManagementController::class, 'show'])->name('users.show');
            Route::get('/usuarios/{user}/editar', [\App\Http\Controllers\UserManagementController::class, 'edit'])->name('users.edit');
            Route::put('/usuarios/{user}', [\App\Http\Controllers\UserManagementController::class, 'update'])->name('users.update');
            Route::post('/usuarios/{user}/redefinir-senha', [\App\Http\Controllers\UserManagementController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('/usuarios/{user}/toggle-status', [\App\Http\Controllers\UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');
            Route::delete('/usuarios/{user}', [\App\Http\Controllers\UserManagementController::class, 'destroy'])->name('users.destroy');

            Route::get('/cursos', [\App\Http\Controllers\AdminCatalogController::class, 'coursesIndex'])->name('courses.index');
            Route::get('/cursos/novo', [\App\Http\Controllers\AdminCatalogController::class, 'courseCreate'])->name('courses.create');
            Route::post('/cursos', [\App\Http\Controllers\AdminCatalogController::class, 'courseStore'])->name('courses.store');
            Route::get('/cursos/{course}/editar', [\App\Http\Controllers\AdminCatalogController::class, 'courseEdit'])->name('courses.edit');
            Route::put('/cursos/{course}', [\App\Http\Controllers\AdminCatalogController::class, 'courseUpdate'])->name('courses.update');
            Route::delete('/cursos/{course}', [\App\Http\Controllers\AdminCatalogController::class, 'courseDestroy'])->name('courses.destroy');

            Route::get('/matrizes', [\App\Http\Controllers\AdminCatalogController::class, 'matricesIndex'])->name('matrices.index');
            Route::get('/matrizes/permitidas', [\App\Http\Controllers\AdminCatalogController::class, 'importScopesIndex'])->name('import-scopes.index');
            Route::get('/matrizes/permitidas/{course}/editar', [\App\Http\Controllers\AdminCatalogController::class, 'importScopeEdit'])->name('import-scopes.edit');
            Route::put('/matrizes/permitidas/{course}', [\App\Http\Controllers\AdminCatalogController::class, 'importScopeUpdate'])->name('import-scopes.update');
            Route::get('/matrizes/novo', [\App\Http\Controllers\AdminCatalogController::class, 'matrixCreate'])->name('matrices.create');
            Route::post('/matrizes', [\App\Http\Controllers\AdminCatalogController::class, 'matrixStore'])->name('matrices.store');
            Route::get('/matrizes/{matrix}/editar', [\App\Http\Controllers\AdminCatalogController::class, 'matrixEdit'])->name('matrices.edit');
            Route::put('/matrizes/{matrix}', [\App\Http\Controllers\AdminCatalogController::class, 'matrixUpdate'])->name('matrices.update');
            Route::delete('/matrizes/{matrix}', [\App\Http\Controllers\AdminCatalogController::class, 'matrixDestroy'])->name('matrices.destroy');
            
            // Gestão de disciplinas por matriz (grade curricular)
            Route::get('/matrizes/{matrix}/disciplinas', [\App\Http\Controllers\CurriculumMatrixDisciplineController::class, 'show'])->name('matrices.disciplines.show');
            Route::post('/matrizes/{matrix}/disciplinas', [\App\Http\Controllers\CurriculumMatrixDisciplineController::class, 'store'])->name('matrices.disciplines.store');
            Route::put('/matrizes/{matrix}/disciplinas/{discipline}', [\App\Http\Controllers\CurriculumMatrixDisciplineController::class, 'update'])->name('matrices.disciplines.update');
            Route::delete('/matrizes/{matrix}/disciplinas/{discipline}', [\App\Http\Controllers\CurriculumMatrixDisciplineController::class, 'destroy'])->name('matrices.disciplines.destroy');

            Route::get('/semestres', [\App\Http\Controllers\AdminCatalogController::class, 'termsIndex'])->name('semestres.index');
            Route::get('/semestres/novo', [\App\Http\Controllers\AdminCatalogController::class, 'termCreate'])->name('semestres.create');
            Route::post('/semestres', [\App\Http\Controllers\AdminCatalogController::class, 'termStore'])->name('semestres.store');
            Route::get('/semestres/{term}/editar', [\App\Http\Controllers\AdminCatalogController::class, 'termEdit'])->name('semestres.edit');
            Route::put('/semestres/{term}', [\App\Http\Controllers\AdminCatalogController::class, 'termUpdate'])->name('semestres.update');
            Route::delete('/semestres/{term}', [\App\Http\Controllers\AdminCatalogController::class, 'termDestroy'])->name('semestres.destroy');

            Route::get('/disciplinas', [\App\Http\Controllers\AdminCatalogController::class, 'subjectsIndex'])->name('subjects.index');
            Route::get('/disciplinas/novo', [\App\Http\Controllers\AdminCatalogController::class, 'subjectCreate'])->name('subjects.create');
            Route::post('/disciplinas', [\App\Http\Controllers\AdminCatalogController::class, 'subjectStore'])->name('subjects.store');
            Route::get('/disciplinas/{subject}/editar', [\App\Http\Controllers\AdminCatalogController::class, 'subjectEdit'])->name('subjects.edit');
            Route::put('/disciplinas/{subject}', [\App\Http\Controllers\AdminCatalogController::class, 'subjectUpdate'])->name('subjects.update');
            Route::delete('/disciplinas/{subject}', [\App\Http\Controllers\AdminCatalogController::class, 'subjectDestroy'])->name('subjects.destroy');

            Route::get('/professores', [\App\Http\Controllers\AdminCatalogController::class, 'professorsIndex'])->name('professors.index');
            Route::get('/professores/novo', [\App\Http\Controllers\AdminCatalogController::class, 'professorCreate'])->name('professors.create');
            Route::post('/professores', [\App\Http\Controllers\AdminCatalogController::class, 'professorStore'])->name('professors.store');
            Route::get('/professores/{professor}/editar', [\App\Http\Controllers\AdminCatalogController::class, 'professorEdit'])->name('professors.edit');
            Route::put('/professores/{professor}', [\App\Http\Controllers\AdminCatalogController::class, 'professorUpdate'])->name('professors.update');
            Route::delete('/professores/{professor}', [\App\Http\Controllers\AdminCatalogController::class, 'professorDestroy'])->name('professors.destroy');
        });
        Route::get('/cursos', [CatalogController::class, 'courses'])->name('catalog.courses');
        Route::get('/professores', [CatalogController::class, 'professors'])->name('catalog.professors');
        Route::get('/disciplinas', [CatalogController::class, 'subjects'])->name('catalog.subjects');
        Route::get('/alunos', [CatalogController::class, 'students'])->name('catalog.students');
        Route::get('/alunos/{user}', [CatalogController::class, 'studentDetail'])->name('catalog.student.detail');
    });

    Route::middleware('can:access-imports')->group(function () {
        Route::get('/importacoes/oferta-ubiqua', [ImportController::class, 'index'])->name('imports.ubiqua.index');
        Route::get('/importacoes/oferta-ubiqua/progresso', [ImportController::class, 'progressOfertaUbiqua'])->name('imports.ubiqua.progress');
        Route::post('/importacoes/oferta-ubiqua', [ImportController::class, 'storeOfertaUbiqua'])->name('imports.ubiqua.store');
        Route::post('/importacoes/oferta-ubiqua/zerar', [ImportController::class, 'resetOfertaUbiqua'])->name('imports.ubiqua.reset');

        Route::get('/importacoes/base-totvs', [ImportController::class, 'totvsIndex'])->name('imports.totvs.index');
        Route::get('/importacoes/base-totvs/progresso', [ImportController::class, 'progressTotvs'])->name('imports.totvs.progress');
        Route::post('/importacoes/base-totvs', [ImportController::class, 'storeTotvs'])->name('imports.totvs.store');
        Route::post('/importacoes/base-totvs/zerar', [ImportController::class, 'resetTotvs'])->name('imports.totvs.reset');

        Route::get('/importacoes/professores', [ImportController::class, 'professorsIndex'])->name('imports.professors.index');
        Route::post('/importacoes/professores', [ImportController::class, 'storeProfessors'])->name('imports.professors.store');
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
    Route::put('/perfil/dados-funcionais', [UserController::class, 'updateProfessionalData'])->name('profile.professional.update');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
