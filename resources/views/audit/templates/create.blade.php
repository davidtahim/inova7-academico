@extends('layouts.app')
@section('title','Novo template • Auditoria')
@section('page-title','Carregar template auditável')
@section('content')
<section class="page-head"><div><h2>Novo template</h2><p>O arquivo HTML deve preservar o layout oficial e usar marcações <code>@{{campo}}</code>.</p></div></section>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form class="card card-soft" method="post" action="{{ route('audit.templates.store') }}" enctype="multipart/form-data"><div class="card-body row g-3">@csrf
 <div class="col-md-6"><label class="form-label">Item da auditoria</label><select class="form-select" name="audit_item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->code }} — {{ $item->name }}</option>@endforeach</select></div>
 <div class="col-md-3"><label class="form-label">Código</label><input class="form-control" name="code" value="CCG-FOR-01" required></div><div class="col-md-3"><label class="form-label">Versão</label><input class="form-control" name="version" value="08" required></div>
 <div class="col-md-6"><label class="form-label">Nome</label><input class="form-control" name="name" value="Horário de Aula" required></div><div class="col-md-3"><label class="form-label">Turno</label><select class="form-select" name="shift"><option>MANHÃ</option><option>TARDE</option><option>NOITE</option></select></div><div class="col-md-3"><label class="form-label">Data de aprovação</label><input type="date" class="form-control" name="approved_at"></div>
 <div class="col-md-6"><label class="form-label">Aprovado por</label><input class="form-control" name="approved_by"></div><div class="col-md-6"><label class="form-label">Arquivo HTML</label><input type="file" class="form-control" name="template" accept=".html,.htm" required></div>
 <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_current" value="1" checked id="is_current"><label class="form-check-label" for="is_current">Publicar como template vigente</label></div>
</div><div class="card-footer bg-white text-end"><a class="btn btn-light" href="{{ route('audit.index') }}">Cancelar</a><button class="btn btn-primary">Carregar e validar</button></div></form>
@endsection
