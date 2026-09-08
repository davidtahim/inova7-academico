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
                <article class="card card-soft h-100">
                    <div class="card-body">
                        <div class="small text-secondary">Cursos</div>
                        <div class="stat-number mt-2">{{ $coursesCount }}</div>
                        <div class="small text-secondary mt-2">Cadastros ativos</div>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0">
                        <a href="{{ route('admin.courses.index') }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </article>
            </div>

            <div class="col-sm-6 col-xl-3">
                <article class="card card-soft h-100">
                    <div class="card-body">
                        <div class="small text-secondary">Disciplinas</div>
                        <div class="stat-number mt-2">{{ $subjectsCount }}</div>
                        <div class="small text-secondary mt-2">Componentes cadastrados</div>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0">
                        <a href="{{ route('admin.subjects.index') }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </article>
            </div>

            <div class="col-sm-6 col-xl-3">
                <article class="card card-soft h-100">
                    <div class="card-body">
                        <div class="small text-secondary">Professores</div>
                        <div class="stat-number mt-2">{{ $professorsCount }}</div>
                        <div class="small text-secondary mt-2">Profissionais ativos</div>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0">
                        <a href="{{ route('admin.professors.index') }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </article>
            </div>

            <div class="col-sm-6 col-xl-3">
                <article class="card card-soft h-100">
                    <div class="card-body">
                        <div class="small text-secondary">Semestres</div>
                        <div class="stat-number mt-2">{{ $termsCount }}</div>
                        <div class="small text-secondary mt-2">Períodos cadastrados</div>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0">
                        <a href="{{ route('admin.semestres.index') }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                    </div>
                </article>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <article class="card card-soft h-100">
                    <div class="card-header bg-white border-0">
                        <span class="section-title">Cadastros</span>
                    </div>
                    <div class="card-body d-grid gap-2">
                        <a href="{{ route('admin.courses.index') }}" class="btn btn-outline-primary">Cursos</a>
                        <a href="{{ route('admin.matrices.index') }}" class="btn btn-outline-primary">Matrizes</a>
                        <a href="{{ route('admin.subjects.index') }}" class="btn btn-outline-primary">Disciplinas</a>
                        <a href="{{ route('admin.professors.index') }}" class="btn btn-outline-primary">Professores</a>
                        <a href="{{ route('admin.semestres.index') }}" class="btn btn-outline-primary">Semestres</a>
                    </div>
                </article>
            </div>

            <div class="col-lg-6">
                <article class="card card-soft h-100">
                    <div class="card-header bg-white border-0">
                        <span class="section-title">Ações rápidas</span>
                    </div>
                    <div class="card-body d-grid gap-2">
                        <a href="{{ route('admin.courses.create') }}" class="btn btn-primary">Novo curso</a>
                        <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary">Nova disciplina</a>
                        <a href="{{ route('admin.professors.create') }}" class="btn btn-primary">Novo professor</a>
                        <a href="{{ route('admin.semestres.create') }}" class="btn btn-primary">Novo semestre</a>
                    </div>
                </article>
            </div>
        </div>
    </div>
@endsection
