@extends('layouts.app')

@section('page-title', 'Alunos')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Alunos</h2>
                <p class="text-muted mb-0">Consulta dos alunos cadastrados no sistema.</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('catalog.students') }}" class="row g-2 align-items-center">
                    <div class="col-md-8">
                        <input type="text" name="q" class="form-control" value="{{ $query ?? '' }}"
                            placeholder="Buscar por nome, e-mail ou matrícula">
                    </div>
                    <div class="col-md-4 d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        @if (($query ?? '') !== '')
                            <a href="{{ route('catalog.students') }}" class="btn btn-outline-secondary">Limpar</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            @forelse ($students as $student)
                <div class="col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-1">{{ $student->name }}</h6>
                                    <div class="small text-muted">{{ $student->email }}</div>
                                </div>
                            </div>
                            <div class="small text-muted">Matrícula: {{ $student->registration_number ?? 'Não informada' }}
                            </div>
                            <div class="mt-3">
                                <a href="{{ route('catalog.student.detail', $student) }}"
                                    class="btn btn-sm btn-outline-primary">Ver histórico</a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-light border">Nenhum aluno cadastrado.</div>
                </div>
            @endforelse
        </div>
    </div>
@endsection
