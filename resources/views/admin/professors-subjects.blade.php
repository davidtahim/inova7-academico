@extends('layouts.app')

@section('page-title', 'Disciplinas - ' . $professor->name)

@section('content')
    <div class="container py-4">
        <div class="page-head mb-4">
            <div>
                <h2>Disponibilidade de Disciplinas</h2>
                <p><strong>Professor:</strong> {{ $professor->name }}</p>
            </div>
            <a href="{{ route('admin.professors.index') }}" class="btn btn-outline-secondary">Voltar</a>
        </div>

        <div class="row g-3">
            <!-- Disciplinas Disponíveis -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0">Disciplinas Habilitadas</h5>
                        <small class="text-muted">Disciplinas que {{ $professor->name }} está habilitado para lecionar</small>
                    </div>
                    <div class="card-body">
                        @if($selectedSubjects->isEmpty())
                            <div class="alert alert-info mb-0">
                                Nenhuma disciplina selecionada para este professor.
                            </div>
                        @else
                            <div class="row g-2">
                                @foreach($selectedSubjects as $subject)
                                    <div class="col-md-6">
                                        <div class="card card-sm border-0 bg-light">
                                            <div class="card-body d-flex align-items-center justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">{{ $subject->name }}</h6>
                                                    <small class="text-muted">
                                                        Código: {{ $subject->code }}
                                                        @if($subject->total_hours)
                                                            | {{ $subject->total_hours }}h
                                                        @endif
                                                    </small>
                                                </div>
                                                <span class="badge bg-success">✓</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Resumo e Estatísticas -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0">Resumo</h5>
                    </div>
                    <div class="card-body">
                        <div class="stat-row mb-3">
                            <span class="text-muted">Total de disciplinas</span>
                            <strong class="fs-5">{{ $totalSubjects }}</strong>
                        </div>
                        <div class="stat-row mb-3">
                            <span class="text-muted">Habilitado em</span>
                            <span class="badge bg-success" style="font-size: 1.1em;">{{ $selectedCount }}</span>
                        </div>
                        <div class="stat-row">
                            <span class="text-muted">Não habilitado</span>
                            <span class="badge bg-secondary" style="font-size: 1.1em;">{{ $unselectedCount }}</span>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0">Informações</h5>
                    </div>
                    <div class="card-body small">
                        <div class="mb-2">
                            <strong>Curso:</strong><br>
                            Sistemas de Informação
                        </div>
                        <div>
                            <strong>E-mail:</strong><br>
                            {{ $professor->email ?? '—' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Todas as Disciplinas Disponíveis -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-light border-0 d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Todas as Disciplinas</h5>
                <small class="text-muted">Clique para adicionar/remover</small>
            </div>
            <div class="card-body">
                @if($allSubjects->isEmpty())
                    <div class="alert alert-warning mb-0">
                        Nenhuma disciplina cadastrada no sistema.
                    </div>
                @else
                    <form action="{{ route('admin.professors.update-subjects', $professor) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row g-2 mb-3">
                            @foreach($allSubjects as $subject)
                                @php
                                    $isSelected = $selectedSubjects->contains($subject->id);
                                @endphp
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="subject_ids[]" 
                                            value="{{ $subject->id }}" id="subject_{{ $subject->id }}"
                                            {{ $isSelected ? 'checked' : '' }}>
                                        <label class="form-check-label w-100" for="subject_{{ $subject->id }}">
                                            <strong>{{ $subject->name }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $subject->code }}</small>
                                            @if($subject->total_hours)
                                                <br>
                                                <small class="text-muted">{{ $subject->total_hours }}h</small>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('admin.professors.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                Salvar Disciplinas
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        <!-- Ações -->
        <div class="d-flex gap-2 justify-content-end mt-4">
            <a href="{{ route('admin.professors.index') }}" class="btn btn-outline-secondary">Voltar</a>
            <a href="{{ route('admin.professors.availability', $professor) }}" class="btn btn-primary">
                📅 Ver Disponibilidade de Horários
            </a>
        </div>
    </div>

    <style>
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .card-sm {
            margin-bottom: 0;
        }

        .form-check-label {
            cursor: pointer;
            padding: 8px;
        }

        .form-check-input {
            cursor: pointer;
            margin-top: 0.3em;
        }
    </style>
@endsection
