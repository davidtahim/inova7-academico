@extends('layouts.app')

@section('page-title', isset($subject->id) ? 'Editar disciplina' : 'Nova disciplina')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">{{ isset($subject->id) ? 'Editar disciplina' : 'Nova disciplina' }}</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST"
                            action="{{ isset($subject->id) ? route('admin.subjects.update', $subject) : route('admin.subjects.store') }}">
                            @csrf
                            @if (isset($subject->id))
                                @method('PUT')
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Código</label>
                                <input type="text" name="code" class="form-control"
                                    value="{{ old('code', $subject->code ?? '') }}" required>
                                <div class="form-text">Use o código oficial da disciplina, por exemplo: GSER133620.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nome</label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $subject->name ?? '') }}" required>
                                <div class="form-text">Cadastre o nome completo da disciplina, como Banco de Dados.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Ementa</label>
                                <textarea name="syllabus" class="form-control" rows="5" placeholder="Descreva os objetivos, conteúdos e competências da disciplina...">{{ old('syllabus', $subject->syllabus ?? '') }}</textarea>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Total</label>
                                    <input type="number" min="0" name="total_hours" class="form-control"
                                        value="{{ old('total_hours', $subject->total_hours ?? 0) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Presenciais</label>
                                    <input type="number" min="0" name="presential_hours" class="form-control"
                                        value="{{ old('presential_hours', $subject->presential_hours ?? 0) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Online</label>
                                    <input type="number" min="0" name="online_hours" class="form-control"
                                        value="{{ old('online_hours', $subject->online_hours ?? 0) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Práticas</label>
                                    <input type="number" min="0" name="practice_hours" class="form-control"
                                        value="{{ old('practice_hours', $subject->practice_hours ?? 0) }}">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Extensão</label>
                                <input type="number" min="0" name="extension_hours" class="form-control"
                                    value="{{ old('extension_hours', $subject->extension_hours ?? 0) }}">
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('admin.subjects.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
