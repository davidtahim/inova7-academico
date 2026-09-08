@extends('layouts.app')

@section('page-title', isset($course->id) ? 'Editar curso' : 'Novo curso')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">{{ isset($course->id) ? 'Editar curso' : 'Novo curso' }}</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST"
                            action="{{ isset($course->id) ? route('admin.courses.update', $course) : route('admin.courses.store') }}">
                            @csrf
                            @if (isset($course->id))
                                @method('PUT')
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Código</label>
                                <input type="text" name="code" class="form-control"
                                    value="{{ old('code', $course->code ?? '') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nome</label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $course->name ?? '') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Grau</label>
                                <input type="text" name="degree" class="form-control"
                                    value="{{ old('degree', $course->degree ?? '') }}">
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" name="active" value="1"
                                    {{ old('active', $course->active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label">Curso ativo</label>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('admin.courses.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
