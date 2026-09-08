@extends('layouts.app')

@section('title', 'Importação • Professores')
@section('page-title', 'Importação de professores')

@section('content')
    <div class="container py-4">
        <div class="alert alert-info border-0 shadow-sm" role="alert">
            <strong>Fluxo:</strong> esta importação atualiza os dados cadastrais dos professores com matrícula, chapa e
            Lattes.
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Importar base de professores</h5>
            </div>
            <div class="card-body">
                <form id="professors-import-form" action="{{ route('imports.professors.store') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="arquivo" class="form-label">Arquivo Excel/CSV dos professores</label>
                            <input id="arquivo" type="file" name="arquivo" class="form-control"
                                accept=".csv,.xlsx,.xls" required>
                        </div>
                    </div>

                    <div class="mt-3 text-muted small">
                        Arquivos esperados: CSV ou XLSX com colunas como Professor, Matrícula, Chapa e Lattes.
                    </div>

                    <div class="mt-3">
                        <div class="small fw-semibold text-secondary mb-2">Colunas esperadas</div>
                        <div class="border rounded-3 bg-light p-3">
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge text-bg-light border">Professor</span>
                                <span class="badge text-bg-light border">Matrícula</span>
                                <span class="badge text-bg-light border">Chapa</span>
                                <span class="badge text-bg-light border">Lattes</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-4">
                        <button type="submit" id="professors-import-submit"
                            class="btn btn-primary px-4 py-3 fw-semibold rounded-3 import-submit-button">
                            <span class="btn-label">Importar professores</span>
                        </button>
                    </div>

                    <div id="professors-import-progress" class="mt-4 d-none p-3 rounded-4 border import-progress-shell">
                        <div class="d-flex justify-content-between align-items-center small text-secondary mb-2">
                            <span class="fw-semibold">Status da importação</span>
                            <strong id="professors-import-progress-value" class="fs-6 text-dark">0%</strong>
                        </div>
                        <div class="progress rounded-pill"
                            style="height: 18px; background-color: #e9ecef; overflow: hidden;">
                            <div id="professors-import-progress-bar"
                                class="progress-bar progress-bar-striped progress-bar-animated bg-primary rounded-pill"
                                role="progressbar" style="width: 0%" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div id="professors-import-progress-status"
                            class="small text-muted mt-3 d-flex align-items-center gap-2">
                            <span class="spinner-border spinner-border-sm text-primary" role="status"
                                aria-hidden="true"></span>
                            <span>Aguardando início...</span>
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
            const form = document.getElementById('professors-import-form');
            const submitButton = document.getElementById('professors-import-submit');
            const progressWrap = document.getElementById('professors-import-progress');
            const progressBar = document.getElementById('professors-import-progress-bar');
            const progressValue = document.getElementById('professors-import-progress-value');
            const progressStatus = document.getElementById('professors-import-progress-status');

            if (!form || !submitButton || !progressWrap || !progressBar || !progressValue || !progressStatus) {
                return;
            }

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

            form.addEventListener('submit', function(event) {
                event.preventDefault();

                if (!form.querySelector('[name="arquivo"]').files.length) {
                    return;
                }

                progressWrap.classList.remove('d-none');
                submitButton.disabled = true;
                submitButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm text-light me-2" role="status" aria-hidden="true"></span><span class="btn-label">Importando...</span>';
                progressBar.style.width = '0%';
                progressValue.textContent = '0%';
                setStatus('Enviando arquivo...', true);

                const formData = new FormData(form);
                fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin'
                    })
                    .then(async response => {
                        const text = await response.text();
                        if (response.redirected || response.ok) {
                            window.location.href = response.url ||
                                '{{ route('imports.professors.index') }}';
                            return;
                        }

                        throw new Error(text || 'Erro ao importar professores');
                    })
                    .catch(() => {
                        setStatus('Erro na importação. Verifique o arquivo e tente novamente.', false);
                        submitButton.disabled = false;
                        submitButton.innerHTML = '<span class="btn-label">Importar professores</span>';
                    });
            });
        });
    </script>
@endsection
