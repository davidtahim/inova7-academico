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
                <header class="topbar">
                    <button class="topbar-menu-btn" type="button" data-sidebar-toggle aria-controls="sidebar"
                        aria-expanded="false" aria-label="Abrir menu">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
                    </button>
                    <div class="topbar-title"><small class="text-uppercase text-secondary">Inova7 Acadêmico</small>
                        <h1>@yield('page-title', 'Painel')</h1>
                    </div>
                    <div class="topbar-actions">
                        @php
                            $academicTerms = \App\Models\AcademicTerm::orderByDesc('id')->get();
                            $selectedAcademicTermId = session('selected_academic_term_id');
                            $selectedAcademicTerm =
                                $academicTerms->firstWhere('id', $selectedAcademicTermId) ?? $academicTerms->first();
                        @endphp
                        @if ($academicTerms->isNotEmpty())
                            <form method="POST" action="{{ route('academic-term.select') }}"
                                class="d-flex align-items-center gap-2 topbar-semester-form">
                                @csrf
                                <label for="academic_term_id" class="small text-secondary mb-0">Semestre</label>
                                <select id="academic_term_id" name="academic_term_id"
                                    class="form-select form-select-sm topbar-semester-select" onchange="this.form.submit()">
                                    @foreach ($academicTerms as $term)
                                        <option value="{{ $term->id }}"
                                            {{ optional($selectedAcademicTerm)->id == $term->id ? 'selected' : '' }}>
                                            {{ $term->code }}
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
                @if (session('success'))
                    <div class="alert alert-success mt-3">{{ session('success') }}</div>
                @endif
                @if (session('warning'))
                    <div class="alert alert-warning mt-3">{{ session('warning') }}</div>
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
