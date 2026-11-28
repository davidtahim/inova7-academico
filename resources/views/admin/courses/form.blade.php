@extends('layouts.app')

@section('page-title', isset($course->id) ? 'Editar curso' : 'Novo curso')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm admin-form-card">
                    <div class="card-header admin-form-header">
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
                                <div class="form-text">Use o padrão institucional do curso, como SI, ADS ou outros códigos
                                    oficiais.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nome</label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $course->name ?? '') }}" required>
                                <div class="form-text">Cadastre o nome completo do curso, por exemplo: Sistemas de
                                    Informação.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Grau</label>
                                <input type="text" name="degree" class="form-control"
                                    value="{{ old('degree', $course->degree ?? '') }}">
                                <div class="form-text">Ex.: Bacharelado, Licenciatura, Tecnólogo.</div>
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

                @if (isset($course->id))
                    {{-- Matrizes do curso: o vínculo curso × matriz visto e ajustado daqui. --}}
                    <section class="card border-0 shadow-sm mt-3" id="matrizes" aria-labelledby="matrizes-title">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <h3 class="h6 mb-0 me-auto" id="matrizes-title">Matrizes deste curso ({{ $courseMatrices->count() }})</h3>
                                <a href="{{ route('admin.matrices.create', ['course' => $course->id]) }}" class="btn btn-sm btn-primary">Nova matriz para este curso</a>
                            </div>

                            @if ($courseMatrices->isEmpty())
                                <p class="small text-warning-emphasis mb-3">
                                    Este curso ainda não tem matriz: as disciplinas dele não aparecem na habilitação docente, na montagem nem na importação.
                                </p>
                            @else
                                <ul class="list-group list-group-flush mb-3">
                                    @foreach ($courseMatrices as $matrix)
                                        <li class="list-group-item px-0 d-flex flex-wrap align-items-center gap-2">
                                            <div class="me-auto">
                                                <strong>{{ $matrix->name }}</strong>
                                                <span class="text-muted small">· {{ $matrix->code }} · {{ $matrix->disciplines_for_offering_count }} disciplinas</span>
                                                <span class="badge {{ in_array($matrix->status, ['Atual', 'Ativa']) ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">{{ $matrix->status ?? '—' }}</span>
                                            </div>
                                            <a href="{{ route('admin.subjects.index', ['matriz' => $matrix->id]) }}" class="btn btn-sm btn-outline-primary">Grade</a>
                                            <a href="{{ route('admin.matrices.edit', $matrix) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($otherMatrices->isNotEmpty())
                                <form method="POST" action="{{ route('admin.courses.attach-matrix', $course) }}" class="d-flex flex-wrap gap-2 align-items-end border-top pt-3">
                                    @csrf
                                    <div class="flex-grow-1">
                                        <label class="form-label small fw-semibold" for="attach-matrix">Trazer matriz de outro curso</label>
                                        <select id="attach-matrix" name="matrix_id" class="form-select form-select-sm" required>
                                            <option value="">Selecione…</option>
                                            @foreach ($otherMatrices->groupBy(fn ($m) => $m->course?->name ?? 'Sem curso') as $courseName => $group)
                                                <optgroup label="Hoje em: {{ $courseName }}">
                                                    @foreach ($group as $matrix)
                                                        <option value="{{ $matrix->id }}">{{ $matrix->name }} ({{ $matrix->code }})</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                        <div class="form-text">Use quando a matriz foi cadastrada no curso errado ou num curso duplicado. A grade e as turmas vêm junto.</div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-outline-primary"
                                        onclick="return confirm('Mover a matriz selecionada para {{ addslashes($course->name) }}?')">Mover para este curso</button>
                                </form>
                            @endif
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
@endsection
