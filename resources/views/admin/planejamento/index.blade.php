@extends('layouts.app')

@section('page-title', 'Administração')

@section('content')
    <div class="container py-4">
        <div class="page-head">
            <div>
                <h2>Planejamento administrativo</h2>
                <p>Controle rápido do cadastro acadêmico e dos principais registros do sistema.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <article class="card card-soft h-100 admin-panel-stat">
                    <div class="card-body py-3">
                        <div class="small text-secondary">Cursos</div>
                        <div class="stat-number mt-2">{{ $coursesCount }}</div>
                        <div class="small text-secondary mt-2">Cadastros ativos</div>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0 pb-3">
                        <a href="{{ route('admin.courses.index') }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </article>
            </div>

            <div class="col-sm-6 col-xl-3">
                <article class="card card-soft h-100 admin-panel-stat">
                    <div class="card-body py-3">
                        <div class="small text-secondary">Disciplinas</div>
                        <div class="stat-number mt-2">{{ $subjectsCount }}</div>
                        <div class="small text-secondary mt-2">Componentes cadastrados</div>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0 pb-3">
                        <a href="{{ route('admin.subjects.index') }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </article>
            </div>

            <div class="col-sm-6 col-xl-3">
                <article class="card card-soft h-100 admin-panel-stat">
                    <div class="card-body py-3">
                        <div class="small text-secondary">Professores</div>
                        <div class="stat-number mt-2">{{ $professorsCount }}</div>
                        <div class="small text-secondary mt-2">Profissionais ativos</div>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0 pb-3">
                        <a href="{{ route('admin.professors.index') }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </article>
            </div>

            <div class="col-sm-6 col-xl-3">
                <article class="card card-soft h-100 admin-panel-stat">
                    <div class="card-body py-3">
                        <div class="small text-secondary">Semestres</div>
                        <div class="stat-number mt-2">{{ $termsCount }}</div>
                        <div class="small text-secondary mt-2">Períodos cadastrados</div>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0 pb-3">
                        <a href="{{ route('admin.semestres.index') }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </article>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <article class="card card-soft h-100 admin-panel-card">
                    <div class="card-header bg-white border-0 pb-2">
                        <span class="section-title">Cadastros</span>
                    </div>
                    <div class="card-body d-grid gap-2 py-2">
                        <a href="{{ route('admin.courses.index') }}"
                            class="btn btn-outline-primary admin-panel-btn">Cursos</a>
                        <a href="{{ route('admin.matrices.index') }}"
                            class="btn btn-outline-primary admin-panel-btn">Matrizes</a>
                        <a href="{{ route('admin.import-scopes.index') }}"
                            class="btn btn-outline-primary admin-panel-btn">Matrizes permitidas</a>
                        <a href="{{ route('admin.subjects.index') }}"
                            class="btn btn-outline-primary admin-panel-btn">Disciplinas</a>
                        <a href="{{ route('admin.professors.index') }}"
                            class="btn btn-outline-primary admin-panel-btn">Professores</a>
                        <a href="{{ route('admin.semestres.index') }}"
                            class="btn btn-outline-primary admin-panel-btn">Semestres</a>
                    </div>
                </article>
            </div>

            <div class="col-lg-6">
                <article class="card card-soft h-100 admin-panel-card">
                    <div class="card-header bg-white border-0 pb-2">
                        <span class="section-title">Ações rápidas</span>
                    </div>
                    <div class="card-body d-grid gap-2 py-2">
                        <a href="{{ route('admin.courses.create') }}" class="btn btn-primary admin-panel-btn">Novo
                            curso</a>
                        <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary admin-panel-btn">Nova
                            disciplina</a>
                        <a href="{{ route('admin.professors.create') }}" class="btn btn-primary admin-panel-btn">Novo
                            professor</a>
                        <a href="{{ route('admin.semestres.create') }}" class="btn btn-primary admin-panel-btn">Novo
                            semestre</a>
                    </div>
                </article>
            </div>
        </div>
    </div>
@endsection
