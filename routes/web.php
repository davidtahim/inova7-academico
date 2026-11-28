<?php

use App\Http\Controllers\AdminCatalogController;
use App\Http\Controllers\AllocationController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuditDocumentController;
use App\Http\Controllers\AuditTemplateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CurriculumMatrixDisciplineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiagnosticsController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LabReservationController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ScenarioController;
use App\Http\Controllers\ProfessorAvailabilityController;
use App\Http\Controllers\ScheduleBoardController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SolverController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
});

/*
|--------------------------------------------------------------------------
| Mapa de rotas por área
|--------------------------------------------------------------------------
| Cada área tem um prefixo de URL e uma permissão (Gate) aplicada na rota,
| espelhando a matriz de User::can*():
|
|   /                Início (redireciona conforme o perfil)       todos
|   /horarios        Horários das turmas                          todos
|   /docente/...     Painel, disciplinas e disponibilidade        professor (teach)
|   /planejamento/.. Grade global, diagnóstico, versões, montagem, enturmação  admin, coordenação (manage-planning)
|   /cadastros/...   Cursos, matrizes, disciplinas, professores   admin, coordenação (manage-catalog)
|   /consultas/...   Catálogo somente leitura                     admin, coordenação, funcionário (access-catalog)
|   /laboratorios/.. Reservas de laboratórios (NTI)               todos exceto aluno; aprovação: admin (manage-labs)
|   /importacoes/..  Oferta Ubíqua, TOTVS, professores            admin, coordenação, funcionário (access-imports)
|   /sistema/...     Usuários e manutenção (manage-users), auditoria (access-audit)
|   /perfil          Meus dados e senha                           todos
|
| Os nomes das rotas foram mantidos; os endereços antigos redirecionam (301).
*/

// Início: visitante vê a apresentação do sistema; logado vai para o ponto de partida do perfil.
Route::get('/', function (\Illuminate\Http\Request $request) {
    return auth()->check() ? app()->call([app(DashboardController::class), '__invoke']) : view('welcome');
})->name('dashboard');
Route::view('/sobre', 'welcome')->name('about');

// Calendário público dos laboratórios (sem login): só reservas aprovadas, sem dados de quem pediu.
Route::get('/calendario-laboratorios', [LabReservationController::class, 'publicCalendar'])->name('labs.public');

