@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">Cadastros</a></li>
                        <li class="breadcrumb-item"><a href="#">Semestres</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Novo semestre</li>
                    </ol>
                </nav>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="card-title mb-0 fw-bold">Novo Semestre Letivo</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="{{ route('semesters.store') }}" method="POST">
                            @csrf

                            <!-- Código do Semestre -->
                            <div class="mb-3">
                                <label for="code" class="form-label fw-semibold">Código do Semestre</label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror"
                                    id="code" name="code" value="{{ old('code') }}" placeholder="Ex: 2026.2"
                                    required>
                                <div class="form-text">Insira o código identificador no padrão do sistema.</div>
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Intervalo de Datas -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label fw-semibold">Data Inicial</label>
                                    <input type="date" class="form-control @error('start_date') is-invalid @enderror"
                                        id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                                    @error('start_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label fw-semibold">Data Final</label>
                                    <input type="date" class="form-control @error('end_date') is-invalid @enderror"
                                        id="end_date" name="end_date" value="{{ old('end_date') }}" required>
                                    @error('end_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Situação / Status -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold d-block">Situação do Período</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="status_planning"
                                        value="planning" checked>
                                    <label class="form-check-label" for="status_planning">Planejamento</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="status_open"
                                        value="open">
                                    <label class="form-check-label" for="status_open">Aberto</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="status_closed"
                                        value="closed">
                                    <label class="form-check-label" for="status_closed">Encerrado</label>
                                </div>
                            </div>

                            <hr class="text-muted mb-4">

                            <!-- Configurações Adicionais -->
                            <div class="mb-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="is_current" name="is_current"
                                        value="1">
                                    <label class="form-check-label fw-medium" for="is_current">
                                        Definir como o Semestre Vigente da Instituição
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="clone_previous"
                                        name="clone_previous" value="1">
                                    <label class="form-check-label fw-medium" for="clone_previous">
                                        Copiar ofertas e planejamento do período anterior
                                    </label>
                                </div>
                            </div>

                            <!-- Botões de Ação -->
                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="#" class="btn btn-light px-4">Cancelar</a>
                                <button type="submit" class="btn btn-primary px-4 fw-bold">Salvar Período</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
