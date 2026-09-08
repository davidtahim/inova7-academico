@extends('layouts.app')

@section('page-title', 'Disciplinas')

@section('content')
    <div class="container py-4">
        <div class="page-head">
            <div>
                <h2>Disciplinas</h2>
                <p>Cadastro e manutenção das disciplinas.</p>
            </div>
            <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary">Nova disciplina</a>
        </div>

        <div class="card border-0 shadow-sm card-soft admin-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle admin-matrix-table">
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
                                    <td><strong class="matrix-code-pill">{{ $subject->code ?? '—' }}</strong></td>
                                    <td><span class="matrix-name-cell">{{ $subject->name }}</span></td>
                                    <td>{{ $subject->total_hours ?? 0 }}h</td>
                                    <td>{{ $subject->presential_hours ?? 0 }}h</td>
                                    <td class="text-end">
                                        <div class="table-actions">
                                            <a href="{{ route('admin.subjects.edit', $subject) }}"
                                                class="btn btn-sm btn-outline-primary">Editar</a>
                                            <form action="{{ route('admin.subjects.destroy', $subject) }}" method="POST"
                                                class="d-inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Deseja remover esta disciplina?')">Excluir</button>
                                            </form>
                                        </div>
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
