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
            </div>
            <a href="{{ route('admin.professors.index') }}" class="btn btn-outline-secondary">Voltar</a>
        </div>

        <div class="row g-3">
            <!-- Calendário de Disponibilidade -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0">Grade de Horários</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered availability-grid">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width: 15%;">Horário</th>
                                        <th class="text-center">Seg</th>
                                        <th class="text-center">Ter</th>
                                        <th class="text-center">Qua</th>
                                        <th class="text-center">Qui</th>
                                        <th class="text-center">Sex</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($timeSlots as $slot)
                                        <tr>
                                            <td class="text-center fw-bold small">{{ $slot['time'] }}</td>
                                            @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day)
                                                @php
                                                    $dayNum = match($day) {
                                                        'monday' => 1,
                                                        'tuesday' => 2,
                                                        'wednesday' => 3,
                                                        'thursday' => 4,
                                                        'friday' => 5,
                                                    };
                                                    $available = $availabilities
                                                        ->where('weekday', $dayNum)
                                                        ->where('starts_at', $slot['start'])
                                                        ->where('ends_at', $slot['end'])
                                                        ->first();
                                                    
                                                    $status = $available ? $available->preference : 'unavailable';
                                                    $class = match($status) {
                                                        'preferred' => 'bg-success text-white',
                                                        'available' => 'bg-info text-white',
                                                        'unavailable' => 'bg-light text-muted',
                                                        default => 'bg-light'
                                                    };
                                                    $label = match($status) {
                                                        'preferred' => '✓ Preferido',
                                                        'available' => '◐ Disponível',
                                                        'unavailable' => '✗ Indisponível',
                                                        default => '—'
                                                    };
                                                @endphp
                                                <td class="text-center p-0">
                                                    <div class="availability-cell {{ $class }}">
                                                        <small>{{ $label }}</small>
                                                    </div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumo e Legenda -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0">Legenda</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success" style="min-width: 50px;">✓</span>
                                <span>Preferido - Horário preferencial</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-info" style="min-width: 50px;">◐</span>
                                <span>Disponível - Pode estar</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-light text-muted" style="min-width: 50px;">✗</span>
                                <span>Indisponível - Não pode estar</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0">Resumo</h5>
                    </div>
                    <div class="card-body">
                        <div class="stat-row mb-2">
                            <span class="text-muted">Total de horários</span>
                            <strong>{{ $totalSlots }}</strong>
                        </div>
                        <div class="stat-row mb-2">
                            <span class="text-muted">Preferidos</span>
                            <span class="badge bg-success">{{ $preferredCount }}</span>
                        </div>
                        <div class="stat-row mb-2">
                            <span class="text-muted">Disponíveis</span>
                            <span class="badge bg-info">{{ $availableCount }}</span>
                        </div>
                        <div class="stat-row">
                            <span class="text-muted">Indisponíveis</span>
                            <span class="badge bg-light text-muted">{{ $unavailableCount }}</span>
                        </div>
                    </div>
                </div>

                @if($term)
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0">Informações</h5>
                        </div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <strong>Semestre:</strong><br>
                                {{ $term->code }}
                                @if($term->starts_at)
                                    <br><strong>Período:</strong><br>
                                    {{ $term->starts_at->format('d/m/Y') }} a {{ $term->ends_at->format('d/m/Y') }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Horários Detalhados -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-light border-0">
                <h5 class="mb-0">Detalhamento de Horários</h5>
            </div>
            <div class="card-body">
                @if($availabilities->isEmpty())
                    <div class="alert alert-info mb-0">
                        Nenhum horário de disponibilidade cadastrado para este professor neste semestre.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Dia da Semana</th>
                                    <th>Horário</th>
                                    <th>Preferência</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($availabilities->sortBy(['weekday', 'starts_at']) as $slot)
                                    <tr>
                                        <td>
                                            <strong>
                                                @switch($slot->weekday)
                                                    @case(1) Segunda-feira @break
                                                    @case(2) Terça-feira @break
                                                    @case(3) Quarta-feira @break
                                                    @case(4) Quinta-feira @break
                                                    @case(5) Sexta-feira @break
                                                    @case(6) Sábado @break
                                                @endswitch
                                            </strong>
                                        </td>
                                        <td>
                                            {{ \Carbon\Carbon::parse($slot->starts_at)->format('H:i') }} - 
                                            {{ \Carbon\Carbon::parse($slot->ends_at)->format('H:i') }}
                                        </td>
                                        <td>
                                            <span class="badge 
                                                @if($slot->preference === 'preferred') bg-success
                                                @elseif($slot->preference === 'available') bg-info
                                                @else bg-light text-muted
                                                @endif">
                                                @switch($slot->preference)
                                                    @case('preferred') Preferido @break
                                                    @case('available') Disponível @break
                                                    @default Indisponível
                                                @endswitch
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ $slot->created_at ? 'Registrado em ' . $slot->created_at->format('d/m/Y H:i') : '—' }}
                                            </small>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Ações -->
        <div class="d-flex gap-2 justify-content-end mt-4">
            <a href="{{ route('admin.professors.index') }}" class="btn btn-outline-secondary">Voltar</a>
            <a href="{{ route('teacher.availability.pdf') }}" class="btn btn-primary" target="_blank">
                📄 Exportar como PDF
            </a>
        </div>
    </div>

    <style>
        .availability-grid {
            margin-bottom: 0;
        }

        .availability-cell {
            padding: 8px 4px !important;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .availability-cell:hover {
            opacity: 0.8;
        }

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
    </style>
@endsection
