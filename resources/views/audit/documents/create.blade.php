@extends('layouts.app')
@section('title','Emitir CCG-FOR-01')
@section('page-title','Emitir CCG-FOR-01')
@section('content')
<section class="page-head"><div><h2>Horário oficial para divulgação</h2><p>O PDF será preenchido a partir do template vigente e da grade cadastrada.</p></div></section>
<form class="card card-soft" method="post" action="{{ route('audit.documents.store') }}"><div class="card-body row g-3">@csrf
 <div class="col-md-6"><label class="form-label">Template</label><select class="form-select" name="template_id" required>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->code }} V.{{ $template->version }} — {{ $template->shift }}</option>@endforeach</select></div>
 <div class="col-md-3"><label class="form-label">Semestre</label><select class="form-select" name="term_id" required>@foreach($terms as $term)<option value="{{ $term->id }}">{{ $term->code }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Curso</label><select class="form-select" name="course_id" required>@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->name }}</option>@endforeach</select></div>
 <div class="col-md-4"><label class="form-label">Código da turma</label><input class="form-control" name="class_code" placeholder="CSE0280102NMA" required></div><div class="col-md-2"><label class="form-label">Período</label><input type="number" class="form-control" name="period" min="1" max="20" required></div><div class="col-md-3"><label class="form-label">Turno</label><select class="form-select" name="shift"><option>MANHÃ</option><option>TARDE</option><option>NOITE</option></select></div><div class="col-md-3"><label class="form-label">Bloco</label><input class="form-control" name="block" value="4º ANDAR"></div>
</div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Validar e emitir PDF</button></div></form>
@endsection
