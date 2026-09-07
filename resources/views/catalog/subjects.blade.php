@extends('layouts.app')

@section('page-title', 'Disciplinas')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Disciplinas</h2>
                <p class="text-muted mb-0">Consulta das disciplinas e seus professores vinculados.</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('catalog.subjects') }}" class="row g-2 align-items-center">
                    <div class="col-md-10">
                        <input type="text" name="q" class="form-control" value="{{ $query ?? '' }}"
                            placeholder="Buscar por nome ou código da disciplina">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            @forelse ($subjects as $subject)
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="text-uppercase small text-secondary">{{ $subject->code }}</div>
                                    <h5 class="mb-0">{{ $subject->name }}</h5>
                                </div>
                                <span class="badge text-bg-light border">{{ $subject->total_hours ?? 0 }}h</span>
                            </div>

                            @if ($subject->professors->isNotEmpty())
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($subject->professors as $professor)
                                        <span
                                            class="badge bg-primary-subtle text-primary border">{{ $professor->name }}</span>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted small">Nenhum professor vinculado.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-light border">Nenhuma disciplina cadastrada.</div>
                </div>
            @endforelse
        </div>
    </div>
@endsection
