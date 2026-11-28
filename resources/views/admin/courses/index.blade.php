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

        <div class="card border-0 shadow-sm mb-3 compact-search-card">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('admin.courses.index') }}" class="d-flex align-items-center gap-2">
                    <input type="text" name="q" class="form-control form-control-sm"
                        value="{{ old('q', $query ?? '') }}" placeholder="Buscar por nome, código ou grau do curso">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    @if (($query ?? '') !== '')
                        <a href="{{ route('admin.courses.index') }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
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
                                <th>Nome</th>
                                <th>Grau</th>
                                <th>Matrizes</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($courses as $course)
                                <tr>
                                    <td><strong class="matrix-code-pill">{{ $course->code }}</strong></td>
                                    <td>
                                        <span class="matrix-name-cell">{{ $course->name }}</span>
                                        @if ($course->coordinator || $course->assistants->isNotEmpty())
                                            <small class="d-block text-muted">
                                                @if ($course->coordinator)Coordenação: {{ $course->coordinator->name }}@endif
                                                @if ($course->assistants->isNotEmpty()){{ $course->coordinator ? ' · ' : '' }}Assistente{{ $course->assistants->count() > 1 ? 's' : '' }}: {{ $course->assistants->pluck('name')->implode(', ') }}@endif
                                            </small>
                                        @endif
                                    </td>
                                    <td>{{ $course->degree ?? '—' }}</td>
                                    <td>
                                        @forelse ($course->matrices as $matrix)
                                            <a href="{{ route('admin.subjects.index', ['matriz' => $matrix->id]) }}"
                                                class="badge rounded-pill bg-light text-dark border text-decoration-none me-1 mb-1"
                                                title="{{ $matrix->code }} · {{ $matrix->disciplines_for_offering_count }} disciplinas · {{ $matrix->status }}">
                                                {{ $matrix->name }}
                                            </a>
                                        @empty
                                            <a href="{{ route('admin.courses.edit', $course) }}#matrizes" class="small text-warning-emphasis">Sem matriz: vincular</a>
                                        @endforelse
                                    </td>
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
                                                    onclick="return confirm('Excluir o curso {{ addslashes($course->name) }}? Só é possível se ele não tiver matrizes nem turmas.')">Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Nenhum curso cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
