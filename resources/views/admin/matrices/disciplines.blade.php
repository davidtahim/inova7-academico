@extends('layouts.app')

@section('page-title', 'Grade de Disciplinas - ' . $matrix->name)

@section('content')
    <div class="container py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2>{{ $matrix->name }}</h2>
                <p class="text-muted mb-0">
                    <strong>Curso:</strong> {{ $matrix->course->name ?? '—' }}
                    <strong class="ms-3">Código:</strong> {{ $matrix->code }}
                </p>
            </div>
            <a href="{{ route('admin.matrices.edit', $matrix) }}" class="btn btn-secondary">
                ← Voltar
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <strong>Erro!</strong>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-4">
            <!-- Coluna Principal: Grade de Disciplinas -->
            <div class="col-lg-8">
                <!-- Resumo Rápido -->
                <div class="card border-0 shadow-sm mb-4 card-soft">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">📊 Resumo da Matriz</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="stat-box">
                                    <div class="stat-number">{{ $summary['total_disciplines'] }}</div>
                                    <div class="stat-label">Disciplinas</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-box">
                                    <div class="stat-number">{{ $summary['total_hours'] }}h</div>
                                    <div class="stat-label">Carga Total</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-box">
                                    <div class="stat-number">{{ count($summary['by_period']) }}</div>
                                    <div class="stat-label">Períodos</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-box">
                                    <div class="stat-number">{{ $summary['total_hours'] > 0 ? round($summary['total_hours'] / (count($summary['by_period']) ?: 1), 1) : 0 }}h</div>
                                    <div class="stat-label">Média/Período</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grade por Período -->
                <div class="card border-0 shadow-sm card-soft">
                    <div class="card-header admin-form-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">📋 Disciplinas por Período</h5>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDisciplineModal">
                            + Adicionar Disciplina
                        </button>
                    </div>
                    <div class="card-body">
                        @if ($disciplinesByPeriod->isEmpty())
                            <div class="alert alert-info">
                                Nenhuma disciplina vinculada. Clique no botão "Adicionar Disciplina" para começar.
                            </div>
                        @else
                            @foreach ($disciplinesByPeriod->sortKeys() as $period => $disciplines)
                                <div class="period-section mb-4 pb-4 {{ !$loop->last ? 'border-bottom' : '' }}">
                                    <h5 class="mb-3">
                                        <span class="badge bg-primary">{{ $period }}º Período</span>
                                        <small class="text-muted ms-2">({{ $disciplines->sum('weekly_hours') }} aulas semanais)</small>
                                    </h5>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 35%;">Disciplina</th>
                                                    <th style="width: 15%;">Carga/Sem</th>
                                                    <th style="width: 15%;">Turno</th>
                                                    <th style="width: 15%;">Tipo</th>
                                                    <th style="width: 10%;" class="text-end">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($disciplines as $discipline)
                                                    <tr>
                                                        <td>
                                                            <strong>{{ $discipline->subject->code ?? '—' }}</strong><br>
                                                            <small class="text-muted">{{ $discipline->subject->name }}</small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info" title="Aulas por semana na grade">{{ $discipline->weekly_hours }} aula(s)/sem</span>
                                                            @if ($discipline->total_hours)<span class="badge bg-light text-dark border">{{ $discipline->total_hours }}h</span>@endif
                                                            @if ($discipline->modality)<span class="badge bg-light text-dark border">{{ $discipline->modality }}</span>@endif
                                                        </td>
                                                        <td>
                                                            <span class="badge" style="background-color: #FFA500;" id="shift-{{ $discipline->shift }}">
                                                                {{ $shifts[$discipline->shift] ?? $discipline->shift }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small>{{ $offeringTypes[$discipline->offering_type] ?? $discipline->offering_type }}</small>
                                                            @if ($discipline->allows_shared_class)
                                                                <br><span class="badge bg-success">Compartilhada</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-end">
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <button type="button" class="btn btn-outline-primary"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#editDisciplineModal{{ $discipline->id }}"
                                                                    title="Editar">
                                                                    ✏️
                                                                </button>
                                                                <form action="{{ route('admin.matrices.disciplines.destroy', [$matrix, $discipline]) }}"
                                                                    method="POST" class="d-inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-outline-danger"
                                                                        onclick="return confirm('Desvincula esta disciplina?')"
                                                                        title="Remover">
                                                                        🗑️
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <!-- Coluna Lateral: Resumos e Legenda -->
            <div class="col-lg-4">
                <!-- Distribuição por Tipo de Oferta -->
                <div class="card border-0 shadow-sm mb-3 card-soft">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">🎓 Por Tipo de Oferta</h5>
                    </div>
                    <div class="card-body">
                        @if (empty($summary['by_offering_type']))
                            <p class="text-muted mb-0">Nenhuma disciplina</p>
                        @else
                            @foreach ($offeringTypes as $key => $label)
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>{{ $label }}</span>
                                    <span class="badge bg-secondary">{{ $summary['by_offering_type'][$key] ?? 0 }}</span>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <!-- Distribuição por Turno -->
                <div class="card border-0 shadow-sm mb-3 card-soft">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">⏰ Por Turno</h5>
                    </div>
                    <div class="card-body">
                        @if (empty($summary['by_shift']))
                            <p class="text-muted mb-0">Nenhuma disciplina</p>
                        @else
                            @foreach ($shifts as $key => $label)
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>{{ $label }}</span>
                                    <span class="badge" style="background-color: {{ $shiftColorMap[$key] ?? '#6C757D' }}">
                                        {{ $summary['by_shift'][$key] ?? 0 }}
                                    </span>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <!-- Legenda de Cores e Restrições -->
                <div class="card border-0 shadow-sm card-soft">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">ℹ️ Legenda</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong class="d-block mb-2">Tipos de Oferta:</strong>
                            <small class="d-block mb-1"><strong>Obrigatória:</strong> Disciplina obrigatória do currículo</small>
                            <small class="d-block mb-1"><strong>Optativa:</strong> Escolha entre disciplinas do curso</small>
                            <small class="d-block mb-1"><strong>Eletiva:</strong> Livre escolha em qualquer curso</small>
                            <small class="d-block"><strong>Integradora:</strong> Projetos que integram múltiplas disciplinas</small>
                        </div>

                        <div class="mb-3">
                            <strong class="d-block mb-2">Turnos Padrão:</strong>
                            @foreach ($shifts as $key => $label)
                                <div class="mb-1">
                                    <small>
                                        <span class="badge" style="background-color: {{ $shiftColorMap[$key] ?? '#6C757D' }}">{{ $key }}</span>
                                        {{ $label }}
                                    </small>
                                </div>
                            @endforeach
                        </div>

                        <div>
                            <strong class="d-block mb-2">Restrições de Sala:</strong>
                            <small class="text-muted">
                                Use tipos de sala quando a disciplina exige espaço específico
                                (ex: laboratório para aulas de programação).
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Adicionar Disciplina -->
    <div class="modal fade" id="addDisciplineModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header admin-form-header">
                    <h5 class="modal-title">Adicionar Disciplina à Matriz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('admin.matrices.disciplines.store', $matrix) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Disciplina *</label>
                                <select name="subject_id" class="form-select" required>
                                    <option value="">Selecione uma disciplina</option>
                                    @foreach ($availableSubjects as $subject)
                                        <option value="{{ $subject->id }}">
                                            {{ $subject->code }} - {{ $subject->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('subject_id')
                                    <div class="form-text text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Período *</label>
                                <select name="period" class="form-select" required>
                                    <option value="">Selecione</option>
                                    @for ($p = 1; $p <= 8; $p++)
                                        <option value="{{ $p }}">{{ $p }}º Período</option>
                                    @endfor
                                </select>
                                @error('period')
                                    <div class="form-text text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Carga Horária Semanal *</label>
                                <input type="number" name="weekly_hours" class="form-control" min="0" max="30" required>
                                @error('weekly_hours')
                                    <div class="form-text text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Turno Padrão *</label>
                                <select name="shift" class="form-select" required>
                                    <option value="">Selecione</option>
                                    @foreach ($shifts as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('shift')
                                    <div class="form-text text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tipo de Oferta *</label>
                                <select name="offering_type" class="form-select" required>
                                    <option value="">Selecione</option>
                                    @foreach ($offeringTypes as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('offering_type')
                                    <div class="form-text text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Tipo de Sala Requerida</label>
                                <select name="required_room_type" class="form-select">
                                    <option value="">Qualquer sala</option>
                                    @foreach ($roomTypes as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('required_room_type')
                                    <div class="form-text text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Máx. Aulas Consecutivas</label>
                                <input type="number" name="max_consecutive_classes" class="form-control" value="2" min="1" max="6">
                                @error('max_consecutive_classes')
                                    <div class="form-text text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="allows_shared_class" id="allowsSharedClass" value="1">
                                    <label class="form-check-label" for="allowsSharedClass">
                                        Permite aula compartilhada
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($disciplinesByPeriod->flatten() as $discipline)
        <!-- Modal: Editar Disciplina -->
        <div class="modal fade" id="editDisciplineModal{{ $discipline->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header admin-form-header">
                        <h5 class="modal-title">Editar: {{ $discipline->subject->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="{{ route('admin.matrices.disciplines.update', [$matrix, $discipline]) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Período *</label>
                                    <select name="period" class="form-select" required>
                                        @for ($p = 1; $p <= 8; $p++)
                                            <option value="{{ $p }}" {{ $discipline->period == $p ? 'selected' : '' }}>
                                                {{ $p }}º Período
                                            </option>
                                        @endfor
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Carga Horária Semanal *</label>
                                    <input type="number" name="weekly_hours" class="form-control" value="{{ $discipline->weekly_hours }}" min="0" max="30" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Turno Padrão *</label>
                                    <select name="shift" class="form-select" required>
                                        @foreach ($shifts as $key => $label)
                                            <option value="{{ $key }}" {{ $discipline->shift == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Tipo de Oferta *</label>
                                    <select name="offering_type" class="form-select" required>
                                        @foreach ($offeringTypes as $key => $label)
                                            <option value="{{ $key }}" {{ $discipline->offering_type == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Tipo de Sala Requerida</label>
                                    <select name="required_room_type" class="form-select">
                                        <option value="">Qualquer sala</option>
                                        @foreach ($roomTypes as $key => $label)
                                            <option value="{{ $key }}" {{ $discipline->required_room_type == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Máx. Aulas Consecutivas</label>
                                    <input type="number" name="max_consecutive_classes" class="form-control"
                                        value="{{ $discipline->max_consecutive_classes }}" min="1" max="6">
                                </div>

                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="allows_shared_class"
                                            id="allowsSharedClass{{ $discipline->id }}" value="1"
                                            {{ $discipline->allows_shared_class ? 'checked' : '' }}>
                                        <label class="form-check-label" for="allowsSharedClass{{ $discipline->id }}">
                                            Permite aula compartilhada
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Atualizar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    <style>
        .stat-box {
            padding: 1rem;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: bold;
            color: #0d6efd;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .period-section {
            scroll-margin-top: 1rem;
        }
    </style>

    @php
        $shiftColorMap = [
            'MANHÃ' => '#FFA500',
            'TARDE' => '#FF6B6B',
            'NOITE' => '#6C63FF',
            'INTEGRAL' => '#4ECDC4',
        ];
    @endphp
@endsection
