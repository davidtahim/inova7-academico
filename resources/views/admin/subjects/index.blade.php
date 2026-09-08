@extends('layouts.app')

@section('page-title', 'Disciplinas')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Disciplinas</h2>
                <p class="text-muted mb-0">Cadastro e manutenção das disciplinas.</p>
            </div>
            <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary">Nova disciplina</a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nome</th>
                                <th>Total</th>
                                <th>Presenciais</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($subjects as $subject)
                                <tr>
                                    <td><strong>{{ $subject->code ?? '—' }}</strong></td>
                                    <td>{{ $subject->name }}</td>
                                    <td>{{ $subject->total_hours ?? 0 }}h</td>
                                    <td>{{ $subject->presential_hours ?? 0 }}h</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.subjects.edit', $subject) }}"
                                            class="btn btn-sm btn-outline-primary">Editar</a>
                                        <form action="{{ route('admin.subjects.destroy', $subject) }}" method="POST"
                                            class="d-inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Deseja remover esta disciplina?')">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Nenhuma disciplina cadastrada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
