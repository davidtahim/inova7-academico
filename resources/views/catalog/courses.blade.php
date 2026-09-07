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
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <div class="text-uppercase small text-secondary">{{ $course->code }}</div>
                                    <h5 class="mb-0">{{ $course->name }}</h5>
                                </div>
                                <span class="badge bg-light text-dark border">{{ $course->degree ?? 'Curso' }}</span>
                            </div>

                            @if ($course->matrices->isNotEmpty())
                                <ul class="list-group list-group-flush">
                                    @foreach ($course->matrices as $matrix)
                                        <li class="list-group-item px-0 py-2">
                                            <div class="d-flex justify-content-between gap-3 align-items-center">
                                                <div>
                                                    <strong>{{ $matrix->name }}</strong>
                                                    <div class="small text-muted">{{ $matrix->code }}</div>
                                                </div>
                                                <span
                                                    class="badge text-bg-light border">{{ $matrix->status ?? 'ativa' }}</span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="text-muted small mt-3">Nenhuma matriz cadastrada para este curso.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-light border">Nenhum curso cadastrado.</div>
                </div>
            @endforelse
        </div>
    </div>
@endsection
