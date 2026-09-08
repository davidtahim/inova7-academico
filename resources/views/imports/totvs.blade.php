@extends('layouts.app')

@section('title', 'Importação • Base TOTVS')
@section('page-title', 'Importação de base TOTVS')

@section('content')
    <div class="container py-4">
        <div class="alert alert-info border-0 shadow-sm" role="alert">
            <strong>Fluxo separado:</strong> esta importação é para a base acadêmica do TOTVS/disciplinas e não substitui a
            Oferta Ubíqua do semestre.
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Importar base TOTVS / Disciplinas</h5>
            </div>
            <div class="card-body">
                <form id="totvs-import-form" action="{{ route('imports.totvs.store') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="arquivo" class="form-label">Arquivo Excel/CSV da base TOTVS</label>
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
                        Use esta base para importar disciplinas, matrizes, cursos e códigos acadêmicos do TOTVS. A oferta
                        Ubíqua continua sendo um processo separado e independente.
                    </div>

                    <div class="mt-3 text-muted small">
                        Arquivos esperados: CSV ou XLSX com colunas como Código da Turma, Cód Disciplina, Nome Disciplina,
                        Curso, Matriz e Turno. O período do importador é selecionado na tela e não precisa constar na
                        planilha.
                    </div>

                    <div class="mt-3">
                        <div class="small fw-semibold text-secondary mb-2">Colunas esperadas</div>
                        <div class="border rounded-3 bg-light p-3">
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge text-bg-light border">Código da Turma</span>
                                <span class="badge text-bg-light border">Cód Disciplina</span>
                                <span class="badge text-bg-light border">Nome Disciplina</span>
                                <span class="badge text-bg-light border">Curso</span>
                                <span class="badge text-bg-light border">Matriz</span>
                                <span class="badge text-bg-light border">Turno</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-4">
                        <button type="submit" id="totvs-import-submit"
                            class="btn btn-primary px-4 py-3 fw-semibold rounded-3 import-submit-button">
                            <span class="btn-label">Importar base TOTVS</span>
                        </button>
                    </div>

                    <div id="totvs-import-progress" class="mt-4 d-none p-3 rounded-4 border import-progress-shell">
                        <div class="d-flex justify-content-between align-items-center small text-secondary mb-2">
                            <span class="fw-semibold">Status da importação</span>
                            <strong id="totvs-import-progress-value" class="fs-6 text-dark">0%</strong>
                        </div>
                        <div class="progress rounded-pill"
                            style="height: 18px; background-color: #e9ecef; overflow: hidden;">
                            <div id="totvs-import-progress-bar"
                                class="progress-bar progress-bar-striped progress-bar-animated bg-primary rounded-pill"
                                role="progressbar" style="width: 0%" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div id="totvs-import-progress-status"
                            class="small text-muted mt-3 d-flex align-items-center gap-2">
                            <span class="spinner-border spinner-border-sm text-primary" role="status"
                                aria-hidden="true"></span>
                            <span>Aguardando início...</span>
                        </div>
                        <div id="totvs-import-progress-eta" class="small text-muted mt-1">Tempo restante: calculando...
                        </div>
                    </div>
                </form>

                <form id="totvs-reset-form" action="{{ route('imports.totvs.reset') }}" method="POST"
                    class="d-inline-block mt-3">
                    @csrf
                    <input type="hidden" id="totvs-reset-academic-term-code" name="academic_term_code"
                        value="{{ $terms->first()?->code ?? '' }}">
                    <button type="submit" class="btn btn-outline-danger px-3 py-2 fw-semibold rounded-3"
                        onclick="return confirm('Deseja apagar toda a base TOTVS importada deste semestre? Esta ação não pode ser desfeita.')">
                        Zerar base TOTVS
                    </button>
                </form>
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

    <style>
        .import-progress-shell {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.75), rgba(233, 236, 239, 0.85));
            border-color: #dfe3e8 !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .import-submit-button {
            background: linear-gradient(135deg, #5aa9f8 0%, #4a9ae8 100%);
            border: 0;
            box-shadow: 0 8px 18px rgba(74, 154, 232, 0.28);
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
        }

        .import-submit-button:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(74, 154, 232, 0.32);
            filter: brightness(1.02);
        }

        .import-submit-button:disabled {
            opacity: 0.9;
            cursor: wait;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('totvs-import-form');
            const submitButton = document.getElementById('totvs-import-submit');
            const progressWrap = document.getElementById('totvs-import-progress');
            const progressBar = document.getElementById('totvs-import-progress-bar');
            const progressValue = document.getElementById('totvs-import-progress-value');
            const progressStatus = document.getElementById('totvs-import-progress-status');
            const progressEta = document.getElementById('totvs-import-progress-eta');
            const semesterSelect = document.getElementById('academic_term_code');
            const resetSemesterInput = document.getElementById('totvs-reset-academic-term-code');

            if (semesterSelect && resetSemesterInput) {
                const syncResetSemester = () => {
                    resetSemesterInput.value = semesterSelect.value;
                };

                semesterSelect.addEventListener('change', syncResetSemester);
                syncResetSemester();
            }

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

            const setLoadingState = (label) => {
                submitButton.disabled = true;
                submitButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm text-light me-2" role="status" aria-hidden="true"></span><span class="btn-label">' +
                    label + '</span>';
            };

            const setIdleState = () => {
                submitButton.disabled = false;
                submitButton.innerHTML = '<span class="btn-label">Importar base TOTVS</span>';
            };

            const setStatus = (message, showSpinner = true) => {
                const spinner = progressStatus.querySelector('.spinner-border');
                if (spinner) {
                    spinner.style.visibility = showSpinner ? 'visible' : 'hidden';
                }
                const content = progressStatus.querySelector('span:last-child');
                if (content) {
                    content.textContent = message;
                }
            };

            let importPollingActive = false;

            const pollProgress = () => {
                if (!importPollingActive) {
                    return;
                }

                fetch('{{ route('imports.totvs.progress') }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(async response => {
                        if (!response.ok) {
                            throw new Error('Progress response not ok');
                        }

                        return response.json().catch(() => null);
                    })
                    .then(data => {
                        if (!data) {
                            setStatus('Processando importação...', true);
                            progressEta.textContent = 'Tempo restante: calculando...';
                            window.setTimeout(pollProgress, 600);
                            return;
                        }

                        const percent = Math.min(100, Math.max(0, Number(data.progress) || 0));
                        progressBar.style.width = percent + '%';
                        progressBar.setAttribute('aria-valuenow', String(percent));
                        progressValue.textContent = percent + '%';
                        setStatus(data.status || 'Processando importação...', true);
                        progressEta.textContent = formatRemaining(data.estimated_remaining_seconds);

                        if (!data.finished) {
                            window.setTimeout(pollProgress, 400);
                        } else {
                            setStatus('Importação concluída', false);
                            progressEta.textContent = 'Tempo restante: concluído';
                            window.setTimeout(() => {
                                window.location.href = '{{ route('imports.totvs.index') }}';
                            }, 1200);
                        }
                    })
                    .catch(() => {
                        if (!importPollingActive) {
                            return;
                        }

                        setStatus('Processando importação...', true);
                        progressEta.textContent = 'Tempo restante: calculando...';
                        window.setTimeout(pollProgress, 600);
                    });
            };

            form.addEventListener('submit', function(event) {
                event.preventDefault();

                if (!form.querySelector('[name="arquivo"]').files.length) {
                    return;
                }

                importPollingActive = true;
                progressWrap.classList.remove('d-none');
                progressBar.style.width = '0%';
                progressValue.textContent = '0%';
                setStatus('Iniciando importação...', true);
                progressEta.textContent = 'Tempo restante: calculando...';
                setLoadingState('Importando...');

                pollProgress();

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
                            const payload = await response.json().catch(() => null);
                            const message = payload?.errors?.arquivo?.[0] || payload?.message ||
                                'Falha ao importar a base TOTVS.';
                            throw new Error(message);
                        }

                        return response.json().catch(() => ({
                            success: true
                        }));
                    })
                    .then((data) => {
                        if (data && data.redirect) {
                            importPollingActive = false;
                            window.location.href = data.redirect;
                            return;
                        }

                        pollProgress();
                    })
                    .catch((error) => {
                        importPollingActive = false;
                        const message = error?.message || 'Falha ao importar a base TOTVS.';
                        setStatus(message, false);
                        setIdleState();
                    });
            });
        });
    </script>
@endsection
