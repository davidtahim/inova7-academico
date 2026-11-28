@extends('layouts.app')
@section('title', 'Auditoria de curso • Inova7 Acadêmico')
@section('page-title', 'Auditoria de curso')

@section('content')
    <div class="container-fluid py-4 px-0">
        <div class="d-flex flex-wrap gap-3 align-items-end justify-content-between mb-3">
            <div>
                <h2 class="h4 mb-1">Auditoria de curso — {{ $term->code ?? 'sem semestre' }}</h2>
                <p class="text-muted mb-0">
                    Checklist do {{ $catalog->source() }}: a nota prevista de cada indicador e os documentos que precisam estar no e-Ser.
                    O resultado é uma estimativa para a coordenação se preparar; a nota oficial é a do auditor.
                </p>
            </div>
            <a href="{{ route('audit.index') }}" class="btn btn-outline-secondary btn-sm">Templates e CCG-FOR-01</a>
        </div>

        <div class="d-flex flex-wrap gap-2 small mb-3" aria-label="Faixas de resultado">
            @foreach ([['Delegação II', '4,00–5,00'], ['Delegação I', '3,70–3,99'], ['Gestão II', '3,50–3,69'], ['Gestão I', '3,00–3,49'], ['Supervisão II', '2,50–2,99'], ['Supervisão I', '1,00–2,49']] as [$band, $range])
                <span class="badge rounded-pill audit-band audit-band-{{ \Illuminate\Support\Str::slug($band) }}">{{ $band }} · {{ $range }}</span>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Coordenação</th>
                            <th style="width: 18%">Documentos</th>
                            <th style="width: 14%">Indicadores avaliados</th>
                            <th>Resultado previsto</th>
                            <th class="text-end"><span class="visually-hidden">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $course = $row['course'];
                                $r = $row['result'];
                                $docsPct = $r && $r['documents']['total'] ? round(100 * $r['documents']['delivered'] / $r['documents']['total']) : 0;
                            @endphp
                            <tr>
                                <td><strong>{{ $course->name }}</strong><br><small class="text-muted">{{ $course->code }}</small></td>
                                <td class="small">
                                    {{ $course->coordinator?->name ?? '—' }}
                                    @if ($course->assistants->isNotEmpty())<br><span class="text-muted">Assist.: {{ $course->assistants->pluck('name')->implode(', ') }}</span>@endif
                                </td>
                                <td>
                                    @if ($r)
                                        <div class="progress" role="progressbar" aria-label="Documentos entregues" aria-valuenow="{{ $docsPct }}" aria-valuemin="0" aria-valuemax="100" style="height: .5rem">
                                            <div class="progress-bar bg-success" style="width: {{ $docsPct }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $r['documents']['delivered'] }} de {{ $r['documents']['total'] }} entregues</small>
                                    @else
                                        <small class="text-muted">Não iniciada</small>
                                    @endif
                                </td>
                                <td class="small">{{ $r ? $r['rated'] . ' de ' . $r['applicable'] : '—' }}</td>
                                <td>
                                    @if ($r && $r['score'] !== null)
                                        <span class="badge audit-band audit-band-{{ \Illuminate\Support\Str::slug($r['band']) }}">{{ $r['band'] }}</span>
                                        <strong class="ms-1">{{ number_format($r['score'], 2, ',', '') }}</strong>
                                        @if ($r['alerts'])<br><small class="text-danger">{{ count($r['alerts']) }} imprescindível(is) abaixo de 3</small>@endif
                                    @else
                                        <small class="text-muted">—</small>
                                    @endif
                                </td>
                                <td class="text-end"><a href="{{ route('audit.courses.show', $course) }}" class="btn btn-sm btn-primary">Abrir</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum curso ativo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @include('audit.courses.styles')
@endsection
