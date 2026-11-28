<!DOCTYPE html>
<html lang="pt-BR">
{{--
    CCG-FOR-02 — Disponibilidade de horário de professor (modelo oficial, versão 06).
    Variáveis: $professorName, $termCode, $coordination, $courseLabel, $shifts, $marks, $subjects,
    $hasPreferred, $logo (data URI ou null), $generatedAt.
--}}
<head>
    <meta charset="UTF-8">
    <title>CCG-FOR-02 - Disponibilidade de horário de professor</title>
    <style>
        @page { margin: 26px 34px 30px; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 10.5px; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .head td { border: 1px solid #000; padding: 2px 5px; font-weight: bold; }
        .head .logo { width: 92px; text-align: center; padding: 2px; }
        .head .logo img { height: 34px; }
        .head .title { text-align: center; font-size: 12px; }
        .head .code { width: 118px; text-align: center; font-size: 9.5px; }
        .head .small { font-size: 8.5px; }
        .field { margin-top: 16px; font-size: 11px; font-weight: bold; }
        .field .label { display: inline-block; width: 175px; white-space: nowrap; }
        .field .value { display: inline-block; border-bottom: 1px solid #000; min-width: 360px; padding: 0 4px 1px; }
        .grid { margin-top: 16px; }
        .grid th, .grid td { border: 1px solid #000; text-align: center; padding: 1px 3px; height: 14px; }
        .grid th { font-size: 11px; }
        .grid td.time { font-weight: bold; text-align: left; white-space: nowrap; width: 100px; }
        .grid .shift td { background: #d9d9d9; font-weight: bold; font-size: 11px; }
        .grid .mark { font-weight: bold; font-size: 11px; }
        .obs { margin-top: 12px; font-weight: bold; }
        .obs span { font-weight: normal; }
        .subtitle { margin-top: 18px; text-align: center; font-weight: bold; font-size: 11.5px; text-decoration: underline; }
        .intro { margin-top: 14px; font-size: 11px; }
        .subjects { margin-top: 6px; }
        .subjects td { border: 1px solid #000; padding: 2px 6px; height: 15px; font-size: 11.5px; }
        .subjects td.n { width: 44px; text-align: center; font-size: 10.5px; }
        .signature { margin: 54px auto 0; width: 250px; border-top: 1px solid #000; text-align: center; padding-top: 3px; font-size: 10.5px; }
        .generated { margin-top: 14px; text-align: center; font-size: 8px; color: #555; }
    </style>
</head>

<body>
    <table class="head">
        <tr>
            <td class="logo" rowspan="2">@if ($logo)<img src="{{ $logo }}" alt="">@endif</td>
            <td class="title" colspan="3" rowspan="2">DISPONIBILIDADE DE HORÁRIO DE PROFESSOR</td>
            <td class="code">CÓDIGO:</td>
        </tr>
        <tr><td class="code">CCG-FOR-02</td></tr>
        <tr class="small">
            <td colspan="2">APROVADO POR: SUPERINTENDENTE ACADÊMICA</td>
            <td style="width: 44px">DATA:</td>
            <td style="width: 78px">05/02/2020</td>
            <td>VERSÃO: &nbsp; 06</td>
        </tr>
    </table>

    <div class="field"><span class="label">COORDENAÇÃO DO CURSO:</span><span class="value">{{ $coordination }}</span></div>
    <div class="field"><span class="label">SEMESTRE LETIVO:</span><span class="value" style="min-width: 120px">{{ $termCode }}</span></div>
    <div class="field"><span class="label">PROFESSOR:</span><span class="value">{{ $professorName }}</span></div>

    <table class="grid">
        <thead>
            <tr>
                <th>Horário</th>
                @foreach (['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'] as $day)
                    <th>{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($shifts as $shiftName => $rows)
                <tr class="shift"><td colspan="7">{{ $shiftName }}</td></tr>
                @foreach ($rows as [$label, $start])
                    <tr>
                        <td class="time">{{ $label }}</td>
                        @foreach (range(1, 6) as $day)
                            @php $pref = $marks[$day . '|' . $start] ?? null; @endphp
                            <td class="mark">{{ $pref ? ($pref === 'preferred' ? 'x*' : 'x') : '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="obs">OBS: Marque com um X os horários disponíveis.@if ($hasPreferred) <span>(* horário de preferência do professor)</span>@endif</div>

    <div class="subtitle">DISPONIBILIDADE DE DISCIPLINAS</div>
    <div class="intro">Indique as disciplinas da Graduação em <strong>{{ $courseLabel }}</strong>, que você se considera habilitado para lecionar:</div>
    <table class="subjects">
        @foreach ($subjects as $i => $subject)
            <tr><td class="n">{{ $i + 1 }}</td><td>{{ $subject }}</td></tr>
        @endforeach
    </table>

    <div class="signature">Assinatura do Professor</div>
    <div class="generated">Gerado pelo Inova7 Acadêmico em {{ $generatedAt }}.</div>
</body>

</html>
