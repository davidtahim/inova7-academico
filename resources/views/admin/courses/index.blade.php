@extends('layouts.app')

@section('page-title', 'Cursos')

@section('content')
    <div class="container py-4">
        <div class="page-head">
            <div>
                <h2>Cursos</h2>
                <p>Cadastro e manutenção dos cursos do sistema.</p>
            </div>
            <a href="{{ route('admin.courses.create') }}" class="btn btn-primary">Novo curso</a>
        </div>

        <div class="card border-0 shadow-sm card-soft admin-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle admin-matrix-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nome</th>
                                <th>Grau</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($courses as $course)
                                <tr>
                                    <td><strong class="matrix-code-pill">{{ $course->code }}</strong></td>
                                    <td><span class="matrix-name-cell">{{ $course->name }}</span></td>
                                    <td>{{ $course->degree ?? '—' }}</td>
                                    <td>
                                        <span class="status-badge {{ $course->active ? 'ativa' : 'inativa' }}">
                                            {{ $course->active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-actions">
                                            <a href="{{ route('admin.courses.edit', $course) }}"
                                                class="btn btn-sm btn-outline-primary">Editar</a>
                                            <form action="{{ route('admin.courses.destroy', $course) }}" method="POST"
                                                class="d-inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Deseja remover este curso?')">Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Nenhum curso cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
