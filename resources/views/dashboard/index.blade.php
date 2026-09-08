@extends('layouts.app')
@section('title', 'Painel • Inova7 Acadêmico')
@section('page-title', 'Painel da Coordenação')
@section('content')
    <section class="page-head">
        <div>
            <h2>{{ $term?->code ?? 'Semestre não cadastrado' }}</h2>
            <p>Planejamento docente, horários e documentos de auditoria em um único fluxo.</p>
        </div><a class="btn btn-primary" href="{{ route('planning.index') }}">Abrir planejamento</a>
    </section>
    <div class="row g-3 mb-4">
        @foreach ([['Cursos', $courses, 'estrutura multicurso'], ['Professores', $professors, 'cadastros ativos'], ['Ofertas', $offerings, 'no semestre selecionado'], ['Pendências', $pending, 'aguardando fechamento']] as [$label, $number, $note])
            <div class="col-6 col-xl-3">
                <article class="card card-soft stat-card">
                    <div class="card-body"><span class="stat-label">{{ $label }}</span>
                        <div class="stat-number">{{ $number }}</div><span class="stat-note">{{ $note }}</span>
                    </div>
                </article>
            </div>
        @endforeach
    </div>

    @if (Auth::check() && Auth::user()->role === 'admin')
        <div class="row g-3 mb-4">
            <div class="col-12">
                <article class="card border-0 shadow-sm"
                    style="background: linear-gradient(135deg, rgba(12,61,103,.97), rgba(18,100,163,.88), rgba(228,185,79,.82)); border: 1px solid rgba(255,255,255,.15)!important; color: white;">
                    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div>
                            <div
                                class="d-inline-flex align-items-center gap-2 mb-2 px-2 py-1 rounded-pill bg-white bg-opacity-10 border border-white border-opacity-25 small text-uppercase fw-semibold">
                                <span>Administração</span>
                            </div>
                            <h3 class="mb-1 mt-1 h5 text-white">Gerenciar catálogo acadêmico</h3>
                            <div class="small mb-0 text-white-50">Cursos, matrizes, disciplinas, professores e semestres.
                            </div>
                        </div>
                        <a class="btn btn-light text-primary fw-semibold px-3"
                            href="{{ route('admin.courses.index') }}">Abrir administração</a>
                    </div>
                </article>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-xl-7">
            <article class="card card-soft h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center"><span
                        class="section-title">Conflitos encontrados</span><span
                        class="badge {{ $conflicts->count() ? 'badge-soft-red' : 'badge-soft-green' }}">{{ $conflicts->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @forelse($conflicts->take(6) as $conflict)
                        <div class="p-3 border-bottom"><strong>{{ $conflict['message'] }}</strong>
                            <div class="small text-secondary mt-1">{{ $conflict['professor'] }} • {{ $conflict['left'] }} ×
                                {{ $conflict['right'] }}</div>
                    </div>@empty<div class="empty">Nenhum conflito detectado.</div>
                    @endforelse
                </div>
            </article>
        </div>
        <div class="col-xl-5">
            <article class="card card-soft h-100">
                <div class="card-header bg-white"><span class="section-title">Auditoria 1.12.1</span></div>
                <div class="card-body">
                    <p class="small text-secondary">Horários dos dois últimos semestres, templates e comprovantes de
                        divulgação.</p>
                    <div class="d-grid gap-2"><a class="btn btn-outline-primary" href="{{ route('audit.index') }}">Abrir
                            módulo de auditoria</a><a class="btn btn-outline-secondary"
                            href="{{ route('audit.documents.create') }}">Emitir CCG-FOR-01</a></div>
                </div>
            </article>
        </div>
    </div>
@endsection
