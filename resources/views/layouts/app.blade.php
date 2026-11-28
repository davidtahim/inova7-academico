<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Inova7 Acadêmico')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <script>
        // Aplica o menu recolhido antes da pintura, sem "piscar".
        try { if (localStorage.getItem('inova7.sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}
    </script>
</head>

<body>
    @auth
        <div class="app-shell">
            @include('layouts.partials.sidebar')
            <main class="content">
                @if (session('impersonator_id'))
                    <div class="impersonation-bar" role="status">
                        <span>Você está agindo como <strong>{{ auth()->user()->name }}</strong> (entrou como {{ session('impersonator_name') }}). Tudo o que salvar fica em nome do professor.</span>
                        <form method="POST" action="{{ route('impersonate.stop') }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-dark">Voltar para minha conta</button>
                        </form>
                    </div>
                @endif
                <header class="topbar">
                    <button class="topbar-menu-btn" type="button" data-sidebar-toggle aria-controls="sidebar"
                        aria-expanded="false" aria-label="Abrir menu">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
                    </button>
                    @php
                        // Trilha da área atual (primeiro segmento da URL), com link para o início da área.
                        $areas = [
                            'docente' => ['Área docente', 'teacher.dashboard'],
                            'planejamento' => ['Planejamento', 'allocation.board'],
                            'cadastros' => ['Cadastros acadêmicos', Gate::allows('manage-catalog') ? 'admin.planejamento.index' : 'admin.professors.index'],
                            'consultas' => ['Consultas', 'catalog.courses'],
                            'importacoes' => ['Importações', 'imports.ubiqua.index'],
                            'laboratorios' => ['Laboratórios', 'labs.index'],
                            'sistema' => ['Sistema', Gate::allows('manage-users') ? 'admin.users.index' : 'audit.index'],
                            'horarios' => ['Horários', 'schedules.index'],
                            'perfil' => ['Meu perfil', 'profile'],
                        ];
                        // Em páginas de erro a área pode estar fora do perfil: não oferecer o link.
                        $area = isset($exception) ? ['Inova7 Acadêmico', null] : ($areas[request()->segment(1)] ?? ['Inova7 Acadêmico', null]);
                    @endphp
                    <div class="topbar-title">
                        @if ($area[1] && Route::has($area[1]))
                            <a href="{{ route($area[1]) }}" class="text-uppercase text-secondary small text-decoration-none topbar-area">{{ $area[0] }}</a>
                        @else
                            <small class="text-uppercase text-secondary">{{ $area[0] }}</small>
                        @endif
                        <h1>@yield('page-title', 'Painel')</h1>
                    </div>
                    <div class="topbar-actions">
                        @php
                            $academicTerms = \App\Models\AcademicTerm::orderByDesc('code')->get();
                            $selectedAcademicTerm = \App\Models\AcademicTerm::current();
                        @endphp
                        @if ($academicTerms->isNotEmpty())
                            <form method="POST" action="{{ route('academic-term.select') }}"
                                class="d-flex align-items-center gap-2 topbar-semester-form">
                                @csrf
                                <label for="academic_term_id" class="small text-secondary mb-0">Semestre</label>
                                <select id="academic_term_id" name="academic_term_id"
                                    class="form-select form-select-sm topbar-semester-select" onchange="this.form.submit()">
                                    @foreach ($academicTerms as $optionTerm)
                                        <option value="{{ $optionTerm->id }}" @selected(optional($selectedAcademicTerm)->id == $optionTerm->id)>
                                            {{ $optionTerm->code }}{{ $optionTerm->isClosed() ? ' (concluído)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                        <span class="topbar-credit">desenvolvido por Inova7</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm topbar-logout">Sair</button>
                        </form>
                    </div>
                </header>
                @php
                    // Semestre concluído: avisa nas telas que alteram dados do semestre.
                    $closedTerm = \App\Models\AcademicTerm::current();
                    $closedTerm = $closedTerm?->isClosed() && request()->routeIs('allocation.*', 'enrollment.*', 'planning.*', 'teacher.availability', 'admin.professors.availability', 'profile.edit') ? $closedTerm : null;
                    $openTerm = $closedTerm ? \App\Models\AcademicTerm::where('status', '!=', 'closed')->orderByDesc('code')->first() : null;
                @endphp
                @if ($closedTerm)
                    <div class="alert alert-secondary d-flex flex-wrap align-items-center gap-2 mt-3 mb-0" role="status">
                        <span class="me-auto"><strong>{{ $closedTerm->code }} é um semestre concluído:</strong> somente consulta, nenhuma alteração é aceita.</span>
                        @if ($openTerm)
                            <form method="POST" action="{{ route('academic-term.select') }}" class="m-0">
                                @csrf
                                <input type="hidden" name="academic_term_id" value="{{ $openTerm->id }}">
                                <button type="submit" class="btn btn-sm btn-primary">Ir para {{ $openTerm->code }}</button>
                            </form>
                        @endif
                    </div>
                @endif
                @if (session('success'))
                    <div class="alert alert-success mt-3">{{ session('success') }}</div>
                @endif
                @if (session('warning'))
                    <div class="alert alert-warning mt-3">{{ session('warning') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger mt-3" role="alert">{{ session('error') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    @else
        <div class="container-fluid p-0">
            @yield('content')
        </div>
    @endauth
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mod_Security da hospedagem recusa formulários que enviam links (http://...): campos
        // marcados com data-strip-scheme vão sem o protocolo, e o servidor o recoloca.
        document.addEventListener('submit', function (ev) {
            ev.target.querySelectorAll('[data-strip-scheme]').forEach(function (input) {
                input.value = input.value.trim().replace(/^https?:\/\//i, '');
            });
        }, true);
    </script>
    @auth
        <script>
            (function () {
                const root = document.documentElement;
                const sidebar = document.getElementById('sidebar');
                const toggle = document.querySelector('[data-sidebar-toggle]');
                const collapse = document.querySelector('[data-sidebar-collapse]');
                if (!sidebar) return;
                const mobile = window.matchMedia('(max-width: 991px)');

                function setOpen(open) {
                    root.classList.toggle('sidebar-open', open);
                    toggle?.setAttribute('aria-expanded', String(open));
                    if (open) {
                        (sidebar.querySelector('.nav-item.is-active') || sidebar.querySelector('.nav-item'))?.focus();
                    } else if (sidebar.contains(document.activeElement)) {
                        toggle?.focus();
                    }
                }

                function setCollapsed(collapsed) {
                    root.classList.toggle('sidebar-collapsed', collapsed);
                    collapse?.setAttribute('aria-pressed', String(collapsed));
                    collapse?.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Recolher menu');
                    collapse?.setAttribute('title', collapsed ? 'Expandir menu' : 'Recolher menu');
                    try { localStorage.setItem('inova7.sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
                }

                setCollapsed(root.classList.contains('sidebar-collapsed'));
                toggle?.addEventListener('click', () => setOpen(!root.classList.contains('sidebar-open')));
                collapse?.addEventListener('click', () => setCollapsed(!root.classList.contains('sidebar-collapsed')));
                document.querySelectorAll('[data-sidebar-close]').forEach(el => el.addEventListener('click', () => setOpen(false)));
                sidebar.querySelectorAll('.nav-item').forEach(link => link.addEventListener('click', () => { if (mobile.matches) setOpen(false); }));
                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape' && root.classList.contains('sidebar-open')) setOpen(false);
                });
                mobile.addEventListener?.('change', () => setOpen(false));

                // Mantém o item ativo visível quando a lista de links tem rolagem.
                const nav = sidebar.querySelector('.sidebar-nav');
                const current = sidebar.querySelector('.nav-item.is-active');
                if (nav && current && current.offsetTop + current.offsetHeight > nav.clientHeight) {
                    nav.scrollTop = current.offsetTop - nav.clientHeight / 2;
                }
            })();
        </script>
    @endauth
</body>

</html>
