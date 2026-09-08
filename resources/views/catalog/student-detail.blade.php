@extends('layouts.app')

@section('page-title', 'Histórico acadêmico')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Histórico acadêmico</h2>
                <p class="text-muted mb-0">{{ $student->name }} • {{ $student->registration_number ?? 'Sem matrícula' }}</p>
            </div>
            <a href="{{ route('catalog.students') }}" class="btn btn-outline-secondary">Voltar</a>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="small text-uppercase text-secondary">E-mail</div>
                        <div>{{ $student->email }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-uppercase text-secondary">Matrícula</div>
                        <div>{{ $student->registration_number ?? 'Não informada' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-uppercase text-secondary">Status</div>
                        <div>{{ $student->is_active ? 'Ativo' : 'Inativo' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 fw-semibold">Disciplinas e registros</div>
            <div class="card-body p-0">
                @if ($records->isEmpty())
                    <div class="p-4 text-muted">Nenhum registro acadêmico encontrado para este aluno.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Semestre</th>
                                    <th>Curso</th>
                                    <th>Disciplina</th>
                                    <th>Status</th>
                                    <th>Nota</th>
                                    <th>Créditos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($records as $record)
                                    <tr>
                                        <td>{{ $record->academicTerm?->code ?? '—' }}</td>
                                        <td>{{ $record->course?->name ?? '—' }}</td>
                                        <td>{{ $record->subject?->name ?? '—' }}</td>
                                        <td>
                                            <span class="badge text-bg-light border text-capitalize">
                                                {{ $record->status === 'aprovado' ? 'Aprovado' : ($record->status === 'reprovado' ? 'Reprovado' : ($record->status === 'trancado' ? 'Trancado' : 'Cursando')) }}
                                            </span>
                                        </td>
                                        <td>{{ $record->grade ?? '—' }}</td>
                                        <td>{{ $record->credits }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
