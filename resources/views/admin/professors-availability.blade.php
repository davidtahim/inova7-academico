@extends('layouts.app')

@php
    $ownArea = request()->routeIs('teacher.*');
    $backUrl = $ownArea ? route('teacher.dashboard') : (Gate::allows('access-catalog') ? route('admin.professors.index') : route('dashboard'));
@endphp

@section('title', ($ownArea ? 'Minha disponibilidade' : 'Disponibilidade — ' . $professor->name) . ' • Inova7 Acadêmico')
@section('page-title', $ownArea ? 'Minha disponibilidade' : 'Disponibilidade docente')

@section('content')
    <div class="container-fluid py-3 px-0">
        <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-3">
            <div style="max-width: 760px">
                <h2 class="h4 mb-1">
                    {{ $ownArea ? 'Quando você pode dar aula' : 'Disponibilidade de ' . $professor->name }}
                    @if ($term)
                        <span class="badge {{ $term->isClosed() ? 'bg-secondary' : 'bg-primary' }} align-middle fs-6">{{ $term->code }}</span>
                    @endif
                </h2>
                <p class="text-muted mb-0">
                    @if ($ownArea)
                        Formulário CCG-FOR-02. Marque os horários em que você <strong>pode dar aula</strong> e destaque com
                        <strong>Prefiro</strong> os melhores. O que ficar sem marcação conta como <strong>não posso</strong>.
                    @else
                        Formulário CCG-FOR-02 do semestre {{ $term?->code }}. ✓ pode dar aula · ★ prefere · sem marcação: não pode.
                    @endif
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if ($ownArea)
                    <a href="{{ route('teacher.availability.pdf') }}" class="btn btn-outline-primary" target="_blank" rel="noopener">Gerar CCG-FOR-02 (PDF)</a>
                    <a href="{{ route('teacher.subjects') }}" class="btn btn-outline-secondary">Minhas disciplinas</a>
                @else
                    <a href="{{ route('admin.professors.subjects', $professor) }}" class="btn btn-outline-secondary">Habilitação</a>
                @endif
                <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Voltar</a>
            </div>
        </div>

        @if (! $term)
            <div class="alert alert-secondary">Nenhum semestre cadastrado.</div>
        @else
            @include('partials.availability-grid')
        @endif
    </div>
@endsection
