{{--
    Grade semanal de uma turma. Variáveis: $letters (TimeBlocks::forShift), $weekdays,
    $entries ([weekday][letter] => [{subject, professor, room, offering_id}]), $compact (opcional),
    $editable (opcional: cada aula vira botão data-edit, que abre o modal partials.allocation-modal).
--}}
@php
    $palette = ['#e7f1ff', '#e8f6ee', '#fff4e0', '#f3e8ff', '#ffe8ec', '#e6f7f8', '#f1f3f5', '#fdf2d6'];
    $colorFor = function ($offeringId) use ($palette) { return $palette[$offeringId % count($palette)]; };
@endphp
<div class="table-responsive">
    <table class="table table-bordered weekly-grid mb-0 {{ ($compact ?? false) ? 'weekly-grid-compact' : '' }}">
        <thead>
            <tr>
                <th scope="col" class="weekly-grid-time">Aula</th>
                @foreach ($weekdays as $weekday => $label)
                    <th scope="col" class="text-center">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($letters as $letter => $slot)
                <tr>
                    <th scope="row" class="weekly-grid-time">
                        <span class="weekly-grid-letter">{{ $letter }}</span>
                        <small>{{ $slot['start'] }}–{{ $slot['end'] }}</small>
                    </th>
                    @foreach ($weekdays as $weekday => $label)
                        @php
                            $items = $entries[$weekday][$letter] ?? [];
                            $clash = count(collect($items)->pluck('subject')->unique()) > 1;
                        @endphp
                        <td class="{{ $clash ? 'weekly-grid-clash' : '' }}">
                            @foreach ($items as $item)
                                @if ($editable ?? false)
                                <button type="button" class="weekly-grid-item weekly-grid-edit" data-edit="{{ $item['offering_id'] }}" aria-haspopup="dialog"
                                    title="Editar {{ $item['subject'] }}" style="background: {{ $colorFor($item['offering_id']) }}">
                                @else
                                <div class="weekly-grid-item" style="background: {{ $colorFor($item['offering_id']) }}">
                                @endif
                                    <strong>{{ $item['subject'] }}</strong>
                                    @unless ($compact ?? false)
                                        <span>{{ $item['professor'] ?? 'Professor a definir' }}</span>
                                    @endunless
                                    @if ($item['room'])
                                        <span class="weekly-grid-room">{{ $item['room'] }}</span>
                                    @endif
                                @if ($editable ?? false)</button>@else</div>@endif
                            @endforeach
                            @if ($clash)
                                <span class="visually-hidden">Choque de horário</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@once
    <style>
        .weekly-grid { table-layout: fixed; min-width: 640px; font-size: .8rem; }
        .weekly-grid th, .weekly-grid td { vertical-align: top; padding: .35rem; }
        .weekly-grid thead th { background: #f6f8fb; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #5b6b7d; }
        .weekly-grid-time { width: 88px; background: #f6f8fb; white-space: nowrap; }
        .weekly-grid-time small { display: block; color: #6c757d; font-weight: 400; }
        .weekly-grid-letter { font-weight: 700; color: #0c3d67; }
        .weekly-grid-item { border-radius: 6px; padding: .3rem .4rem; line-height: 1.25; }
        .weekly-grid-item + .weekly-grid-item { margin-top: .25rem; }
        .weekly-grid-item strong { display: block; font-size: .72rem; }
        .weekly-grid-item span { display: block; color: #495057; font-size: .7rem; }
        .weekly-grid-room { font-weight: 600; color: #0c3d67 !important; }
        .weekly-grid-edit { display: block; width: 100%; text-align: left; border: 0; color: inherit; cursor: pointer; }
        .weekly-grid-edit:hover { box-shadow: inset 0 0 0 2px #1264a3; }
        .weekly-grid-edit:focus-visible { outline: 3px solid #0c3d67; outline-offset: 1px; }
        .weekly-grid-clash { background: #fff1f1; box-shadow: inset 0 0 0 2px #dc3545; }
        .weekly-grid-compact { min-width: 520px; }
    </style>
@endonce
