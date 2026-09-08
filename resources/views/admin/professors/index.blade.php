@extends('layouts.app')

@section('page-title', 'Professores')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Professores</h2>
                <p class="text-muted mb-0">Cadastro e manutenção dos docentes.</p>
            </div>
            <a href="{{ route('admin.professors.create') }}" class="btn btn-primary">Novo professor</a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
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
                                    <td>{{ $professor->name }}</td>
                                    <td>{{ $professor->email ?? '—' }}</td>
                                    <td>{{ $professor->qualification ?? '—' }}</td>
                                    <td>
                                        <span
                                            class="badge {{ $professor->active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} border">
                                            {{ $professor->active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.professors.edit', $professor) }}"
                                            class="btn btn-sm btn-outline-primary">Editar</a>
                                        <form action="{{ route('admin.professors.destroy', $professor) }}" method="POST"
                                            class="d-inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Deseja remover este professor?')">Excluir</button>
                                        </form>
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
