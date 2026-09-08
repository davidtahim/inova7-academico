@extends('layouts.app')

@section('page-title', isset($matrix->id) ? 'Editar matriz' : 'Nova matriz')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm admin-form-card">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">{{ isset($matrix->id) ? 'Editar matriz' : 'Nova matriz' }}</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST"
                            action="{{ isset($matrix->id) ? route('admin.matrices.update', $matrix) : route('admin.matrices.store') }}">
                            @csrf
                            @if (isset($matrix->id))
                                @method('PUT')
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Curso</label>
                                <select id="matrix_course_id" name="course_id" class="form-select" required>
                                    <option value="">Selecione</option>
                                    @foreach ($courses as $course)
                                        <option value="{{ $course->id }}"
                                            {{ old('course_id', $matrix->course_id ?? '') == $course->id ? 'selected' : '' }}>
                                            {{ $course->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Código</label>
                                <input id="matrix_code" type="text" name="code" class="form-control"
                                    value="{{ old('code', $matrix->code ?? '') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nome</label>
                                <input id="matrix_name" type="text" name="name" class="form-control"
                                    value="{{ old('name', $matrix->name ?? '') }}" required>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Versão</label>
                                    <input id="matrix_version" type="text" name="version" class="form-control"
                                        value="{{ old('version', $matrix->version ?? '') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        @foreach (['Atual', 'Ativa', 'Inativa'] as $status)
                                            <option value="{{ $status }}"
                                                {{ old('status', $matrix->status ?? 'Ativa') == $status ? 'selected' : '' }}>
                                                {{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Vigência</label>
                                <input type="date" name="effective_from" class="form-control"
                                    value="{{ old('effective_from', $matrix->effective_from ?? '') }}">
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('admin.matrices.index') }}"
                                    class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const courseSelect = document.getElementById('matrix_course_id');
            const versionInput = document.getElementById('matrix_version');
            const nameInput = document.getElementById('matrix_name');

            if (!courseSelect || !versionInput || !nameInput) {
                return;
            }

            const applyMatrixNameTemplate = () => {
                const courseName = courseSelect.options[courseSelect.selectedIndex]?.text?.trim();
                const version = versionInput.value.trim();

                if (!courseName) {
                    return;
                }

                if (version) {
                    nameInput.value = `Matriz ${version} - ${courseName}`;
                    return;
                }

                if (!nameInput.value.trim()) {
                    nameInput.value = `Matriz - ${courseName}`;
                }
            };

            courseSelect.addEventListener('change', applyMatrixNameTemplate);
            versionInput.addEventListener('input', applyMatrixNameTemplate);

            if (!nameInput.value.trim()) {
                applyMatrixNameTemplate();
            }
        });
    </script>
@endsection
