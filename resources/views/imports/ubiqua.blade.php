@extends('layouts.app')

@section('title', 'Importação • Oferta Ubíqua')
@section('page-title', 'Importação de Oferta Ubíqua')

@section('content')
    <div class="container py-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Importar planilha de Oferta Ubíqua</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('imports.ubiqua.store') }}" method="POST" enctype="multipart/form-data">
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
                        O administrador continua livre para cadastrar ou editar qualquer informação manualmente.
                    </div>

                    <div class="mt-2 text-muted small">
                        Arquivos esperados: CSV ou XLSX com colunas como CURSO, MATRIZ, DISCIPLINA, CÓDIGO, PERÍODO, TURMA,
                        TURNO, MODALIDADE e PROFESSOR.
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">Importar dados</button>
                </form>
            </div>
        </div>
    </div>
@endsection
