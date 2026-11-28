@extends('layouts.app')
@section('title', 'Auditoria • ' . $course->name)
@section('page-title', 'Auditoria de curso')

@php
    $indicators = $catalog->indicators();
    $units = $result['units'];
    $groups = $catalog->groups();
    $statusLabels = \App\Models\CourseAuditDocument::STATUSES;
    $bandSlug = $result['band'] ? \Illuminate\Support\Str::slug($result['band']) : null;
    $docsPct = $result['documents']['total'] ? round(100 * $result['documents']['delivered'] / $result['documents']['total']) : 0;
@endphp

@section('content')
    <div class="container-fluid py-4 px-0">
        <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-3">
            <div>
                <a href="{{ route('audit.courses.index') }}" class="small">‹ Todos os cursos</a>
                <h2 class="h4 mb-1 mt-1">{{ $course->name }} · {{ $term->code }}</h2>
                <p class="text-muted mb-0 small">
                    Coordenação: {{ $course->coordinator?->name ?? 'não definida' }}
                    @if ($course->assistants->isNotEmpty()) · Assistentes: {{ $course->assistants->pluck('name')->implode(', ') }}@endif
                    · {{ $catalog->source() }}
                </p>
            </div>
            @unless ($canEdit)
                <span class="badge text-bg-secondary">Somente consulta</span>
            @endunless
        </div>

        {{-- Resultado previsto --}}
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="small text-uppercase text-muted">Resultado previsto</div>
                    @if ($result['score'] !== null)
                        <div class="d-flex align-items-baseline gap-2 mt-1">
                            <span class="fs-2 fw-bold">{{ number_format($result['score'], 2, ',', '') }}</span>
                            <span class="badge audit-band audit-band-{{ $bandSlug }}">{{ $result['band'] }}</span>
                        </div>
                        <div class="small text-muted">Média ponderada de {{ $result['rated'] }} de {{ $result['applicable'] }} indicadores avaliados.</div>
                    @else
                        <div class="fs-5 mt-1 text-muted">Ainda sem notas</div>
                        <div class="small text-muted">Marque a nota esperada em cada indicador.</div>
                    @endif
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="small text-uppercase text-muted">Documentos entregues</div>
                    <div class="fs-2 fw-bold mt-1">{{ $result['documents']['delivered'] }} <span class="fs-6 text-muted fw-normal">de {{ $result['documents']['total'] }}</span></div>
                    <div class="progress" style="height: .5rem" role="progressbar" aria-label="Documentos entregues" aria-valuenow="{{ $docsPct }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-success" style="width: {{ $docsPct }}%"></div>
                    </div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="small text-uppercase text-muted">Imprescindíveis</div>
                    @if ($result['alerts'])
                        <ul class="small text-danger mb-0 mt-1 ps-3">@foreach ($result['alerts'] as $alert)<li>{{ $alert }}</li>@endforeach</ul>
                    @else
                        <div class="text-success fw-semibold mt-1">✓ Nenhum imprescindível abaixo de 3</div>
                    @endif
                </div></div>
            </div>
        </div>
        <p class="small text-muted">
            Como é calculado: cada indicador (ou grupo, como 1.11) vale o seu peso. Nos grupos de SIM/NÃO, SIM conta 5 e NÃO conta 1, e o grupo
            fica com a média. O QUA-INT-08 não traz essa conversão, então o resultado é uma estimativa.
        </p>

        {{-- Dados da auditoria --}}
        <details class="audit-ind" @if (! $audit->auditor && $canEdit) open @endif>
            <summary><strong>Dados da auditoria</strong> <span class="small text-muted">{{ $audit->auditor ? 'Auditor: ' . $audit->auditor : '' }}{{ $audit->audit_date ? ' · ' . $audit->audit_date->format('d/m/Y') : '' }}</span></summary>
            <form method="POST" action="{{ route('audit.courses.header', $course) }}" class="p-3 row g-2">
                @csrf
                <fieldset class="contents" @disabled(! $canEdit) style="display: contents">
                    <div class="col-md-5"><label class="form-label small" for="auditor">Auditor(es)</label><input id="auditor" name="auditor" class="form-control form-control-sm" value="{{ old('auditor', $audit->auditor) }}"></div>
                    <div class="col-md-3"><label class="form-label small" for="audit_date">Data da auditoria</label><input id="audit_date" type="date" name="audit_date" class="form-control form-control-sm" value="{{ old('audit_date', $audit->audit_date?->format('Y-m-d')) }}"></div>
                    <div class="col-12"><label class="form-label small" for="audit_notes">Observações gerais</label><textarea id="audit_notes" name="notes" rows="2" class="form-control form-control-sm">{{ old('notes', $audit->notes) }}</textarea></div>
                    @if ($canEdit)<div class="col-12"><button class="btn btn-sm btn-primary">Salvar dados</button></div>@endif
                </fieldset>
            </form>
        </details>

        @foreach ($catalog->dimensions() as $dimension)
            <h3 class="h5 mt-4 mb-2">{{ $dimension['code'] }}. {{ $dimension['name'] }}</h3>
            @php $shownGroups = []; @endphp
            @foreach ($indicators->where('dimension', $dimension['code']) as $code => $ind)
                @if ($ind['group'] && ! in_array($ind['group'], $shownGroups, true))
                    @php $shownGroups[] = $ind['group']; $g = $groups[$ind['group']]; $gu = $units[$ind['group']]; @endphp
                    <div class="audit-group">
                        <strong>{{ $g['code'] }} {{ $g['name'] }}</strong>
                        <span class="badge text-bg-light border">Peso {{ $g['weight'] }}</span>
                        @if ($gu['score'] !== null)<span class="small text-muted">Nota do grupo: {{ number_format($gu['score'], 2, ',', '') }}{{ $gu['partial'] ? ' (parcial)' : '' }}</span>@endif
                    </div>
                @endif
                @php
                    $entry = $entries->get($code);
                    $anchor = 'ind-' . str_replace('.', '-', $code);
                    $docRows = collect($ind['documents'])->map(fn ($name) => ['name' => $name, 'key' => \App\Models\CourseAuditDocument::keyFor($code, $name)]);
                    $delivered = $docRows->filter(fn ($d) => ($documents->get($d['key'])?->status) === 'delivered')->count();
                    $naDocs = $docRows->filter(fn ($d) => ($documents->get($d['key'])?->status) === 'na')->count();
                    $isOpen = old('_indicator') === $code || session('success') === "Indicador {$code} salvo.";
                    $scoreLabel = $entry?->not_applicable ? 'Não se aplica' : ($entry?->score ? ($ind['kind'] === 'simnao' ? ($entry->score === 5 ? 'SIM' : 'NÃO') : 'Nota ' . $entry->score) : 'Sem nota');
                @endphp
                <details class="audit-ind" id="{{ $anchor }}" @if ($isOpen) open @endif>
                    <summary>
                        <span class="audit-code">{{ $code }}</span>
                        <span class="fw-semibold me-auto">{{ $ind['name'] }}</span>
                        @if ($ind['essential'])<span class="badge text-bg-danger-subtle text-danger-emphasis border border-danger-subtle">Imprescindível</span>@endif
                        @if ($ind['weight'])<span class="badge text-bg-light border">Peso {{ $ind['weight'] }}</span>@endif
                        <span class="badge {{ $entry?->not_applicable ? 'text-bg-secondary' : ($entry?->score ? ($entry->score >= 4 ? 'text-bg-success' : ($entry->score >= 3 ? 'text-bg-primary' : 'text-bg-danger')) : 'text-bg-light border') }}">{{ $scoreLabel }}</span>
                        @if ($docRows->isNotEmpty())
                            <span class="badge {{ $delivered + $naDocs === $docRows->count() ? 'text-bg-success' : 'text-bg-warning' }}">{{ $delivered }}/{{ $docRows->count() - $naDocs }} doc.</span>
                        @endif
                    </summary>
                    <form method="POST" action="{{ route('audit.courses.indicator', [$course, $code]) }}" enctype="multipart/form-data" class="p-3">
                        @csrf
                        <input type="hidden" name="_indicator" value="{{ $code }}">
                        <fieldset @disabled(! $canEdit)>
                            @if ($ind['applies'])<div class="alert alert-info py-1 px-2 small mb-2">{{ $ind['applies'] }}</div>@endif
                            @if ($ind['question'])<p class="small mb-2"><strong>{{ $ind['question'] }}</strong></p>@endif
                            @if ($ind['category'])<p class="small text-muted mb-2">Categoria no e-Ser: {{ $ind['category'] }}</p>@endif

                            <div class="small fw-semibold mb-1">Nota esperada</div>
                            @if ($ind['options'])
                                <div class="row g-2 mb-2" role="radiogroup" aria-label="Nota esperada em {{ $code }}">
                                    @foreach ($ind['options'] as $value => $text)
                                        @php $val = $ind['kind'] === 'simnao' ? ($value === 'SIM' ? 5 : 1) : (int) $value; $id = $anchor . '-s' . $val; @endphp
                                        <div class="{{ $ind['kind'] === 'simnao' ? 'col-md-6' : 'col-md' }}">
                                            <input type="radio" class="btn-check" name="score" id="{{ $id }}" value="{{ $val }}" @checked((int) old('score', $entry?->score) === $val)>
                                            <label class="audit-opt" for="{{ $id }}"><strong>{{ $ind['kind'] === 'simnao' ? $value : 'Nota ' . $value }}</strong>{{ $text }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="small text-muted mb-1">{{ \Illuminate\Support\Str::limit($ind['criteria'], 600) }}</p>
                                <div class="btn-group mb-2" role="radiogroup" aria-label="Nota esperada em {{ $code }}">
                                    @foreach ([1, 2, 3, 4, 5] as $val)
                                        <input type="radio" class="btn-check" name="score" id="{{ $anchor }}-s{{ $val }}" value="{{ $val }}" @checked((int) old('score', $entry?->score) === $val)>
                                        <label class="btn btn-sm btn-outline-primary" for="{{ $anchor }}-s{{ $val }}">{{ $val }}</label>
                                    @endforeach
                                </div>
                            @endif
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="not_applicable" value="1" id="{{ $anchor }}-na" @checked(old('not_applicable', $entry?->not_applicable))>
                                <label class="form-check-label small" for="{{ $anchor }}-na">Não se aplica a este curso (fica fora do cálculo)</label>
                            </div>

                            @if ($docRows->isNotEmpty())
                                <div class="small fw-semibold mb-1">Documentos exigidos</div>
                                <div class="table-responsive mb-2">
                                    <table class="table table-sm audit-docs mb-0">
                                        <thead><tr><th>Documento</th><th style="width: 9rem">Situação</th><th style="width: 22%">Arquivo ou link</th><th style="width: 11rem">Responsável</th><th style="width: 8.5rem">Prazo</th></tr></thead>
                                        <tbody>
                                            @foreach ($docRows as $d)
                                                @php $doc = $documents->get($d['key']); $k = $d['key']; @endphp
                                                <tr>
                                                    <td>{{ $d['name'] }}@if ($model = $catalog->template($d['name']))<br><a href="{{ $model['url'] }}" target="_blank" rel="noopener" class="small">⬇ Baixar modelo</a>@endif @if ($doc?->delivered_at)<br><small class="text-success">Entregue em {{ $doc->delivered_at->format('d/m/Y') }}</small>@endif</td>
                                                    <td>
                                                        <select name="docs[{{ $k }}][status]" class="form-select form-select-sm" aria-label="Situação de {{ $d['name'] }}">
                                                            @foreach ($statusLabels as $value => $label)
                                                                <option value="{{ $value }}" @selected(($doc?->status ?? 'pending') === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        @if ($doc?->file_path)
                                                            <a href="{{ route('audit.courses.download', [$course, $doc]) }}" class="d-block text-truncate small" style="max-width: 14rem">📎 {{ $doc->file_name }}</a>
                                                            @if ($canEdit)<label class="small text-muted"><input type="checkbox" name="docs[{{ $k }}][remove_file]" value="1"> remover</label>@endif
                                                        @endif
                                                        @if ($canEdit)<input type="file" name="files[{{ $k }}]" class="form-control form-control-sm mb-1" aria-label="Arquivo de {{ $d['name'] }}">@endif
                                                        <input type="text" name="docs[{{ $k }}][link]" class="form-control form-control-sm" data-strip-scheme placeholder="link do e-Ser (opcional)"
                                                            value="{{ $doc?->link ? preg_replace('#^https?://#', '', $doc->link) : '' }}" aria-label="Link de {{ $d['name'] }}">
                                                        @if ($doc?->link)<a href="{{ $doc->link }}" target="_blank" rel="noopener" class="small">abrir link</a>@endif
                                                    </td>
                                                    <td>
                                                        <select name="docs[{{ $k }}][responsible_id]" class="form-select form-select-sm" aria-label="Responsável por {{ $d['name'] }}">
                                                            <option value="">—</option>
                                                            @foreach ($people as $person)<option value="{{ $person->id }}" @selected($doc?->responsible_id === $person->id)>{{ $person->name }}</option>@endforeach
                                                        </select>
                                                    </td>
                                                    <td><input type="date" name="docs[{{ $k }}][due_date]" class="form-control form-control-sm" value="{{ $doc?->due_date?->format('Y-m-d') }}" aria-label="Prazo de {{ $d['name'] }}"></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="small text-muted">Anexar arquivo ou link marca o documento como entregue. Arquivos até 20 MB; o original continua sendo publicado no e-Ser.</p>
                            @endif
                            @if ($ind['support'])
                                <p class="small text-muted mb-2">Documentação de apoio: {{ implode('; ', $ind['support']) }}.</p>
                            @endif

                            <label class="form-label small fw-semibold" for="{{ $anchor }}-notes">Observações</label>
                            <textarea id="{{ $anchor }}-notes" name="notes" rows="2" class="form-control form-control-sm mb-2" placeholder="Pendências, combinados, evidências…">{{ old('notes', $entry?->notes) }}</textarea>
                            @if ($entry?->editor)<p class="small text-muted mb-2">Atualizado por {{ $entry->editor->name }} em {{ $entry->updated_at->format('d/m/Y H:i') }}.</p>@endif
                            @if ($canEdit)<button type="submit" class="btn btn-primary btn-sm">Salvar {{ $code }}</button>@endif
                        </fieldset>
                    </form>
                </details>
            @endforeach
        @endforeach
    </div>
    @include('audit.courses.styles')
@endsection
