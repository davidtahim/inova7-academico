@extends('layouts.app')

@section('page-title', isset($term->id) ? 'Editar semestre' : 'Novo semestre')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm admin-form-card">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">{{ isset($term->id) ? 'Editar semestre' : 'Novo semestre' }}</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST"
                            action="{{ isset($term->id) ? route('admin.semestres.update', $term) : route('admin.semestres.store') }}">
                            @csrf
                            @if (isset($term->id))
                                @method('PUT')
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Código</label>
                                <input type="text" name="code" class="form-control"
                                    value="{{ old('code', $term->code ?? '') }}" required>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Data de início</label>
                                    <input type="date" name="starts_at" class="form-control"
                                        value="{{ old('starts_at', optional($term->starts_at)->format('Y-m-d')) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Data de fim</label>
                                    <input type="date" name="ends_at" class="form-control"
                                        value="{{ old('ends_at', optional($term->ends_at)->format('Y-m-d')) }}">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    @foreach (['planning', 'active', 'closed'] as $status)
                                        <option value="{{ $status }}"
                                            {{ old('status', $term->status ?? 'planning') == $status ? 'selected' : '' }}>
                                            {{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('admin.semestres.index') }}"
                                    class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
