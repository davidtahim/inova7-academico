@extends('layouts.app')

@section('page-title', isset($professor->id) ? 'Editar professor' : 'Novo professor')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm admin-form-card">
                    <div class="card-header admin-form-header">
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
                                <div class="form-text">Cadastre o nome completo do professor conforme consta no quadro
                                    docente.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email', $professor->email ?? '') }}">
                                <div class="form-text">Use o e-mail institucional do docente, quando houver.</div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Matrícula</label>
                                    <input type="text" name="registration" class="form-control"
                                        value="{{ old('registration', $professor->registration ?? '') }}">
                                    <div class="form-text">Ex.: matrícula interna ou código funcional do docente.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Chapa</label>
                                    <input type="text" name="chapa" class="form-control"
                                        value="{{ old('chapa', $professor->chapa ?? '') }}">
                                    <div class="form-text">Ex.: CH-101.</div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Qualificação</label>
                                    <input type="text" name="qualification" class="form-control"
                                        value="{{ old('qualification', $professor->qualification ?? '') }}">
                                    <div class="form-text">Ex.: Mestre, Doutor, Especialista.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Lattes</label>
                                    <input type="url" name="lattes" class="form-control"
                                        value="{{ old('lattes', $professor->lattes ?? '') }}">
                                    <div class="form-text">URL do Currículo Lattes.</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Cursos vinculados</label>
                                <div class="border rounded p-3 bg-light">
                                    @php $selectedCourseIds = old('course_ids', $professor->courses->pluck('id')->all()); @endphp
                                    @forelse (\App\Models\Course::orderBy('name')->get() as $course)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="course_ids[]"
                                                value="{{ $course->id }}" id="course_{{ $course->id }}"
                                                {{ in_array((string) $course->id, array_map('strval', $selectedCourseIds), true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="course_{{ $course->id }}">
                                                {{ $course->name }} ({{ $course->code }})
                                            </label>
                                        </div>
                                    @empty
                                        <div class="text-muted small">Nenhum curso cadastrado.</div>
                                    @endforelse
                                </div>
                                <div class="form-text">Selecione os cursos aos quais este professor está vinculado.</div>
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
