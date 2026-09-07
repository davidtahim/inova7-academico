<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>CCG-FOR-02 - Disponibilidade de horário do professor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
            margin: 28px;
        }

        .document {
            border: 1px solid #d1d5db;
            padding: 22px 26px;
            background: #ffffff;
        }

        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }

        .header-title {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 0.04em;
            margin: 0;
        }

        .header-subtitle {
            font-size: 11px;
            color: #475569;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .meta td {
            padding: 7px 0;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .meta .label {
            width: 180px;
            font-weight: bold;
            color: #334155;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin: 18px 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f8fafc;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #334155;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            background: #e0f2fe;
            color: #075985;
        }

        .badge-preferred {
            background: #dcfce7;
            color: #166534;
        }

        .badge-unavailable {
            background: #fee2e2;
            color: #991b1b;
        }

        .footer {
            margin-top: 26px;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
            font-size: 10px;
            color: #64748b;
        }
    </style>
</head>

<body>
    <div class="document">
        <div class="header">
            <div class="header-title">CCG-FOR-02</div>
            <div class="header-subtitle">Disponibilidade de horário do professor</div>
        </div>

        <table class="meta">
            <tr>
                <td class="label">Professor:</td>
                <td>{{ $professor?->name ?? $user->name }}</td>
            </tr>
            <tr>
                <td class="label">E-mail:</td>
                <td>{{ $user->email }}</td>
            </tr>
            <tr>
                <td class="label">Semestre / período:</td>
                <td>{{ $term?->code ?? 'Sem semestre informado' }}</td>
            </tr>
        </table>

        @if ($slots->isEmpty())
            <p style="margin: 20px 0; color: #475569;">Não há registros de disponibilidade cadastrados para o semestre
                atual.</p>
        @else
            @foreach ($grouped as $dayNumber => $entries)
                <div class="section-title">{{ $weekdays[$dayNumber] ?? 'Dia ' . $dayNumber }}</div>
                <table>
                    <thead>
                        <tr>
                            <th>Início</th>
                            <th>Fim</th>
                            <th>Preferência</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td>{{ $entry->starts_at }}</td>
                                <td>{{ $entry->ends_at }}</td>
                                <td>
                                    @if ($entry->preference === 'preferred')
                                        <span class="badge badge-preferred">Preferencial</span>
                                    @elseif ($entry->preference === 'unavailable')
                                        <span class="badge badge-unavailable">Indisponível</span>
                                    @else
                                        <span class="badge">Disponível</span>
                                    @endif
                                </td>
                                <td>{{ $entry->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endif

        <div class="footer">
            Documento gerado pelo sistema INOVA7 Acadêmico em {{ now()->format('d/m/Y') }}.
        </div>
    </div>
</body>

</html>
