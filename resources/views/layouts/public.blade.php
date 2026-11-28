<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Inova7 Acadêmico')</title>
    <meta name="description" content="Inova7 Acadêmico: catálogo, disponibilidade docente, grade horária, enturmação e laboratórios da coordenação, num só lugar.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <style>
        .pub { --pub-navy: #0c3d67; --pub-navy2: #072a47; --pub-gold: #e4b94f; --pub-ink: #17263a; --pub-muted: #5b6b7d; background: #fff; color: var(--pub-ink); }
        .pub a { text-underline-offset: 3px; }
        .pub-skip { position: absolute; left: -999px; top: .5rem; z-index: 100; background: #fff; padding: .5rem 1rem; border-radius: .5rem; }
        .pub-skip:focus { left: .5rem; }
        .pub-mark { display: inline-grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .65rem; background: var(--pub-navy); color: var(--pub-gold);
            font-weight: 800; font-size: 1.2rem; border: 2px solid var(--pub-gold); flex: none; }
        .pub-brand { display: inline-flex; align-items: center; gap: .6rem; color: inherit; text-decoration: none; font-weight: 800; letter-spacing: .02em; }
        .pub-brand small { display: block; font-weight: 500; font-size: .72rem; letter-spacing: 0; opacity: .75; }
        .pub :focus-visible { outline: 3px solid var(--pub-gold); outline-offset: 2px; }
    </style>
    @stack('styles')
</head>

<body class="pub">
    <a href="#conteudo" class="pub-skip">Pular para o conteúdo</a>
    @yield('body')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>

</html>
