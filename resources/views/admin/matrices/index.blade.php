@extends('layouts.app')

@section('page-title', 'Matrizes')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Matrizes curriculares</h2>
                <p class="text-muted mb-0">Cadastro e manutenção das matrizes por curso.</p>
            </div>
            <a href="{{ route('admin.matrices.create') }}" class="btn btn-primary">Nova matriz</a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
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
                                    <td>{{ $matrix->course?->name ?? '—' }}</td>
                                    <td><strong>{{ $matrix->code }}</strong></td>
                                    <td>{{ $matrix->name }}</td>
                                    <td><span class="badge text-bg-light border">{{ $matrix->status ?? 'Ativa' }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.matrices.edit', $matrix) }}"
                                            class="btn btn-sm btn-outline-primary">Editar</a>
                                        <form action="{{ route('admin.matrices.destroy', $matrix) }}" method="POST"
                                            class="d-inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Deseja remover esta matriz?')">Excluir</button>
                                        </form>
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
