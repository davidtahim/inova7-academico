@extends('layouts.app')

@section('page-title', 'Semestres')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Semestres</h2>
                <p class="text-muted mb-0">Cadastro e manutenção dos períodos letivos.</p>
            </div>
            <a href="{{ route('admin.semestres.create') }}" class="btn btn-primary">Novo semestre</a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
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
                                    <td><strong>{{ $term->code }}</strong></td>
                                    <td>{{ optional($term->starts_at)->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ optional($term->ends_at)->format('d/m/Y') ?? '—' }}</td>
                                    <td><span
                                            class="badge text-bg-light border">{{ ucfirst($term->status ?? 'planning') }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.semestres.edit', $term) }}"
                                            class="btn btn-sm btn-outline-primary">Editar</a>
                                        <form action="{{ route('admin.semestres.destroy', $term) }}" method="POST"
                                            class="d-inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Deseja remover este semestre?')">Excluir</button>
                                        </form>
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
