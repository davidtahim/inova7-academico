@extends('layouts.app')

@section('page-title', 'Semestres')

@section('content')
    <div class="container py-4">
        <div class="page-head">
            <div>
                <h2>Semestres</h2>
                <p>Cadastro e manutenção dos períodos letivos.</p>
            </div>
            <a href="{{ route('admin.semestres.create') }}" class="btn btn-primary">Novo semestre</a>
        </div>

        <div class="card border-0 shadow-sm mb-3 compact-search-card">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('admin.semestres.index') }}" class="d-flex align-items-center gap-2">
                    <input type="text" name="q" class="form-control form-control-sm"
                        value="{{ old('q', $query ?? '') }}" placeholder="Buscar por código ou status do semestre">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    @if (($query ?? '') !== '')
                        <a href="{{ route('admin.semestres.index') }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm card-soft admin-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle admin-matrix-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Início</th>
                                <th>Fim</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($terms as $term)
                                <tr>
                                    <td><strong class="matrix-code-pill">{{ $term->code }}</strong></td>
                                    <td>{{ optional($term->starts_at)->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ optional($term->ends_at)->format('d/m/Y') ?? '—' }}</td>
                                    <td>
                                        <span class="status-badge {{ strtolower($term->status ?? 'planning') }}">
                                            {{ ucfirst($term->status ?? 'planning') }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-actions">
                                            <a href="{{ route('admin.semestres.edit', $term) }}"
                                                class="btn btn-sm btn-outline-primary">Editar</a>
                                            <form action="{{ route('admin.semestres.destroy', $term) }}" method="POST"
                                                class="d-inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Deseja remover este semestre?')">Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Nenhum semestre cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
