@extends('layouts.app')

@section('page-title', 'Editar matrizes permitidas')

@section('content')
    <div class="container py-4">
        <div class="page-head">
            <div>
                <h2>Editar matrizes permitidas</h2>
                <p>Selecione quais matrizes do curso podem ser aceitas na importação da oferta Ubíqua.</p>
            </div>
            <a href="{{ route('admin.import-scopes.index') }}" class="btn btn-outline-secondary">Voltar</a>
        </div>

        <div class="card border-0 shadow-sm card-soft">
            <div class="card-body">
                <h5 class="mb-3">{{ $course->name }} <span class="text-muted">Código do curso: {{ $course->code }}</span>
                </h5>

                <form action="{{ route('admin.import-scopes.update', $course) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <div class="border rounded p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                <div>
                                    <div class="fw-semibold">{{ $course->name }}</div>
                                    <small class="text-muted">Curso</small>
                                </div>
                                <span class="badge bg-dark-subtle text-dark">Código do curso: {{ $course->code }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded overflow-hidden">
                        @forelse ($matrices as $matrix)
                            <label class="d-flex justify-content-between align-items-center gap-3 p-3 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <input type="checkbox" name="matrix_ids[]" value="{{ $matrix->id }}"
                                        {{ $matrix->allowed_for_import ? 'checked' : '' }}>
                                    <div>
                                        <div class="fw-semibold">Código: {{ $matrix->code }}</div>
                                        <small class="text-muted">{{ $matrix->name }}</small>
                                    </div>
                                </div>

                                <span
                                    class="badge {{ $matrix->allowed_for_import ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">
                                    {{ $matrix->allowed_for_import ? 'Ativa' : 'Inativa' }}
                                </span>
                            </label>
                        @empty
                            <div class="p-3 bg-warning-subtle text-warning-emphasis">
                                Este curso ainda não possui matrizes cadastradas.
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Salvar matrizes permitidas</button>
                        <a href="{{ route('admin.import-scopes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
