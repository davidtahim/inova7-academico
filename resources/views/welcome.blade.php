@extends('layouts.public')

@section('title', 'Inova7 Acadêmico • Gestão acadêmica da coordenação')

@php
    $steps = [
        ['Catálogo', 'Cursos, matrizes e disciplinas, com aulas por semana, turno e sala exigida em cada período.'],
        ['Oferta', 'As turmas do semestre chegam da planilha da Oferta Ubíqua, sem redigitar nada.'],
        ['Professores', 'Cada professor marca as disciplinas que pode lecionar e os horários em que pode dar aula.'],
        ['Grade', 'Montagem automática ou por arrastar e soltar, com diagnóstico do que impede a grade de fechar.'],
        ['Publicação', 'Quando a coordenação publica, alunos e professores passam a ver os horários.'],
        ['Enturmação', 'Os históricos dos alunos de matrizes antigas viram matrículas nas turmas do semestre.'],
    ];
    $profiles = [
        ['Coordenação', 'Planeja o semestre de ponta a ponta: grade, professores por turma, versões, publicação e enturmação.', 'M4 5h16v14H4zM4 9h16M9 9v10'],
        ['Professores', 'Informam disciplinas e disponibilidade (Posso, Prefiro, Não posso) e consultam o próprio horário.', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0'],
        ['Alunos', 'Veem a grade da turma e o próprio horário depois da publicação.', 'M3 8l9-4 9 4-9 4-9-4zM7 10v5c0 1.5 2.5 3 5 3s5-1.5 5-3v-5'],
        ['Laboratórios', 'Gestores de cada área aprovam reservas; as aulas da grade já entram no calendário do laboratório.', 'M9 3h6M10 3v6l-5 9a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9V3'],
        ['Secretaria e CRA', 'Consulta o catálogo, mantém os dados dos professores, importa planilhas e acessa a auditoria.', 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h7M9 17h7'],
        ['TI', 'Administra usuários e perfis, aplica atualizações e acompanha a saúde do sistema.', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19 12h2M3 12h2M12 3v2M12 19v2'],
    ];
    $rules = [
        ['Sem choques', 'Professor, sala e turma nunca ficam em dois lugares ao mesmo tempo.'],
        ['Disponibilidade respeitada', 'Nenhuma aula é marcada fora do horário que o professor informou.'],
        ['Laboratório certo', 'Só disciplinas práticas ocupam laboratório, com vagas e softwares para a turma (Regulamento PED-RGU-18307).'],
        ['Histórico fechado', 'Semestre concluído fica somente para consulta.'],
        ['Dados protegidos', 'Dos históricos, só o necessário para a enturmação: CPF e RG não são lidos.'],
    ];
    $grid = [
        ['A', ['ALG', '', 'BD', '', 'ES']],
        ['B', ['ALG', '', 'BD', '', 'ES']],
        ['C', ['', 'CAL', '', 'RED', '']],
        ['D', ['', 'CAL', '', 'RED', '']],
    ];
@endphp

@section('body')
    <header class="lp-nav">
        <div class="container d-flex align-items-center gap-3 py-3">
            <a class="pub-brand text-white" href="{{ route('dashboard') }}">
                <span class="pub-mark" aria-hidden="true">7</span>
                <span>INOVA7<small>Gestão Acadêmica</small></span>
            </a>
            <nav class="ms-auto d-none d-md-flex gap-4 small" aria-label="Seções">
                <a href="#como-funciona">Como funciona</a>
                <a href="#perfis">Perfis</a>
                <a href="#regras">Regras</a>
                <a href="{{ route('labs.public') }}">Laboratórios</a>
            </nav>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-warning btn-sm fw-semibold ms-auto ms-md-0">Ir para o sistema</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-warning btn-sm fw-semibold ms-auto ms-md-0">Entrar</a>
            @endauth
        </div>
    </header>

    <main id="conteudo">
        <section class="lp-hero">
            <div class="container py-5">
                <div class="row align-items-center g-4 g-lg-5">
                    <div class="col-lg-6">
                        <p class="lp-eyebrow">Coordenação acadêmica</p>
                        <h1 class="lp-title">O semestre inteiro planejado num só lugar.</h1>
                        <p class="lp-lead">
                            Do catálogo de disciplinas à grade publicada: o Inova7 Acadêmico reúne a disponibilidade dos professores,
                            monta os horários sem choques, reserva os laboratórios e matricula os alunos nas turmas.
                        </p>
                        <div class="d-flex flex-wrap gap-2 mt-4">
                            @auth
                                <a href="{{ route('dashboard') }}" class="btn btn-warning btn-lg fw-semibold px-4">Ir para o sistema</a>
                            @else
                                <a href="{{ route('login') }}" class="btn btn-warning btn-lg fw-semibold px-4">Entrar no sistema</a>
                            @endauth
                            <a href="#como-funciona" class="btn btn-outline-light btn-lg px-4">Como funciona</a>
                        </div>
                        <p class="small mt-3 mb-0 lp-hero-note">
                            Acesso com o e-mail institucional. Sem usuário? Peça ao Administrador de TI.
                        </p>
                    </div>
                    <div class="col-lg-6">
                        <figure class="lp-mock mb-0" aria-label="Exemplo de grade semanal de uma turma">
                            <div class="lp-mock-head">
                                <span><strong>Turma 3º período</strong> · Noite</span>
                                <span class="lp-mock-ok">✓ sem choques</span>
                            </div>
                            <table class="lp-mock-grid" aria-hidden="true">
                                <thead><tr><th></th>@foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex'] as $d)<th>{{ $d }}</th>@endforeach</tr></thead>
                                <tbody>
                                    @foreach ($grid as [$letter, $cells])
                                        <tr>
                                            <th>{{ $letter }}</th>
                                            @foreach ($cells as $i => $cell)
                                                <td>@if ($cell)<span class="lp-chip lp-chip-{{ $i }}">{{ $cell }}</span>@endif</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <figcaption class="lp-mock-foot">
                                <span><span class="lp-dot"></span> Li41 reservado para Algoritmos</span>
                                <span>10 de 10 aulas</span>
                            </figcaption>
                        </figure>
                    </div>
                </div>
            </div>
        </section>

        <section id="como-funciona" class="py-5 lp-section">
            <div class="container">
                <div class="lp-section-head">
                    <p class="lp-eyebrow text-primary">Como funciona</p>
                    <h2>Seis passos, do catálogo à sala de aula</h2>
                    <p>Cada etapa usa o que a anterior deixou pronto. Nada é digitado duas vezes.</p>
                </div>
                <ol class="lp-steps">
                    @foreach ($steps as $i => [$title, $text])
                        <li>
                            <span class="lp-step-n" aria-hidden="true">{{ $i + 1 }}</span>
                            <h3>{{ $title }}</h3>
                            <p>{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section id="perfis" class="py-5 lp-section lp-alt">
            <div class="container">
                <div class="lp-section-head">
                    <p class="lp-eyebrow text-primary">Perfis</p>
                    <h2>Cada pessoa vê só o que precisa</h2>
                    <p>Ao entrar, o sistema leva cada perfil direto ao seu ponto de partida.</p>
                </div>
                <div class="row g-3">
                    @foreach ($profiles as [$title, $text, $icon])
                        <div class="col-sm-6 col-lg-4">
                            <article class="lp-card h-100">
                                <span class="lp-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="{{ $icon }}" /></svg>
                                </span>
                                <h3>{{ $title }}</h3>
                                <p>{{ $text }}</p>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="regras" class="py-5 lp-section">
            <div class="container">
                <div class="row g-4 g-lg-5 align-items-start">
                    <div class="col-lg-5">
                        <p class="lp-eyebrow text-primary">Regras</p>
                        <h2 class="lp-h2">O que o sistema garante</h2>
                        <p class="text-secondary">
                            As regras são conferidas a cada alteração, seja na montagem automática, no arrastar e soltar ou na edição de uma turma.
                            Quando a grade não fecha, o diagnóstico mostra o motivo.
                        </p>
                        <a href="{{ route('labs.public') }}" class="btn btn-outline-primary mt-2">Ver o calendário dos laboratórios</a>
                    </div>
                    <div class="col-lg-7">
                        <ul class="lp-rules">
                            @foreach ($rules as [$title, $text])
                                <li>
                                    <span class="lp-check" aria-hidden="true">✓</span>
                                    <div><strong>{{ $title }}</strong><span>{{ $text }}</span></div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="lp-cta">
            <div class="container py-5 text-center">
                <h2 class="h3 fw-bold mb-2">Pronto para começar?</h2>
                <p class="mb-4">Entre com o seu e-mail institucional e a senha recebida do Administrador de TI.</p>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-warning btn-lg fw-semibold px-5">Ir para o sistema</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-warning btn-lg fw-semibold px-5">Entrar no sistema</a>
                @endauth
            </div>
        </section>
    </main>

    <footer class="lp-footer">
        <div class="container d-flex flex-wrap gap-3 justify-content-between align-items-center py-4 small">
            <span class="pub-brand"><span class="pub-mark" aria-hidden="true">7</span><span>Inova7 Acadêmico</span></span>
            <nav class="d-flex flex-wrap gap-3" aria-label="Rodapé">
                <a href="{{ route('labs.public') }}">Calendário dos laboratórios</a>
                <a href="{{ route('login') }}">Entrar</a>
            </nav>
            <span class="text-secondary">desenvolvido por Inova7</span>
        </div>
    </footer>
@endsection

@push('styles')
    <style>
        .lp-nav { position: absolute; inset: 0 0 auto; z-index: 10; color: #fff; }
        .lp-nav nav a { color: rgba(255, 255, 255, .85); text-decoration: none; }
        .lp-nav nav a:hover { color: #fff; text-decoration: underline; }
        .lp-hero { background: radial-gradient(1200px 500px at 85% -10%, rgba(228, 185, 79, .18), transparent 60%), linear-gradient(160deg, var(--pub-navy), var(--pub-navy2)); color: #fff; padding-top: 4.5rem; }
        .lp-eyebrow { text-transform: uppercase; letter-spacing: .12em; font-size: .75rem; font-weight: 700; color: var(--pub-gold); margin-bottom: .75rem; }
        .lp-title { font-size: clamp(2rem, 4.5vw, 3.2rem); font-weight: 800; line-height: 1.1; letter-spacing: -.02em; }
        .lp-lead { font-size: 1.1rem; color: rgba(255, 255, 255, .85); max-width: 34rem; margin-top: 1rem; }
        .lp-hero-note { color: rgba(255, 255, 255, .65); }
        .lp-hero .btn-outline-light { border-color: rgba(255, 255, 255, .45); }

        .lp-mock { background: #fff; color: var(--pub-ink); border-radius: 1rem; box-shadow: 0 30px 60px -20px rgba(0, 0, 0, .45); padding: 1rem; transform: rotate(-1deg); }
        .lp-mock-head, .lp-mock-foot { display: flex; justify-content: space-between; gap: 1rem; font-size: .8rem; }
        .lp-mock-head { margin-bottom: .75rem; }
        .lp-mock-foot { margin-top: .75rem; color: var(--pub-muted); }
        .lp-mock-ok { color: #146c43; font-weight: 600; }
        .lp-mock-grid { width: 100%; border-collapse: separate; border-spacing: 4px; table-layout: fixed; font-size: .72rem; }
        .lp-mock-grid th { color: var(--pub-muted); font-weight: 600; text-align: center; }
        .lp-mock-grid tbody th { width: 1.5rem; }
        .lp-mock-grid td { background: #f3f6f9; border-radius: .4rem; height: 2.3rem; padding: 2px; }
        .lp-chip { display: grid; place-items: center; height: 100%; border-radius: .35rem; font-weight: 700; }
        .lp-chip-0 { background: #dbe8f6; color: #0c3d67; }
        .lp-chip-1 { background: #e8f6ee; color: #146c43; }
        .lp-chip-2 { background: #fff4e0; color: #7a4b00; }
        .lp-chip-3 { background: #f3e8ff; color: #5a2d91; }
        .lp-chip-4 { background: #ffe8ec; color: #9b1c31; }
        .lp-dot { display: inline-block; width: .55rem; height: .55rem; border-radius: 50%; background: #1264a3; margin-right: .25rem; }

        .lp-section h2, .lp-h2 { font-weight: 800; letter-spacing: -.01em; }
        .lp-section-head { max-width: 40rem; margin-bottom: 2rem; }
        .lp-section-head p:last-child { color: var(--pub-muted); }
        .lp-alt { background: #f3f6f9; }
        .lp-steps { list-style: none; padding: 0; margin: 0; display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); counter-reset: none; }
        .lp-steps li { position: relative; padding: 1.25rem 1.25rem 1.25rem 4rem; border: 1px solid #e3e9ef; border-radius: .9rem; background: #fff; }
        .lp-step-n { position: absolute; left: 1.1rem; top: 1.15rem; width: 2.1rem; height: 2.1rem; border-radius: 50%; display: grid; place-items: center;
            background: var(--pub-navy); color: var(--pub-gold); font-weight: 800; }
        .lp-steps h3, .lp-card h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: .35rem; }
        .lp-steps p, .lp-card p { color: var(--pub-muted); margin: 0; font-size: .92rem; }
        .lp-card { background: #fff; border-radius: .9rem; padding: 1.25rem; border: 1px solid #e3e9ef; transition: box-shadow .15s, transform .15s; }
        .lp-card:hover { box-shadow: 0 10px 30px -12px rgba(12, 61, 103, .35); transform: translateY(-2px); }
        .lp-icon { display: inline-grid; place-items: center; width: 2.6rem; height: 2.6rem; border-radius: .7rem; background: #e7f0f9; margin-bottom: .75rem; }
        .lp-icon svg { width: 1.35rem; height: 1.35rem; fill: none; stroke: var(--pub-navy); stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .lp-rules { list-style: none; padding: 0; margin: 0; display: grid; gap: .75rem; }
        .lp-rules li { display: flex; gap: .9rem; align-items: flex-start; padding: 1rem 1.1rem; border-radius: .8rem; background: #f7f9fb; border: 1px solid #e3e9ef; }
        .lp-rules strong { display: block; }
        .lp-rules span:not(.lp-check) { color: var(--pub-muted); font-size: .92rem; }
        .lp-check { flex: none; width: 1.6rem; height: 1.6rem; border-radius: 50%; display: grid; place-items: center; background: #e8f6ee; color: #146c43; font-weight: 800; }
        .lp-cta { background: linear-gradient(160deg, var(--pub-navy), var(--pub-navy2)); color: #fff; }
        .lp-cta p { color: rgba(255, 255, 255, .8); }
        .lp-footer { border-top: 1px solid #e3e9ef; }
        .lp-footer a { color: var(--pub-navy); }
        @media (max-width: 991.98px) { .lp-mock { transform: none; } }
        @media (prefers-reduced-motion: reduce) { .lp-card { transition: none; } .lp-card:hover { transform: none; } }
    </style>
@endpush
