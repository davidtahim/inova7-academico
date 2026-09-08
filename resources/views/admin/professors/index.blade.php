@extends('layouts.app')

@section('page-title', 'Professores')

@section('content')
    <div class="container py-4">
        <div class="page-head">
            <div>
                <h2>Professores</h2>
                <p>Cadastro e manutenção dos docentes.</p>
            </div>
            <a href="{{ route('admin.professors.create') }}" class="btn btn-primary">Novo professor</a>
        </div>

        <div class="card border-0 shadow-sm mb-3 compact-search-card">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('admin.professors.index') }}" class="d-flex align-items-center gap-2">
                    <input type="text" name="q" class="form-control form-control-sm"
                        value="{{ old('q', $query ?? '') }}" placeholder="Buscar por nome, e-mail, formação ou matrícula">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    @if (($query ?? '') !== '')
                        <a href="{{ route('admin.professors.index') }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
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
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Formação</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($professors as $professor)
                                <tr>
                                    <td><span class="matrix-name-cell">{{ $professor->name }}</span></td>
                                    <td>{{ $professor->email ?? '—' }}</td>
                                    <td>{{ $professor->qualification ?? '—' }}</td>
                                    <td>
                                        <span class="status-badge {{ $professor->active ? 'ativa' : 'inativa' }}">
                                            {{ $professor->active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-actions">
                                            <a href="{{ route('admin.professors.edit', $professor) }}"
                                                class="btn btn-sm btn-outline-primary">Editar</a>
                                            <form action="{{ route('admin.professors.destroy', $professor) }}"
                                                method="POST" class="d-inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Deseja remover este professor?')">Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Nenhum professor cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
