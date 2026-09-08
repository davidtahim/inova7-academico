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
                                        <div class="border rounded-3 px-3 py-2 bg-light-subtle">
                                            <div>
                                                <strong>{{ $matrix->name ?: 'Matriz ' . ($matrix->version ?? $loop->iteration) . ' - ' . $course->name }}</strong>
                                            </div>
                                            <div class="small text-muted mt-1">{{ $matrix->code }}</div>
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
