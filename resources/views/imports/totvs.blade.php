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
                        Arquivos esperados: CSV ou XLSX com colunas como CURSO, PERÍODO, DISCIPLINA, MATRIZ, CÓDIGO,
                        MODALIDADE, CÓDIGO_DA_TURMA e TURNO.
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
