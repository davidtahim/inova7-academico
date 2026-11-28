@php
    /*
     * Menu lateral: organizado por tarefa e filtrado por permissão (Gates).
     * Cada item: label, url, icon e "active" (padrões de rota que o destacam).
     */
    $user = Auth::user();
    $isTeacher = Gate::allows('teach');
    $canPlan = Gate::allows('manage-planning');
    $canManageCatalog = Gate::allows('manage-catalog');

    $sections = [];

    // Início: cada perfil tem o seu ponto de partida.
    $sections[] = ['label' => null, 'items' => array_values(array_filter([
        $isTeacher
            ? ['label' => 'Meu painel docente', 'url' => route('teacher.dashboard'), 'icon' => 'home', 'active' => ['teacher.dashboard']]
            : ($user->isStudent() ? null : ['label' => 'Painel do semestre', 'url' => route('dashboard'), 'icon' => 'home', 'active' => ['dashboard']]),
        ['label' => $user->isStudent() ? 'Meus horários' : 'Horários das turmas', 'url' => route('schedules.index'), 'icon' => 'clock', 'active' => ['schedules.*']],
    ]))];

    if ($isTeacher) {
        $sections[] = ['label' => 'Docência', 'items' => [
            ['label' => 'Minhas disciplinas', 'url' => route('teacher.subjects'), 'icon' => 'book', 'active' => ['teacher.subjects']],
            ['label' => 'Minha disponibilidade', 'url' => route('teacher.availability'), 'icon' => 'calendar', 'active' => ['teacher.availability']],
        ]];
    }

    // Fluxo do semestre, na ordem em que a coordenação trabalha.
    if ($canPlan) {
        $sections[] = ['label' => 'Planejamento', 'items' => [
            ['label' => 'Professores por turma', 'url' => route('planning.index'), 'icon' => 'teacher', 'active' => ['planning.*']],
            ['label' => 'Grade global', 'url' => route('allocation.board'), 'icon' => 'layers', 'active' => ['allocation.board*', 'allocation.solver.*']],
            ['label' => 'Diagnóstico', 'url' => route('allocation.diagnosis'), 'icon' => 'alert', 'active' => ['allocation.diagnosis*']],
            ['label' => 'Versões da grade', 'url' => route('allocation.scenarios'), 'icon' => 'history', 'active' => ['allocation.scenarios*']],
            ['label' => 'Montagem por turma', 'url' => route('allocation.index'), 'icon' => 'grid', 'active' => ['allocation.index', 'allocation.update', 'allocation.copy']],
            ['label' => 'Enturmação', 'url' => route('enrollment.index'), 'icon' => 'users', 'active' => ['enrollment.*']],
        ]];
    }

    if ($canManageCatalog) {
        $sections[] = ['label' => 'Cadastros', 'items' => [
            ['label' => 'Visão geral', 'url' => route('admin.planejamento.index'), 'icon' => 'grid', 'active' => ['admin.planejamento.index']],
            ['label' => 'Cursos', 'url' => route('admin.courses.index'), 'icon' => 'cap', 'active' => ['admin.courses.*']],
            ['label' => 'Matrizes curriculares', 'url' => route('admin.matrices.index'), 'icon' => 'layers', 'active' => ['admin.matrices.*', 'admin.import-scopes.*']],
            ['label' => 'Disciplinas', 'url' => route('admin.subjects.index'), 'icon' => 'bookOpen', 'active' => ['admin.subjects.*']],
            ['label' => 'Professores', 'url' => route('admin.professors.index'), 'icon' => 'teacher', 'active' => ['admin.professors.*']],
            ['label' => 'Semestres', 'url' => route('admin.semestres.index'), 'icon' => 'flag', 'active' => ['admin.semestres.*']],
            ['label' => 'Alunos', 'url' => route('catalog.students'), 'icon' => 'users', 'active' => ['catalog.students', 'catalog.student.detail']],
        ]];
    } elseif (Gate::allows('access-catalog')) {
        $sections[] = ['label' => 'Consultas', 'items' => [
            ['label' => 'Cursos e matrizes', 'url' => route('catalog.courses'), 'icon' => 'cap', 'active' => ['catalog.courses']],
            ['label' => 'Professores', 'url' => route('catalog.professors'), 'icon' => 'teacher', 'active' => ['catalog.professors', 'admin.professors.*']],
            ['label' => 'Disciplinas', 'url' => route('catalog.subjects'), 'icon' => 'bookOpen', 'active' => ['catalog.subjects']],
            ['label' => 'Alunos', 'url' => route('catalog.students'), 'icon' => 'users', 'active' => ['catalog.students', 'catalog.student.detail']],
        ]];
    }

    if (Gate::allows('access-imports')) {
        $sections[] = ['label' => 'Importações', 'items' => [
            ['label' => 'Oferta Ubíqua', 'url' => route('imports.ubiqua.index'), 'icon' => 'upload', 'active' => ['imports.ubiqua.*']],
            ['label' => 'Base TOTVS', 'url' => route('imports.totvs.index'), 'icon' => 'database', 'active' => ['imports.totvs.*']],
            ['label' => 'Cadastro de professores', 'url' => route('imports.professors.index'), 'icon' => 'fileUp', 'active' => ['imports.professors.*']],
        ]];
    }

    $systemItems = array_values(array_filter([
        Gate::allows('manage-users') ? ['label' => 'Usuários e perfis', 'url' => route('admin.users.index'), 'icon' => 'shield', 'active' => ['admin.users.*']] : null,
        Gate::allows('access-audit') ? ['label' => 'Auditoria', 'url' => route('audit.index'), 'icon' => 'clipboard', 'active' => ['audit.*']] : null,
    ]));
    if ($systemItems !== []) {
        $sections[] = ['label' => 'Sistema', 'items' => $systemItems];
    }

    $icons = [
        'home' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
        'calendar' => 'M8 2v4M16 2v4M3 9h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
        'book' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5',
        'clock' => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 6v6l4 2',
        'grid' => 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
        'cap' => 'M22 10 12 5 2 10l10 5 10-5zM6 12v5c3 2 9 2 12 0v-5',
        'layers' => 'M12 2 2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
        'bookOpen' => 'M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2zM22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z',
        'teacher' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM16 11l2 2 4-4',
        'flag' => 'M8 2v4M16 2v4M3 9h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01',
        'users' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
        'upload' => 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12',
        'database' => 'M12 8c4.97 0 9-1.34 9-3s-4.03-3-9-3-9 1.34-9 3 4.03 3 9 3zM3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5M3 12c0 1.66 4 3 9 3s9-1.34 9-3',
        'fileUp' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M12 18v-6M9 15l3-3 3 3',
        'shield' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4',
        'clipboard' => 'M9 2h6a1 1 0 0 1 1 1v2H8V3a1 1 0 0 1 1-1zM16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M9 14l2 2 4-4',
        'user' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'logout' => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
        'panel' => 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM9 3v18',
        'x' => 'M18 6 6 18M6 6l12 12',
        'history' => 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2',
        'alert' => 'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01',
    ];
    $icon = fn (string $name) => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . $icons[$name] . '"/></svg>';

    $initials = strtoupper(Str::of($user->name)->split('/\s+/')->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->implode(''));
