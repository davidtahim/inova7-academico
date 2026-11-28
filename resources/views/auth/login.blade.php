@extends('layouts.public')

@section('title', 'Entrar • Inova7 Acadêmico')

@section('body')
    <main id="conteudo" class="login-shell">
        <aside class="login-side" aria-label="Sobre o sistema">
            <a class="pub-brand text-white" href="{{ route('dashboard') }}">
                <span class="pub-mark" aria-hidden="true">7</span>
                <span>INOVA7<small>Gestão Acadêmica</small></span>
            </a>
            <div class="login-side-body">
                <h2>O semestre inteiro planejado num só lugar.</h2>
                <ul>
                    <li>Grade horária sem choques de professor, sala e turma</li>
                    <li>Disponibilidade e disciplinas de cada professor</li>
                    <li>Laboratórios, enturmação e horários publicados</li>
                </ul>
            </div>
            <a href="{{ route('dashboard') }}" class="login-side-link">← Conheça o sistema</a>
        </aside>

        <section class="login-main">
            <div class="login-box">
                <a class="pub-brand d-lg-none mb-4" href="{{ route('dashboard') }}">
                    <span class="pub-mark" aria-hidden="true">7</span>
                    <span>INOVA7<small>Gestão Acadêmica</small></span>
                </a>
                <h1 class="h3 fw-bold mb-1">Entrar</h1>
                <p class="text-secondary mb-4">Use o seu e-mail institucional.</p>

                @if ($errors->any())
                    <div class="alert alert-danger py-2 small" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}" novalidate data-login>
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">E-mail</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" inputmode="email"
                            class="form-control form-control-lg @error('email') is-invalid @enderror" required autofocus
                            placeholder="nome@uni7.edu.br" @error('email') aria-describedby="email-error" aria-invalid="true" @enderror>
                        @error('email')<span id="email-error" class="visually-hidden">{{ $message }}</span>@enderror
                    </div>

                    <div class="mb-2">
                        <label for="password" class="form-label fw-semibold">Senha</label>
                        <div class="input-group input-group-lg">
                            <input id="password" type="password" name="password" autocomplete="current-password"
                                class="form-control @error('password') is-invalid @enderror" required aria-describedby="caps-hint">
                            <button type="button" class="btn btn-outline-secondary" data-toggle-password aria-controls="password" aria-pressed="false">Mostrar</button>
                        </div>
                        <div id="caps-hint" class="form-text text-warning-emphasis" hidden>Caps Lock está ativado.</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold mt-3" data-submit>Entrar</button>
                </form>

                <p class="small text-secondary mt-4 mb-0">
                    Não tem acesso ou esqueceu a senha? Fale com o Administrador de TI.
                </p>
                <p class="small mt-2 mb-0 d-flex flex-wrap gap-3">
                    <a href="{{ route('dashboard') }}" class="d-lg-none">← Conheça o sistema</a>
                    <a href="{{ route('labs.public') }}">Ver o calendário dos laboratórios</a>
                </p>
            </div>
        </section>
    </main>
@endsection

@push('styles')
    <style>
        .login-shell { min-height: 100vh; display: grid; grid-template-columns: minmax(320px, 5fr) 7fr; }
        .login-side { background: radial-gradient(700px 400px at 100% 0, rgba(228, 185, 79, .18), transparent 60%), linear-gradient(165deg, var(--pub-navy), var(--pub-navy2));
            color: #fff; padding: 2.5rem; display: flex; flex-direction: column; }
        .login-side-body { margin: auto 0; }
        .login-side h2 { font-size: 1.9rem; font-weight: 800; line-height: 1.15; max-width: 22rem; }
        .login-side ul { list-style: none; padding: 0; margin: 1.5rem 0 0; display: grid; gap: .7rem; color: rgba(255, 255, 255, .85); }
        .login-side li { padding-left: 1.6rem; position: relative; }
        .login-side li::before { content: "✓"; position: absolute; left: 0; color: var(--pub-gold); font-weight: 800; }
        .login-side-link { color: rgba(255, 255, 255, .8); text-decoration: none; font-size: .9rem; }
        .login-side-link:hover { color: #fff; text-decoration: underline; }
        .login-main { display: grid; place-items: center; padding: 2rem 1rem; background: #f3f6f9; }
        .login-box { width: 100%; max-width: 400px; background: #fff; border-radius: 1rem; padding: 2rem; box-shadow: 0 20px 50px -25px rgba(12, 61, 103, .35); }
        .login-box .pub-brand { color: var(--pub-navy); }
        @media (max-width: 991.98px) {
            .login-shell { grid-template-columns: 1fr; }
            .login-side { display: none; }
            .login-main { align-items: start; padding-top: 3rem; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            const pwd = document.getElementById('password');
            const toggle = document.querySelector('[data-toggle-password]');
            toggle.addEventListener('click', () => {
                const show = pwd.type === 'password';
                pwd.type = show ? 'text' : 'password';
                toggle.textContent = show ? 'Ocultar' : 'Mostrar';
                toggle.setAttribute('aria-pressed', String(show));
                pwd.focus();
            });
            const caps = document.getElementById('caps-hint');
            ['keyup', 'keydown'].forEach(t => pwd.addEventListener(t, e => { caps.hidden = !(e.getModifierState && e.getModifierState('CapsLock')); }));
            document.querySelector('[data-login]').addEventListener('submit', e => {
                const btn = e.target.querySelector('[data-submit]');
                setTimeout(() => { btn.disabled = true; btn.textContent = 'Entrando…'; });
            });
        })();
    </script>
@endpush
