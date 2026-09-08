@extends('layouts.app')

@section('page-title', 'Cursos e matrizes')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Cursos e matrizes</h2>
                <p class="text-muted mb-0">Consulta rápida dos cursos e versões das matrizes curriculares.</p>
            </div>
        </div>

        <div class="row g-3">
            @forelse ($courses as $course)
                <div class="col-lg-6">
                    <article class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                <div>
                                    <div class="text-uppercase small text-secondary fw-semibold">{{ $course->code }}</div>
                                    <h5 class="mb-0">{{ $course->name }}</h5>
                                </div>
                                <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-sm btn-outline-primary">
                                    Editar em admin/cursos
                                </a>
                            </div>
                            <div class="small text-secondary mb-3">{{ $course->degree ?? 'Curso' }}</div>

                            @if ($course->matrices->isNotEmpty())
                                <div class="d-grid gap-2">
                                    @foreach ($course->matrices as $matrix)
                                        <div class="matrix-card">
                                            <div class="matrix-header">
                                                <div>
                                                    <strong>{{ $matrix->name ?: 'Matriz ' . ($matrix->version ?? $loop->iteration) . ' - ' . $course->name }}</strong>
                                                    <div class="matrix-code">{{ $matrix->code }}</div>
                                                </div>
                                                <a href="{{ route('admin.matrices.edit', $matrix) }}"
                                                    class="btn btn-sm btn-outline-secondary matrix-edit-btn">Editar</a>
                                            </div>

                                            @if ($matrix->subjects->isNotEmpty())
                                                <details class="matrix-structure">
                                                    <summary class="matrix-summary">Estrutura da disciplina</summary>
                                                    <div class="matrix-subjects">
                                                        @foreach ($matrix->subjects as $subject)
                                                            <div class="subject-item">
                                                                <div class="subject-main">
                                                                    <div>
                                                                        <div class="subject-name">{{ $subject->name }}</div>
                                                                        <div class="subject-code">{{ $subject->code }}</div>
                                                                    </div>
                                                                    @if (!empty($subject->syllabus))
                                                                        <details class="syllabus-details">
                                                                            <summary class="syllabus-summary">Abrir ementa
                                                                            </summary>
                                                                            <div class="syllabus-block">
                                                                                {{ $subject->syllabus }}</div>
                                                                        </details>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @else
                                                <div class="empty-matrix">Nenhuma disciplina associada a esta matriz.</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted small mt-2">Nenhuma matriz cadastrada para este curso.</div>
                            @endif
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-light border">Nenhum curso cadastrado.</div>
                </div>
            @endforelse
        </div>
    </div>
@endsection
