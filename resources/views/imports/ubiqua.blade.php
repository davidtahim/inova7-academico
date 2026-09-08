@extends('layouts.app')

@section('title', 'Importação • Oferta Ubíqua')
@section('page-title', 'Importação de Oferta Ubíqua')

@section('content')
    <div class="container py-4">
        <div class="alert alert-warning border-0 shadow-sm" role="alert">
            <strong>Atenção:</strong> a importação é limitada ao grupo Uni7. Registros de outros grupos são ignorados.
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Importar planilha de Oferta Ubíqua</h5>
            </div>
            <div class="card-body">
                <form id="ubiqua-import-form" action="{{ route('imports.ubiqua.store') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="arquivo" class="form-label">Arquivo Excel/CSV</label>
                            <input id="arquivo" type="file" name="arquivo" class="form-control"
                                accept=".csv,.xlsx,.xls" required>
                        </div>

                        <div class="col-md-6">
                            <label for="academic_term_code" class="form-label">Semestre de destino</label>
                            <select id="academic_term_code" name="academic_term_code" class="form-select" required>
                                @foreach ($terms as $term)
                                    <option value="{{ $term->code }}" {{ $term->code === '2026.2' ? 'selected' : '' }}>
                                        {{ $term->code }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-3 text-muted small">
                        A importação da Oferta Ubíqua é por semestre. Ela carrega a base acadêmica do período selecionado
                        e pode ser reimportada em outro semestre sem sobrescrever dados de períodos anteriores.
                        A importação é limitada ao grupo Uni7. Registros de outros grupos são ignorados.
                        O administrador continua livre para cadastrar ou editar qualquer informação manualmente.
                    </div>

                    <div class="mt-2 text-muted small">
                        Arquivos esperados: CSV ou XLSX com colunas como CURSO, MATRIZ, DISCIPLINA, CÓDIGO, PERÍODO, TURMA,
                        TURNO, MODALIDADE e PROFESSOR.
                    </div>

                    <div id="import-progress" class="mt-4 d-none">
                        <div class="d-flex justify-content-between small text-muted mb-2">
                            <span>Status da importação</span>
                            <strong id="import-progress-value">0%</strong>
                        </div>
                        <div class="progress" style="height: 14px;">
                            <div id="import-progress-bar"
                                class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                                role="progressbar" style="width: 0%" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div id="import-progress-status" class="small text-muted mt-2">Aguardando início...</div>
                        <div id="import-progress-eta" class="small text-muted mt-1">Tempo restante: calculando...</div>
                    </div>

                    <button type="submit" id="import-submit-button" class="btn btn-primary mt-4">Importar dados</button>
                </form>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const form = document.getElementById('ubiqua-import-form');
                        const submitButton = document.getElementById('import-submit-button');
                        const progressWrap = document.getElementById('import-progress');
                        const progressBar = document.getElementById('import-progress-bar');
                        const progressValue = document.getElementById('import-progress-value');
                        const progressStatus = document.getElementById('import-progress-status');
                        const progressEta = document.getElementById('import-progress-eta');

                        if (!form || !submitButton || !progressWrap || !progressBar || !progressValue || !progressStatus || !
                            progressEta) {
                            return;
                        }

                        const formatRemaining = (seconds) => {
                            const value = Number(seconds) || 0;
                            if (value <= 0) {
                                return 'Tempo restante: concluindo...';
                            }

                            if (value < 60) {
                                return 'Tempo restante: aprox. ' + value + 's';
                            }

                            const minutes = Math.ceil(value / 60);
                            return 'Tempo restante: aprox. ' + minutes + ' min';
                        };

                        const pollProgress = () => {
                            fetch('{{ route('imports.ubiqua.progress') }}', {
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                })
                                .then(response => response.ok ? response.json() : null)
                                .then(data => {
                                    if (!data) {
                                        return;
                                    }

                                    const percent = Math.min(100, Math.max(0, Number(data.progress) || 0));
                                    progressBar.style.width = percent + '%';
                                    progressBar.setAttribute('aria-valuenow', String(percent));
                                    progressValue.textContent = percent + '%';
                                    progressStatus.textContent = data.status || 'Processando...';
                                    progressEta.textContent = formatRemaining(data.estimated_remaining_seconds);

                                    if (!data.finished) {
                                        window.setTimeout(pollProgress, 700);
                                    } else {
                                        progressEta.textContent = 'Tempo restante: concluído';
                                        window.setTimeout(() => {
                                            window.location.href = '{{ route('imports.ubiqua.index') }}';
                                        }, 1200);
                                    }
                                })
                                .catch(() => {
                                    progressStatus.textContent = 'Processando importação...';
                                    progressEta.textContent = 'Tempo restante: calculando...';
                                    window.setTimeout(pollProgress, 1000);
                                });
                        };

                        form.addEventListener('submit', function(event) {
                            event.preventDefault();

                            if (!form.querySelector('[name="arquivo"]').files.length) {
                                return;
                            }

                            progressWrap.classList.remove('d-none');
                            submitButton.disabled = true;
                            submitButton.textContent = 'Importando...';
                            progressBar.style.width = '0%';
                            progressValue.textContent = '0%';
                            progressStatus.textContent = 'Iniciando importação...';
                            progressEta.textContent = 'Tempo restante: calculando...';

                            const formData = new FormData(form);
                            fetch(form.action, {
                                    method: 'POST',
                                    body: formData,
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                                    }
                                })
                                .then(async response => {
                                    if (!response.ok) {
                                        const text = await response.text();
                                        throw new Error(text || 'Erro na importação');
                                    }

                                    return response.json().catch(() => ({
                                        success: true
                                    }));
                                })
                                .then((data) => {
                                    if (data && data.redirect) {
                                        window.location.href = data.redirect;
                                        return;
                                    }

                                    pollProgress();
                                })
                                .catch((error) => {
                                    console.error(error);
                                    progressStatus.textContent = 'Falha ao importar a planilha. Tente novamente.';
                                    submitButton.disabled = false;
                                    submitButton.textContent = 'Importar dados';
                                });
                        });
                    });
                </script>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-light">
                <h6 class="mb-0">Últimas cargas por semestre</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Semestre</th>
                                <th>Ofertas importadas</th>
                                <th>Última carga</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentLoads as $load)
                                <tr>
                                    <td>{{ $load->code }}</td>
                                    <td>{{ (int) $load->offering_count }}</td>
                                    <td>
                                        @if ($load->last_imported_at)
                                            {{ \Illuminate\Support\Carbon::parse($load->last_imported_at)->format('d/m/Y H:i') }}
                                        @else
                                            <span class="text-muted">Sem carga</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">Nenhuma carga registrada até o
                                        momento.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
