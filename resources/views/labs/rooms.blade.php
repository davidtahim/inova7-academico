@extends('layouts.app')

@section('title', 'Cadastro de laboratórios • Inova7 Acadêmico')
@section('page-title', 'Cadastro de laboratórios')

@section('content')
    <div class="container-fluid py-3 px-0">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-3">
            <div>
                <h2 class="h4 mb-1">Laboratórios de informática</h2>
                <p class="text-muted small mb-0">
                    A grade horária só aloca disciplinas práticas em laboratórios <strong>ativos</strong> com computadores suficientes
                    para a turma e com os softwares que a disciplina exige.
                </p>
            </div>
            <a href="{{ route('labs.index') }}" class="btn btn-outline-secondary">Ver reservas</a>
        </div>

        <div class="d-grid gap-2">
            @forelse ($labs as $lab)
                <form method="POST" action="{{ route('labs.rooms.update', $lab) }}" class="card border-0 shadow-sm">
                    @csrf
                    @method('PUT')
                    <div class="card-body row g-2 align-items-end">
                        <div class="col-md-2">
                            <div class="small text-muted">Código</div>
                            <strong>{{ $lab->code }}</strong>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small" for="n-{{ $lab->id }}">Nome</label>
                            <input id="n-{{ $lab->id }}" name="name" class="form-control form-control-sm" value="{{ $lab->name }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small" for="c-{{ $lab->id }}">Computadores</label>
                            <input id="c-{{ $lab->id }}" type="number" name="computers" min="0" class="form-control form-control-sm" value="{{ $lab->computers }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small" for="s-{{ $lab->id }}">Softwares homologados</label>
                            <textarea id="s-{{ $lab->id }}" name="softwares" rows="1" class="form-control form-control-sm" placeholder="VS Code, Python, MySQL Workbench">{{ $lab->softwares }}</textarea>
                        </div>
                        <div class="col-md-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" value="1" id="a-{{ $lab->id }}" @checked($lab->active)>
                                <label class="form-check-label small" for="a-{{ $lab->id }}">Ativo</label>
                            </div>
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                        </div>
                    </div>
                </form>
            @empty
                <div class="alert alert-info">Nenhum laboratório cadastrado ainda.</div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('labs.rooms.store') }}" class="card border-0 shadow-sm mt-3">
            @csrf
            <div class="card-body row g-2 align-items-end">
                <div class="col-12"><h3 class="h6 mb-0">Novo laboratório</h3></div>
                <div class="col-md-2">
                    <label class="form-label small" for="new-code">Código *</label>
                    <input id="new-code" name="code" class="form-control form-control-sm" required placeholder="Li55" value="{{ old('code') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small" for="new-name">Nome</label>
                    <input id="new-name" name="name" class="form-control form-control-sm" value="{{ old('name') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small" for="new-pcs">Computadores</label>
                    <input id="new-pcs" type="number" name="computers" min="0" class="form-control form-control-sm" value="{{ old('computers') }}">
                </div>
                <div class="col-md-5">
                    <label class="form-label small" for="new-sw">Softwares homologados</label>
                    <input id="new-sw" name="softwares" class="form-control form-control-sm" value="{{ old('softwares') }}">
                </div>
                <div class="col-md-1 text-end">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Cadastrar</button>
                </div>
            </div>
        </form>
    </div>
@endsection
