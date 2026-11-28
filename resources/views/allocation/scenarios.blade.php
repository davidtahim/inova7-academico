@extends('layouts.app')

@section('title', 'Versões da grade • Inova7 Acadêmico')
@section('page-title', 'Versões da grade')

@section('content')
    @php
        $kindBadge = ['manual' => 'bg-primary-subtle text-primary-emphasis', 'auto' => 'bg-secondary-subtle text-secondary-emphasis', 'import' => 'bg-info-subtle text-info-emphasis'];
        $points = fn ($n) => number_format((int) $n, 0, ',', '.');
    @endphp
    <div class="container-fluid py-3 px-0">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-3">
            <div>
                <h2 class="h4 mb-1">Versões da grade — {{ $term->code }}</h2>
                <p class="text-muted small mb-0">
                    Guarde a grade antes de mudanças grandes, compare pela pontuação e volte atrás quando quiser.
                    O sistema também guarda uma versão automática antes de aplicar o resolvedor ou restaurar outra versão.
                </p>
            </div>
            <a href="{{ route('allocation.board') }}" class="btn btn-sm btn-primary">Voltar à grade global</a>
        </div>

        @if (session('restore_problems'))
            <details class="alert alert-warning small" open>
                <summary class="fw-semibold">{{ count(session('restore_problems')) }} {{ count(session('restore_problems')) === 1 ? 'item não pôde ser restaurado' : 'itens não puderam ser restaurados' }}</summary>
                <ul class="mb-0 mt-2">
                    @foreach (session('restore_problems') as $problem)
                        <li>{{ $problem }}</li>
                    @endforeach
                </ul>
            </details>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-lg-7">
                <section class="card border-0 shadow-sm h-100" aria-labelledby="current-title">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div>
                                <h3 class="h6 mb-0" id="current-title">Grade atual</h3>
                                <div class="small text-muted">é a que está na grade global agora</div>
                            </div>
                            <div class="ms-auto text-end">
                                <div class="fs-4 fw-bold lh-1">{{ $points($current['total']) }}</div>
                                <span class="badge {{ $current['hard'] ? 'bg-danger' : 'bg-success' }}">{{ $current['hard'] }} rígidas</span>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('allocation.scenarios.store') }}" class="d-flex flex-wrap gap-2">
                            @csrf
                            <label class="visually-hidden" for="scenario-name">Nome da versão</label>
                            <input type="text" id="scenario-name" name="name" class="form-control" style="flex: 1 1 240px" maxlength="120" required
                                placeholder="Nome da versão (ex.: proposta para o colegiado)" value="{{ old('name') }}">
                            <button type="submit" class="btn btn-primary">Salvar esta versão</button>
                            <a href="{{ route('allocation.scenarios.export') }}" class="btn btn-outline-secondary">Baixar arquivo</a>
                        </form>
                        @error('name')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                    </div>
                </section>
            </div>
            <div class="col-lg-5">
                <section class="card border-0 shadow-sm h-100" aria-labelledby="import-title">
                    <div class="card-body">
                        <h3 class="h6 mb-1" id="import-title">Importar arquivo</h3>
                        <p class="small text-muted mb-2">Um arquivo .json baixado desta tela, inclusive de outro computador ou do sistema local. Ele vira uma versão; nada muda até você restaurá-la.</p>
                        <form method="POST" action="{{ route('allocation.scenarios.import') }}" enctype="multipart/form-data" class="d-grid gap-2">
                            @csrf
                            <label class="visually-hidden" for="scenario-file">Arquivo da versão</label>
                            <input type="file" id="scenario-file" name="file" accept=".json,application/json" class="form-control" required>
                            @error('file')<div class="small text-danger">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-outline-primary">Importar</button>
                        </form>
                    </div>
                </section>
            </div>
        </div>

        <section class="card border-0 shadow-sm" aria-labelledby="list-title">
            <header class="card-header bg-white">
                <h3 class="h6 mb-0" id="list-title">Versões guardadas ({{ $scenarios->count() }})</h3>
            </header>
            @if ($scenarios->isEmpty())
                <div class="card-body text-muted small">Nenhuma versão ainda. Salve a grade atual antes de rodar o resolvedor ou de fazer mudanças grandes.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="small text-uppercase text-muted">
                                <th scope="col">Versão</th>
                                <th scope="col">Quando</th>
                                <th scope="col" class="text-end">Aulas na grade</th>
                                <th scope="col" class="text-end">Pontos</th>
                                <th scope="col" class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($scenarios as $scenario)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $scenario->name }}</div>
                                        <span class="badge {{ $kindBadge[$scenario->kind] ?? 'bg-light text-dark' }}">{{ \App\Models\ScheduleScenario::KINDS[$scenario->kind] ?? $scenario->kind }}</span>
                                        @if (($scenario->payload['term'] ?? $term->code) !== $term->code)
                                            <span class="badge bg-warning-subtle text-warning-emphasis">do semestre {{ $scenario->payload['term'] }}</span>
                                        @endif
                                    </td>
                                    <td class="small">
                                        {{ $scenario->created_at->format('d/m/Y H:i') }}
                                        <div class="text-muted">{{ $scenario->author?->name ?? '—' }}</div>
                                    </td>
                                    <td class="text-end small">{{ $scenario->classes_count }} aulas<div class="text-muted">{{ $scenario->offerings_count }} disciplinas/turma</div></td>
                                    <td class="text-end">
                                        @if ($scenario->score !== null)
                                            <strong>{{ $points($scenario->score) }}</strong>
                                            <div><span class="badge {{ $scenario->hard ? 'bg-danger-subtle text-danger-emphasis' : 'bg-success-subtle text-success-emphasis' }}">{{ $scenario->hard }} rígidas</span></div>
                                        @else
                                            <span class="text-muted small" title="Calculados ao restaurar">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <form method="POST" action="{{ route('allocation.scenarios.restore', $scenario) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary"
                                                onclick="return confirm('Restaurar “{{ addslashes($scenario->name) }}”? A grade atual será guardada como versão automática antes.')">Restaurar</button>
                                        </form>
                                        <a href="{{ route('allocation.scenarios.download', $scenario) }}" class="btn btn-sm btn-outline-secondary">Baixar</a>
                                        <form method="POST" action="{{ route('allocation.scenarios.destroy', $scenario) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir a versão “{{ addslashes($scenario->name) }}”?')">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        <p class="small text-muted mt-2 mb-0">Versões automáticas: o sistema guarda as {{ \App\Services\ScheduleScenarios::KEEP_AUTO }} mais recentes. As salvas e importadas ficam até você excluir.</p>
    </div>
@endsection
