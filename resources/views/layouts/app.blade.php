<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Inova7 Acadêmico')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>

<body>
    @auth
        <div class="app-shell">
            <aside class="sidebar" id="sidebar">
                <a class="brand" href="{{ route('dashboard') }}"><span
                        class="brand-mark">7</span><span><strong>INOVA7</strong><small>Gestão Acadêmica</small></span></a>
                <nav class="nav flex-column gap-1 mt-4">
                    <details class="nav-section-group" open>
                        <summary class="nav-section">
                            <span>Geral</span>
                            <span class="nav-caret">▾</span>
                        </summary>
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                            href="{{ route('dashboard') }}">▦ Painel</a>

                        @if (in_array(Auth::user()->role, ['student', 'teacher', 'coordinator', 'staff', 'admin'], true))
                            <a class="nav-link {{ request()->routeIs('planning.*') ? 'active' : '' }}"
                                href="{{ route('planning.index') }}">▤ Planejamento</a>
                        @endif

                        <a class="nav-link {{ request()->routeIs('profile') || request()->routeIs('profile.edit') ? 'active' : '' }}"
                            href="{{ route('profile') }}">◉ Meu perfil</a>
                    </details>

                    @if (Auth::user()->role === 'admin')
                        <details class="nav-section-group nav-section-group-admin" open>
                            <summary class="nav-section nav-section-admin">
                                <span>Administração</span>
                                <span class="nav-caret">▾</span>
                            </summary>
                            <a class="nav-link nav-link-admin {{ request()->routeIs('admin.*') ? 'active' : '' }}"
                                href="{{ route('admin.planejamento.index') }}">⚙ Administração do catálogo</a>
                            <a class="nav-link nav-link-admin {{ request()->routeIs('imports.*') ? 'active' : '' }}"
                                href="{{ route('imports.ubiqua.index') }}">⇧ Importações</a>
                            <a class="nav-link nav-link-admin {{ request()->routeIs('audit.*') ? 'active' : '' }}"
                                href="{{ route('audit.index') }}">✓ Auditoria</a>
                        </details>
                    @endif

                    @if (in_array(Auth::user()->role, ['coordinator', 'staff', 'admin'], true))
                        <details class="nav-section-group" open>
                            <summary class="nav-section">
                                <span>Catálogo</span>
                                <span class="nav-caret">▾</span>
                            </summary>
                            <a class="nav-link {{ request()->routeIs('catalog.courses') ? 'active' : '' }}"
                                href="{{ route('catalog.courses') }}">◫ Cursos e matrizes</a>
                            <a class="nav-link {{ request()->routeIs('catalog.professors') ? 'active' : '' }}"
                                href="{{ route('catalog.professors') }}">♙ Professores</a>
                            <a class="nav-link {{ request()->routeIs('catalog.subjects') ? 'active' : '' }}"
                                href="{{ route('catalog.subjects') }}">◌ Disciplinas</a>
                            <a class="nav-link {{ request()->routeIs('catalog.students') ? 'active' : '' }}"
                                href="{{ route('catalog.students') }}">◍ Alunos</a>
                        </details>
                    @endif

                    @if (Auth::user()->role === 'teacher')
                        <details class="nav-section-group" open>
                            <summary class="nav-section">
                                <span>Professor</span>
                                <span class="nav-caret">▾</span>
                            </summary>
                            <a class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}"
                                href="{{ route('profile.edit') }}#disciplinas">◌ Minhas disciplinas</a>
                            <a class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}"
                                href="{{ route('profile.edit') }}#disponibilidade">◔ Minha disponibilidade</a>
                        </details>
                    @endif

                </nav>
                <div class="sidebar-footer">
                    <a href="{{ route('profile') }}"
                        class="d-flex align-items-center gap-2 text-decoration-none text-white">
                        @if (Auth::user()->photo_path)
                            <img src="{{ Storage::disk('public')->url(Auth::user()->photo_path) }}" alt="Foto do usuário"
                                class="avatar" style="object-fit:cover;">
                        @else
                            <span
                                class="avatar">{{ strtoupper(Str::of(Auth::user()->name)->split('/\s+/')->map(fn($part) => Str::substr($part, 0, 1))->take(2)->implode('')) }}</span>
                        @endif
                        <span class="d-flex flex-column">
                            <strong class="text-white">{{ Auth::user()->name }}</strong>
                            <small class="text-white-50">{{ Auth::user()->role_label }}</small>
                        </span>
                    </a>
                </div>
            </aside>
            <main class="content">
                <header class="topbar">
                    <button class="btn d-lg-none" type="button"
                        onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
                    <div><small class="text-uppercase text-secondary">Inova7 Acadêmico</small>
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
</body>

</html>
