@extends('layouts.app')

@section('title', 'Manutenção • Inova7 Acadêmico')
@section('page-title', 'Manutenção do sistema')

@section('content')
    <div class="container-fluid py-3 px-0">
        <div class="mb-3">
            <h2 class="h4 mb-1">Manutenção do sistema</h2>
            <p class="text-muted small mb-0">
                Depois de cada atualização (Atualizar do remoto + Implantar), confira aqui se o banco está em dia.
                Equivale a rodar os comandos do artisan no servidor, sem terminal.
            </p>
        </div>

        @if (session('maintenance_output'))
            <details class="mb-3" open>
                <summary class="small fw-semibold">Saída do comando</summary>
                <pre class="small bg-dark text-light p-2 rounded mt-1 mb-0" style="white-space: pre-wrap; max-height: 260px; overflow: auto;">{{ session('maintenance_output') }}</pre>
            </details>
        @endif

        <div class="row g-3">
            {{-- Banco de dados: o mais importante depois de um deploy. --}}
            <div class="col-lg-7">
                <section class="card border-0 shadow-sm h-100" aria-labelledby="db-title">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h3 class="h6 mb-0" id="db-title">Banco de dados (migrations)</h3>
                            @if ($migrations['error'])
                                <span class="badge bg-danger">erro</span>
                            @elseif ($migrations['pending'])
                                <span class="badge bg-warning text-dark">{{ count($migrations['pending']) }} pendente(s)</span>
                            @else
                                <span class="badge bg-success">atualizado</span>
                            @endif
                            <span class="small text-muted ms-auto">{{ $migrations['ran'] }} já aplicadas</span>
                        </div>

                        @if ($migrations['error'])
                            <div class="alert alert-danger small mb-0">{{ $migrations['error'] }}</div>
                        @elseif ($migrations['pending'])
                            <p class="small mb-2">Estas alterações de banco chegaram com a atualização e ainda não foram aplicadas. Telas novas que dependem delas dão erro até você aplicar.</p>
                            <ul class="small font-monospace mb-3">
                                @foreach ($migrations['pending'] as $name)
                                    <li>{{ $name }}</li>
                                @endforeach
                            </ul>
                            <form method="POST" action="{{ route('system.maintenance.migrate') }}"
                                onsubmit="return confirm('Aplicar {{ count($migrations['pending']) }} migration(s) no banco de produção? Recomenda-se ter um backup recente do banco (cPanel > Backup).')">
                                @csrf
                                <button type="submit" class="btn btn-primary">Aplicar migrations pendentes</button>
                            </form>
                        @else
                            <p class="small text-muted mb-0">Nenhuma alteração pendente: o banco está na mesma versão do código.</p>
                        @endif
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="card border-0 shadow-sm h-100" aria-labelledby="actions-title">
                    <div class="card-body d-grid gap-3">
                        <h3 class="h6 mb-0" id="actions-title">Outras ações</h3>
                        <form method="POST" action="{{ route('system.maintenance.clear') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary w-100">Limpar caches</button>
                            <div class="form-text">Configuração, rotas, telas e cache. Use se uma tela nova não aparecer ou se o .env foi alterado.</div>
                        </form>
                        <form method="POST" action="{{ route('system.maintenance.storage-link') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary w-100">Criar link de arquivos públicos</button>
                            <div class="form-text">Necessário uma vez para as fotos de perfil aparecerem.</div>
                        </form>
                    </div>
                </section>
            </div>

            <div class="col-12">
                <section class="card border-0 shadow-sm" aria-labelledby="status-title">
                    <div class="card-body">
                        <h3 class="h6" id="status-title">Situação do servidor</h3>
                        <ul class="list-unstyled mb-0 small">
                            @foreach ($checks as $check)
                                <li class="d-flex gap-2 py-1 border-bottom">
                                    <span class="{{ $check['ok'] ? 'text-success' : 'text-danger' }}" aria-hidden="true">{{ $check['ok'] ? '✓' : '✗' }}</span>
                                    <span class="visually-hidden">{{ $check['ok'] ? 'OK:' : 'Problema:' }}</span>
                                    <strong style="min-width: 190px">{{ $check['label'] }}</strong>
                                    <span class="text-muted text-break">{{ $check['detail'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            </div>

            <div class="col-12">
                <details class="card border-0 shadow-sm">
                    <summary class="card-body small fw-semibold">Últimas linhas do log de erros (storage/logs/laravel.log)</summary>
                    <div class="card-body pt-0">
                        @if ($log)
                            <pre class="small bg-dark text-light p-2 rounded mb-0" style="white-space: pre-wrap; max-height: 420px; overflow: auto;">{{ $log }}</pre>
                        @else
                            <p class="small text-muted mb-0">Nenhum log registrado.</p>
                        @endif
                    </div>
                </details>
            </div>
        </div>
    </div>
@endsection
