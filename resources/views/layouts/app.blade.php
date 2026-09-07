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
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                        href="{{ route('dashboard') }}">▦ Painel</a>
                    <a class="nav-link {{ request()->routeIs('planning.*') ? 'active' : '' }}"
                        href="{{ route('planning.index') }}">▤ Planejamento</a>
                    <a class="nav-link" href="#">◫ Cursos e matrizes</a>
                    <a class="nav-link" href="#">♙ Professores</a>
                    <a class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}"
                        href="{{ route('audit.index') }}">✓ Auditoria</a>
                    <a class="nav-link" href="#">⇧ Importações</a>
                </nav>
                <div class="sidebar-footer"><span class="avatar">LT</span><span><strong>Larissa
                            Torres</strong><small>Coordenação</small></span></div>
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
