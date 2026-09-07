@extends('layouts.app')

@section('page-title', 'Professores')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Professores</h2>
                <p class="text-muted mb-0">Lista de docentes, disciplinas e disponibilidade cadastradas.</p>
            </div>
        </div>

        <div class="row g-3">
            @forelse ($professors as $professor)
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-1">{{ $professor->name }}</h5>
                                    <div class="text-muted small">{{ $professor->email }}</div>
                                </div>
                                <span
                                    class="badge {{ $professor->active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} border">
                                    {{ $professor->active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </div>

                            <div class="mb-3">
                                <div class="small text-uppercase text-secondary mb-1">Disciplinas</div>
                                @if ($professor->subjects->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($professor->subjects as $subject)
                                            <span class="badge text-bg-light border">{{ $subject->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted small">Nenhuma disciplina atribuída.</span>
                                @endif
                            </div>

                            <div>
                                <div class="small text-uppercase text-secondary mb-1">Disponibilidade</div>
                                @if ($professor->availabilities->isNotEmpty())
                                    <ul class="list-group list-group-flush">
                                        @foreach ($professor->availabilities as $slot)
                                            <li class="list-group-item px-0 py-2">
                                                <div class="d-flex justify-content-between gap-3">
                                                    <strong>{{ ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][$slot->weekday - 1] ?? 'Dia' }}</strong>
                                                    <span class="small text-muted">{{ $slot->starts_at }} -
                                                        {{ $slot->ends_at }}</span>
                                                </div>
                                                <div class="small text-muted">{{ ucfirst($slot->preference) }}@if ($slot->notes)
                                                        · {{ $slot->notes }}
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-muted small">Nenhuma disponibilidade cadastrada.</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-light border">Nenhum professor cadastrado.</div>
                    </div>
                @endforelse
            </div>
        </div>
    @endsection
