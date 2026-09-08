@extends('layouts.app')

@section('page-title', 'Matrizes permitidas para importação')

@section('content')
    <div class="container py-4">
        <div class="page-head">
            <div>
                <h2>Matrizes permitidas para importação</h2>
                <p>Controle os cursos e matrizes autorizados para a importação da oferta Ubíqua.</p>
            </div>
            <a href="{{ route('admin.matrices.index') }}" class="btn btn-primary">Gerenciar matrizes</a>
        </div>

        <div class="card border-0 shadow-sm card-soft admin-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle admin-matrix-table">
                        <thead>
                            <tr>
                                <th>Curso</th>
                                <th>Matrizes permitidas</th>
                                <th>Quantidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($courses as $course)
                                <tr>
                                    <td>
                                        <strong>{{ $course->name }}</strong>
                                        <div class="small text-muted">Código do curso: {{ $course->code }}</div>
                                    </td>
                                    <td>
                                        @if ($course->matrices->isEmpty())
                                            <span class="text-muted">Nenhuma matriz cadastrada.</span>
                                        @else
                                            <div class="d-flex flex-wrap gap-2">
                                                @foreach ($course->matrices as $matrix)
                                                    <span
                                                        class="status-badge {{ $matrix->allowed_for_import ? 'ativa' : 'inativa' }}"
                                                        title="{{ $matrix->allowed_for_import ? 'Ativa' : 'Inativa' }} - Curso {{ $course->code }}">
                                                        {{ $matrix->code }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span
                                                class="badge bg-light text-dark">{{ $course->matrices->where('allowed_for_import', true)->count() }}</span>
                                            <a href="{{ route('admin.import-scopes.edit', $course) }}"
                                                class="btn btn-sm btn-outline-primary">Editar</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Nenhum curso cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
