@extends('layouts.app')

@section('page-title', 'Cursos')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Cursos</h2>
                <p class="text-muted mb-0">Cadastro e manutenção dos cursos do sistema.</p>
            </div>
            <a href="{{ route('admin.courses.create') }}" class="btn btn-primary">Novo curso</a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
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
                                    <td><strong>{{ $course->code }}</strong></td>
                                    <td>{{ $course->name }}</td>
                                    <td>{{ $course->degree ?? '—' }}</td>
                                    <td>
                                        <span
                                            class="badge {{ $course->active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} border">
                                            {{ $course->active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.courses.edit', $course) }}"
                                            class="btn btn-sm btn-outline-primary">Editar</a>
                                        <form action="{{ route('admin.courses.destroy', $course) }}" method="POST"
                                            class="d-inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Deseja remover este curso?')">Excluir</button>
                                        </form>
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
