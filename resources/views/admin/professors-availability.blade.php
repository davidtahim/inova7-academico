@extends('layouts.app')

@section('page-title', 'Disponibilidade - ' . $professor->name)

@section('content')
    <div class="container py-4">
        <div class="page-head mb-4">
            <div>
                <h2>Disponibilidade de Horários</h2>
                <p><strong>Professor:</strong> {{ $professor->name }}</p>
                @if($term)
                    <p><strong>Semestre:</strong> {{ $term->code }}</p>
                @endif
                @if($isEditable)
                    <p class="text-info mb-0"><small>✏️ Clique nos horários para selecionar sua disponibilidade</small></p>
                @endif
            </div>
            <a href="{{ request()->routeIs('teacher.*') ? route('teacher.dashboard') : (Gate::allows('access-catalog') ? route('admin.professors.index') : route('dashboard')) }}" class="btn btn-outline-secondary">Voltar</a>
        </div>

        @include('partials.availability-grid')

        <!-- Ações -->
        <div class="d-flex gap-2 justify-content-end mt-4">
            <a href="{{ request()->routeIs('teacher.*') ? route('teacher.dashboard') : (Gate::allows('access-catalog') ? route('admin.professors.index') : route('dashboard')) }}" class="btn btn-outline-secondary">Voltar</a>
            @if(request()->routeIs('teacher.*'))
                {{-- O CCG-FOR-02 é gerado pelo próprio professor, na área docente. --}}
                <a href="{{ route('teacher.availability.pdf') }}" class="btn btn-primary" target="_blank">
                    📄 Exportar como PDF
                </a>
            @endif
        </div>
    </div>
@endsection
