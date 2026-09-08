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
                    <div class="nav-section">Geral</div>
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                        href="{{ route('dashboard') }}">▦ Painel</a>

                    @if (in_array(Auth::user()->role, ['student', 'teacher', 'coordinator', 'staff', 'admin'], true))
                        <a class="nav-link {{ request()->routeIs('planning.*') ? 'active' : '' }}"
                            href="{{ route('planning.index') }}">▤ Planejamento</a>
                    @endif

                    @if (in_array(Auth::user()->role, ['coordinator', 'staff', 'admin'], true))
                        <div class="nav-section">Catálogo</div>
                        <a class="nav-link {{ request()->routeIs('catalog.courses') ? 'active' : '' }}"
                            href="{{ route('catalog.courses') }}">◫ Cursos e matrizes</a>
                        <a class="nav-link {{ request()->routeIs('catalog.professors') ? 'active' : '' }}"
                            href="{{ route('catalog.professors') }}">♙ Professores</a>
                        <a class="nav-link {{ request()->routeIs('catalog.subjects') ? 'active' : '' }}"
                            href="{{ route('catalog.subjects') }}">◌ Disciplinas</a>
                        <a class="nav-link {{ request()->routeIs('catalog.students') ? 'active' : '' }}"
                            href="{{ route('catalog.students') }}">◍ Alunos</a>
                        @if (Auth::user()->role === 'admin')
                            <a class="nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}"
                                href="{{ route('admin.courses.index') }}">⚙ Administração do catálogo</a>
                        @endif
                    @endif

                    <a class="nav-link {{ request()->routeIs('profile') || request()->routeIs('profile.edit') ? 'active' : '' }}"
                        href="{{ route('profile') }}">◉ Meu perfil</a>

                    @if (Auth::user()->role === 'teacher')
                        <div class="nav-section">Professor</div>
                        <a class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}"
                            href="{{ route('profile.edit') }}#disciplinas">◌ Minhas disciplinas</a>
                        <a class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}"
                            href="{{ route('profile.edit') }}#disponibilidade">◔ Minha disponibilidade</a>
                    @endif

                    @if (in_array(Auth::user()->role, ['coordinator', 'staff', 'admin'], true))
                        <div class="nav-section">Operação</div>
                        <a class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}"
                            href="{{ route('audit.index') }}">✓ Auditoria</a>
                    @endif

                    @if (in_array(Auth::user()->role, ['admin', 'staff'], true))
                        <a class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}"
                            href="{{ route('imports.ubiqua.index') }}">⇧ Importações</a>
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
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-bg-light border">Laravel 12 • MySQL</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Sair</button>
                        </form>
                    </div>
                </header>
                @if (session('success'))
                    <div class="alert alert-success mt-3">{{ session('success') }}</div>
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