Route::middleware('auth')->group(function () {
    /*
     * Arquivos do disco "public" (fotos de perfil). Normalmente o Apache entrega
     * direto pelo link public/storage; na HostGator o symlink não pode ser criado,
     * então, sem o link, a requisição cai aqui e o Laravel entrega o arquivo.
     */
    Route::get('/storage/{path}', function (string $path) {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        abort_if(str_contains($path, '..') || ! $disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    })->where('path', '.*')->name('storage.public');

    Route::post('/semestre/selecionar', [DashboardController::class, 'selectAcademicTerm'])->name('academic-term.select');
    Route::get('/horarios', [ScheduleController::class, 'index'])->middleware('course.scope')->name('schedules.index');

    // Área do professor: sempre o próprio cadastro, sem id na URL.
    Route::middleware('can:teach')->prefix('docente')->name('teacher.')->group(function () {
        Route::get('/', [UserController::class, 'profile'])->name('dashboard');
        Route::get('/disciplinas', [ProfessorAvailabilityController::class, 'mySubjects'])->name('subjects');
        Route::get('/disponibilidade', [ProfessorAvailabilityController::class, 'myAvailability'])->name('availability');
        Route::get('/disponibilidade/pdf', [UserController::class, 'exportTeacherAvailabilityDocument'])->name('availability.pdf');
    });

    // Planejamento do semestre (coordenação e admin).
    // term.open: num semestre concluído, o planejamento é só consulta.
    Route::middleware(['can:manage-planning', 'term.open', 'course.scope'])->prefix('planejamento')->group(function () {
        Route::redirect('/', '/planejamento/grade')->name('planning.home');

        Route::name('allocation.')->group(function () {
            Route::get('/grade', [ScheduleBoardController::class, 'index'])->name('board');
            Route::get('/grade/dados', [ScheduleBoardController::class, 'data'])->name('board.data');
            Route::post('/grade/mover', [ScheduleBoardController::class, 'move'])->name('board.move');
            Route::get('/resolvedor/problema', [SolverController::class, 'problem'])->name('solver.problem');
            Route::post('/resolvedor/aplicar', [SolverController::class, 'apply'])->name('solver.apply');
            Route::get('/diagnostico', [DiagnosticsController::class, 'index'])->name('diagnosis');
            Route::get('/diagnostico/dados', [DiagnosticsController::class, 'data'])->name('diagnosis.data');

            Route::get('/versoes', [ScenarioController::class, 'index'])->name('scenarios');
            Route::post('/versoes', [ScenarioController::class, 'store'])->name('scenarios.store');
            Route::get('/versoes/exportar', [ScenarioController::class, 'export'])->name('scenarios.export');
            Route::post('/versoes/importar', [ScenarioController::class, 'import'])->name('scenarios.import');
            Route::get('/versoes/{scenario}/arquivo', [ScenarioController::class, 'download'])->name('scenarios.download');
            Route::post('/versoes/{scenario}/restaurar', [ScenarioController::class, 'restore'])->name('scenarios.restore');
            Route::delete('/versoes/{scenario}', [ScenarioController::class, 'destroy'])->name('scenarios.destroy');

            Route::get('/montagem', [AllocationController::class, 'index'])->name('index');
            Route::post('/montagem/copiar', [AllocationController::class, 'copy'])->name('copy');
            Route::put('/montagem/{offering}', [AllocationController::class, 'update'])->name('update');

            Route::post('/publicar', [AllocationController::class, 'publish'])->name('publish');
            Route::post('/despublicar', [AllocationController::class, 'unpublish'])->name('unpublish');
        });

        Route::prefix('enturmacao')->name('enrollment.')->group(function () {
            Route::get('/', [EnrollmentController::class, 'index'])->name('index');
            Route::post('/gerar', [EnrollmentController::class, 'plan'])->name('plan');
            Route::post('/abrir-turma', [EnrollmentController::class, 'openClass'])->name('open-class');
            Route::post('/importar-historicos', [EnrollmentController::class, 'importHistories'])->name('import-histories');
        });

        Route::prefix('turmas')->name('planning.')->group(function () {
            Route::get('/', [PlanningController::class, 'index'])->name('index');
            Route::post('/confirmar-lote', [PlanningController::class, 'confirmProfessorBulk'])->name('confirm-professor-bulk');
            Route::post('/{offering}/confirmar', [PlanningController::class, 'confirmProfessor'])->name('confirm-professor');
        });
    });

    // Cadastros acadêmicos (admin e coordenação).
    Route::prefix('cadastros')->middleware('course.scope')->name('admin.')->group(function () {
        Route::middleware('can:manage-catalog')->group(function () {
            Route::get('/', [AdminCatalogController::class, 'planejamentoIndex'])->name('planejamento.index');

            Route::get('/cursos', [AdminCatalogController::class, 'coursesIndex'])->name('courses.index');
            Route::get('/cursos/novo', [AdminCatalogController::class, 'courseCreate'])->name('courses.create');
            Route::post('/cursos', [AdminCatalogController::class, 'courseStore'])->name('courses.store');
            Route::get('/cursos/{course}/editar', [AdminCatalogController::class, 'courseEdit'])->name('courses.edit');
            Route::put('/cursos/{course}', [AdminCatalogController::class, 'courseUpdate'])->name('courses.update');
            Route::delete('/cursos/{course}', [AdminCatalogController::class, 'courseDestroy'])->name('courses.destroy');
            Route::post('/cursos/{course}/matrizes', [AdminCatalogController::class, 'courseAttachMatrix'])->name('courses.attach-matrix');

            Route::get('/matrizes', [AdminCatalogController::class, 'matricesIndex'])->name('matrices.index');
            Route::get('/matrizes/permitidas', [AdminCatalogController::class, 'importScopesIndex'])->name('import-scopes.index');
            Route::get('/matrizes/permitidas/{course}/editar', [AdminCatalogController::class, 'importScopeEdit'])->name('import-scopes.edit');
            Route::put('/matrizes/permitidas/{course}', [AdminCatalogController::class, 'importScopeUpdate'])->name('import-scopes.update');
            Route::get('/matrizes/novo', [AdminCatalogController::class, 'matrixCreate'])->name('matrices.create');
            Route::post('/matrizes', [AdminCatalogController::class, 'matrixStore'])->name('matrices.store');
            Route::get('/matrizes/{matrix}/editar', [AdminCatalogController::class, 'matrixEdit'])->name('matrices.edit');
            Route::put('/matrizes/{matrix}', [AdminCatalogController::class, 'matrixUpdate'])->name('matrices.update');
            Route::delete('/matrizes/{matrix}', [AdminCatalogController::class, 'matrixDestroy'])->name('matrices.destroy');
            Route::get('/matrizes/{matrix}/disciplinas', [CurriculumMatrixDisciplineController::class, 'show'])->name('matrices.disciplines.show');
            Route::post('/matrizes/{matrix}/disciplinas', [CurriculumMatrixDisciplineController::class, 'store'])->name('matrices.disciplines.store');
            Route::put('/matrizes/{matrix}/disciplinas/{discipline}', [CurriculumMatrixDisciplineController::class, 'update'])->name('matrices.disciplines.update');
            Route::delete('/matrizes/{matrix}/disciplinas/{discipline}', [CurriculumMatrixDisciplineController::class, 'destroy'])->name('matrices.disciplines.destroy');

            Route::get('/disciplinas', [AdminCatalogController::class, 'subjectsIndex'])->name('subjects.index');
            Route::get('/disciplinas/novo', [AdminCatalogController::class, 'subjectCreate'])->name('subjects.create');
            Route::post('/disciplinas', [AdminCatalogController::class, 'subjectStore'])->name('subjects.store');
            Route::get('/disciplinas/{subject}/editar', [AdminCatalogController::class, 'subjectEdit'])->name('subjects.edit');
            Route::put('/disciplinas/{subject}', [AdminCatalogController::class, 'subjectUpdate'])->name('subjects.update');
            Route::delete('/disciplinas/{subject}', [AdminCatalogController::class, 'subjectDestroy'])->name('subjects.destroy');

            Route::get('/semestres', [AdminCatalogController::class, 'termsIndex'])->name('semestres.index');
            Route::get('/semestres/novo', [AdminCatalogController::class, 'termCreate'])->name('semestres.create');
            Route::post('/semestres', [AdminCatalogController::class, 'termStore'])->name('semestres.store');
            Route::get('/semestres/{term}/editar', [AdminCatalogController::class, 'termEdit'])->name('semestres.edit');
            Route::put('/semestres/{term}', [AdminCatalogController::class, 'termUpdate'])->name('semestres.update');
            Route::delete('/semestres/{term}', [AdminCatalogController::class, 'termDestroy'])->name('semestres.destroy');

            Route::get('/professores/novo', [AdminCatalogController::class, 'professorCreate'])->name('professors.create');
            Route::post('/professores', [AdminCatalogController::class, 'professorStore'])->name('professors.store');
            Route::delete('/professores/{professor}', [AdminCatalogController::class, 'professorDestroy'])->name('professors.destroy');
        });

        // Lista e dados funcionais de professores: o funcionário também mantém.
        Route::middleware('can:access-catalog')->group(function () {
            Route::get('/professores', [AdminCatalogController::class, 'professorsIndex'])->name('professors.index');
            Route::get('/professores/{professor}/editar', [AdminCatalogController::class, 'professorEdit'])->name('professors.edit');
            Route::put('/professores/{professor}', [AdminCatalogController::class, 'professorUpdate'])->name('professors.update');
        });

        // Disponibilidade (CCG-FOR-02) e habilitação de um professor: ProfessorPolicy
        // (coordenação edita; funcionário consulta; o próprio professor usa /docente).
        Route::prefix('professores/{professor}')->name('professors.')->controller(ProfessorAvailabilityController::class)->group(function () {
            Route::get('/disponibilidade', 'show')->name('availability')->can('view', 'professor');
            // PUT ou POST: hospedagens compartilhadas às vezes bloqueiam PUT vindo de fetch().
            Route::match(['put', 'post'], '/disponibilidade', 'updateAvailability')->name('update-availability')->can('updateAvailability', 'professor');
            Route::post('/disponibilidade/copiar', 'copyAvailability')->name('copy-availability')->can('updateAvailability', 'professor');
            Route::get('/disciplinas', 'showSubjects')->name('subjects')->can('view', 'professor');
            Route::put('/disciplinas', 'updateSubjects')->name('update-subjects')->can('updateSubjects', 'professor');
        });
    });

    // Laboratórios de informática (NTI): grade de cada laboratório, pedidos e aprovação.
    Route::middleware('can:view-labs')->prefix('laboratorios')->name('labs.')->controller(LabReservationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/reservas', 'store')->name('reservations.store');
        Route::post('/reservas/{reservation}/cancelar', 'cancel')->name('reservations.cancel');
        Route::middleware('can:manage-labs')->group(function () {
            Route::post('/reservas/{reservation}/aprovar', 'approve')->name('reservations.approve');
            Route::post('/reservas/{reservation}/recusar', 'reject')->name('reservations.reject');
            Route::post('/sincronizar', 'sync')->name('sync');
            Route::get('/cadastro', 'rooms')->name('rooms');
            Route::post('/cadastro', 'storeRoom')->name('rooms.store');
            Route::put('/cadastro/{room}', 'updateRoom')->name('rooms.update');
        });
    });

    // Consultas somente leitura (inclui o funcionário).
    Route::middleware('can:access-catalog')->prefix('consultas')->name('catalog.')->group(function () {
        Route::get('/cursos', [CatalogController::class, 'courses'])->name('courses');
        Route::get('/professores', [CatalogController::class, 'professors'])->name('professors');
        Route::get('/disciplinas', [CatalogController::class, 'subjects'])->name('subjects');
        Route::get('/alunos', [CatalogController::class, 'students'])->name('students');
        Route::get('/alunos/{user}', [CatalogController::class, 'studentDetail'])->name('student.detail');
    });

    Route::middleware('can:access-imports')->prefix('importacoes')->name('imports.')->group(function () {
        Route::get('/oferta-ubiqua', [ImportController::class, 'index'])->name('ubiqua.index');
        Route::get('/oferta-ubiqua/progresso', [ImportController::class, 'progressOfertaUbiqua'])->name('ubiqua.progress');
        Route::post('/oferta-ubiqua', [ImportController::class, 'storeOfertaUbiqua'])->name('ubiqua.store');
        Route::post('/oferta-ubiqua/zerar', [ImportController::class, 'resetOfertaUbiqua'])->name('ubiqua.reset');

        Route::get('/base-totvs', [ImportController::class, 'totvsIndex'])->name('totvs.index');
        Route::get('/base-totvs/progresso', [ImportController::class, 'progressTotvs'])->name('totvs.progress');
        Route::post('/base-totvs', [ImportController::class, 'storeTotvs'])->name('totvs.store');
        Route::post('/base-totvs/zerar', [ImportController::class, 'resetTotvs'])->name('totvs.reset');

        Route::get('/professores', [ImportController::class, 'professorsIndex'])->name('professors.index');
        Route::post('/professores', [ImportController::class, 'storeProfessors'])->name('professors.store');
    });

    Route::prefix('sistema')->group(function () {
        // Entrar como professor (coordenação e TI) e voltar para a própria conta.
        Route::post('/entrar-como/{user}', [\App\Http\Controllers\ImpersonationController::class, 'start'])->name('impersonate.start');
        Route::post('/voltar-para-minha-conta', [\App\Http\Controllers\ImpersonationController::class, 'stop'])->name('impersonate.stop');

        // Manutenção sem terminal (HostGator): migrations e caches pelo navegador.
        Route::middleware('can:manage-users')->prefix('manutencao')->name('system.maintenance')->controller(MaintenanceController::class)->group(function () {
            Route::get('/', 'index')->name('');
            Route::post('/migrations', 'migrate')->name('.migrate');
            Route::post('/caches', 'clearCaches')->name('.clear');
            Route::post('/storage-link', 'storageLink')->name('.storage-link');
        });

        Route::middleware('can:manage-users')->prefix('usuarios')->name('admin.users.')->controller(UserManagementController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/novo', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{user}', 'show')->name('show');
            Route::get('/{user}/editar', 'edit')->name('edit');
            Route::put('/{user}', 'update')->name('update');
            Route::post('/{user}/redefinir-senha', 'resetPassword')->name('reset-password');
            Route::post('/{user}/toggle-status', 'toggleStatus')->name('toggle-status');
            Route::delete('/{user}', 'destroy')->name('destroy');
        });

        Route::middleware(['can:access-audit', 'course.scope'])->prefix('auditoria')->name('audit.')->group(function () {
            Route::get('/', [AuditController::class, 'index'])->name('index');
            Route::get('/templates/novo', [AuditTemplateController::class, 'create'])->name('templates.create');
            Route::post('/templates', [AuditTemplateController::class, 'store'])->name('templates.store');
            Route::get('/documentos/emitir', [AuditDocumentController::class, 'create'])->name('documents.create');
            Route::post('/documentos', [AuditDocumentController::class, 'store'])->name('documents.store');

            // Auditoria de curso (QUA-INT-08): indicadores, documentos e resultado previsto por semestre.
            Route::prefix('cursos')->name('courses.')->controller(\App\Http\Controllers\CourseAuditController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{course}', 'show')->name('show');
                Route::post('/{course}/dados', 'updateHeader')->name('header');
                Route::post('/{course}/indicador/{code}', 'updateIndicator')->where('code', '[0-9.]+')->name('indicator');
                Route::get('/{course}/documento/{document}', 'download')->name('download');
            });
        });
    });

    Route::get('/perfil', [UserController::class, 'profile'])->name('profile');
    Route::get('/perfil/editar', [UserController::class, 'editProfile'])->name('profile.edit');
    Route::put('/perfil', [UserController::class, 'updateProfile'])->middleware('not.impersonating')->name('profile.update');
    Route::put('/perfil/dados-funcionais', [UserController::class, 'updateProfessionalData'])->name('profile.professional.update');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    /*
     * Endereços antigos (favoritos, links enviados por e-mail): redirecionamento
     * permanente para a área nova, preservando o restante do caminho e a query string.
     */
    $legacy = function (string $from, string $to) {
        Route::get($from . '/{rest?}', function (\Illuminate\Http\Request $request, ?string $rest = null) use ($to) {
            $target = rtrim($to . '/' . ltrim((string) $rest, '/'), '/');
            $query = $request->getQueryString();

            return redirect($target . ($query ? '?' . $query : ''), 301);
        })->where('rest', '.*');
    };
    $legacy('/admin/planejamento/usuarios', '/sistema/usuarios');
    $legacy('/admin/planejamento', '/cadastros');
    $legacy('/admin', '/cadastros');
    $legacy('/alocacao/grade', '/planejamento/grade');
    $legacy('/alocacao', '/planejamento/montagem');
    $legacy('/enturmacao', '/planejamento/enturmacao');
    $legacy('/auditoria', '/sistema/auditoria');
    $legacy('/perfil/disponibilidade/pdf', '/docente/disponibilidade/pdf');
    foreach (['cursos', 'professores', 'disciplinas', 'alunos'] as $area) {
        $legacy('/' . $area, '/consultas/' . $area);
    }
});