@endphp

<aside class="sidebar" id="sidebar" aria-label="Menu principal">
    <div class="sidebar-head">
        <a class="brand" href="{{ $isTeacher ? route('teacher.dashboard') : route('dashboard') }}" title="Inova7 Acadêmico — início">
            <span class="brand-mark">7</span>
            <span class="brand-text"><strong>INOVA7</strong><small>Gestão Acadêmica</small></span>
        </a>
        <button type="button" class="sidebar-icon-btn sidebar-collapse-btn" data-sidebar-collapse
            aria-label="Recolher menu" title="Recolher menu" aria-pressed="false">{!! $icon('panel') !!}</button>
        <button type="button" class="sidebar-icon-btn sidebar-close-btn" data-sidebar-close aria-label="Fechar menu">{!! $icon('x') !!}</button>
    </div>

    <nav class="sidebar-nav">
        @foreach ($sections as $section)
            <div class="nav-group">
                @if ($section['label'])
                    <p class="nav-group-label">{{ $section['label'] }}</p>
                @endif
                <ul>
                    @foreach ($section['items'] as $item)
                        @php
                            $isActive = request()->routeIs(...$item['active']);
                        @endphp
                        <li>
                            <a href="{{ $item['url'] }}" class="nav-item {{ $isActive ? 'is-active' : '' }}"
                                @if ($isActive) aria-current="page" @endif title="{{ $item['label'] }}">
                                {!! $icon($item['icon']) !!}
                                <span class="nav-item-label">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="sidebar-user">
        @if ($user->photo_path)
            <img src="{{ Storage::disk('public')->url($user->photo_path) }}" alt="" class="avatar" style="object-fit:cover;">
        @else
            <span class="avatar" aria-hidden="true">{{ $initials }}</span>
        @endif
        <span class="sidebar-user-text">
            <strong title="{{ $user->name }}">{{ $user->name }}</strong>
            <small>{{ $user->role_label }}</small>
        </span>
        <span class="sidebar-user-actions">
            <a href="{{ $isTeacher ? route('profile.edit') : route('profile') }}" class="sidebar-icon-btn {{ request()->routeIs('profile', 'profile.edit') ? 'is-active' : '' }}"
                title="Meu perfil e senha" aria-label="Meu perfil e senha">{!! $icon('user') !!}</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-icon-btn" title="Sair" aria-label="Sair">{!! $icon('logout') !!}</button>
            </form>
        </span>
    </div>
</aside>
<div class="sidebar-backdrop" data-sidebar-close></div>
