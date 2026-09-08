@extends('layouts.app')

@section('page-title', isset($professor->id) ? 'Editar professor' : 'Novo professor')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">{{ isset($professor->id) ? 'Editar professor' : 'Novo professor' }}</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST"
                            action="{{ isset($professor->id) ? route('admin.professors.update', $professor) : route('admin.professors.store') }}">
                            @csrf
                            @if (isset($professor->id))
                                @method('PUT')
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Nome</label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $professor->name ?? '') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email', $professor->email ?? '') }}">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Matrícula</label>
                                    <input type="text" name="registration" class="form-control"
                                        value="{{ old('registration', $professor->registration ?? '') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Qualificação</label>
                                    <input type="text" name="qualification" class="form-control"
                                        value="{{ old('qualification', $professor->qualification ?? '') }}">
                                </div>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" name="active" value="1"
                                    {{ old('active', $professor->active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label">Professor ativo</label>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('admin.professors.index') }}"
                                    class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
