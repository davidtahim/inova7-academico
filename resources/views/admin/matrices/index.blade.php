@extends('layouts.app')

@section('page-title', 'Matrizes')

@section('content')
    <div class="container py-4">
        <div class="page-head">
            <div>
                <h2>Matrizes curriculares</h2>
                <p>Cadastro e manutenção das matrizes por curso.</p>
            </div>
            <a href="{{ route('admin.matrices.create') }}" class="btn btn-primary">Nova matriz</a>
        </div>

        <div class="card border-0 shadow-sm mb-3 compact-search-card">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('admin.matrices.index') }}" class="d-flex align-items-center gap-2">
                    <input type="text" name="q" class="form-control form-control-sm"
                        value="{{ old('q', $query ?? '') }}"
                        placeholder="Buscar por curso, código, nome ou versão da matriz">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    @if (($query ?? '') !== '')
                        <a href="{{ route('admin.matrices.index') }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
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
                                <th>Curso</th>
                                <th>Código</th>
                                <th>Nome</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($matrices as $matrix)
                                <tr>
                                    <td>
                                        <span class="matrix-course-name">{{ $matrix->course?->name ?? '—' }}</span>
                                    </td>
                                    <td><strong class="matrix-code-pill">{{ $matrix->code }}</strong></td>
                                    <td>
                                        <span
                                            class="matrix-name-cell">{{ $matrix->name ?: 'Matriz ' . ($matrix->version ?? '—') . ' - ' . ($matrix->course?->name ?? 'Curso') }}</span>
                                    </td>
                                    <td>
                                        <span class="status-badge {{ strtolower($matrix->status ?? 'ativa') }}">
                                            {{ $matrix->status ?? 'Ativa' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-actions">
                                            <a href="{{ route('admin.matrices.edit', $matrix) }}"
                                                class="btn btn-sm btn-outline-primary">Editar</a>
                                            <form action="{{ route('admin.matrices.destroy', $matrix) }}" method="POST"
                                                class="d-inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Deseja remover esta matriz?')">Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Nenhuma matriz cadastrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
