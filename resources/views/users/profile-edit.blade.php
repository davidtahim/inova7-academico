@extends('layouts.app')

@section('page-title', $user->role === 'teacher' ? 'Meu perfil' : 'Editar perfil')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="{{ $user->role === 'teacher' ? 'col-12' : 'col-xl-9' }}">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0 fw-bold">{{ $user->role === 'teacher' ? 'Meu perfil' : 'Editar perfil' }}</h5>
                    </div>
                    <div class="card-body p-4">
                        @if ($user->role === 'teacher')
                            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')

                                <div
                                    class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                                    <div>
                                        <div class="text-uppercase text-secondary small fw-semibold">Semestre ativo</div>
                                        <h5 class="mb-0 mt-1">{{ $term?->code ?? 'Semestre não selecionado' }}</h5>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary border">Configuração do
                                        professor</span>
                                </div>

                                <div class="alert alert-info border mb-4">
                                    Defina sua nova senha e escolha as disciplinas que deseja manter vinculadas ao seu
                                    perfil para o semestre ativo.
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <div class="border rounded p-3 h-100 bg-light-subtle">
                                            <small class="text-uppercase text-secondary">Nome</small>
                                            <div class="fw-semibold mt-1">{{ $user->name }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-3 h-100 bg-light-subtle">
                                            <small class="text-uppercase text-secondary">E-mail</small>
                                            <div class="fw-semibold mt-1">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-3 h-100 bg-light-subtle">
                                            <small class="text-uppercase text-secondary">Matrícula</small>
                                            <div class="fw-semibold mt-1">
                                                {{ $user->registration_number ?? 'Não informado' }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label for="password" class="form-label fw-semibold">Nova senha</label>
                                        <input type="password" class="form-control @error('password') is-invalid @enderror"
                                            id="password" name="password" autocomplete="new-password">
                                        <div class="form-text">Mínimo 8 caracteres com letras e números.</div>
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="password_confirmation" class="form-label fw-semibold">Confirmar nova
                                            senha</label>
                                        <input type="password" class="form-control" id="password_confirmation"
                                            name="password_confirmation" autocomplete="new-password">
                                    </div>
                                </div>

                                <div class="border rounded p-3 mb-4" id="disciplinas">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <h6 class="fw-bold mb-0">Minhas disciplinas</h6>
                                        <span
                                            class="badge text-bg-primary rounded-pill">{{ count($selectedSubjectIds) }}</span>
                                    </div>

                                    @if ($allSubjects->isEmpty())
                                        <div class="alert alert-secondary mb-0">Nenhuma disciplina cadastrada no catálogo.
                                        </div>
                                    @else
                                        <div class="row g-2">
                                            @foreach ($allSubjects as $subject)
                                                <div class="col-md-6">
                                                    <label
                                                        class="d-flex align-items-center gap-2 border rounded p-2 h-100 mb-0 cursor-pointer">
                                                        <input type="checkbox" name="subjects[]" value="{{ $subject->id }}"
                                                            {{ in_array($subject->id, $selectedSubjectIds, true) ? 'checked' : '' }}>
                                                        <span>
                                                            <span class="fw-semibold d-block">{{ $subject->name }}</span>
                                                            <small class="text-secondary">{{ $subject->code }}</small>
                                                        </span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <a href="{{ route('profile') }}" class="btn btn-light">Cancelar</a>
                                    <button type="submit" class="btn btn-primary">Salvar perfil</button>
                                </div>
                            </form>

                            <section class="mt-4 pt-4 border-top" id="disponibilidade" aria-labelledby="disponibilidade-title">
                                <div class="d-flex flex-wrap gap-2 align-items-start justify-content-between mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-1" id="disponibilidade-title">Disponibilidade de horários — {{ $term?->code ?? 'semestre não selecionado' }}</h6>
                                        <small class="text-muted">
                                            Preencha a grade do formulário CCG-FOR-02. Cada clique salva automaticamente;
                                            a coordenação usa esta grade para alocar suas turmas.
                                        </small>
                                    </div>
                                    @if ($hasAvailabilityGrid)
                                        <a href="{{ route('teacher.availability.pdf') }}" class="btn btn-sm btn-outline-success">Gerar CCG-FOR-02 (PDF)</a>
                                    @endif
                                </div>

                                @if (! $hasAvailabilityGrid)
                                    <div class="alert alert-warning mb-0">
                                        Seu usuário ainda não está vinculado a um cadastro docente, por isso a grade de
                                        disponibilidade não pode ser exibida. Procure a coordenação.
                                    </div>
                                @elseif (! $term)
                                    <div class="alert alert-secondary mb-0">Nenhum semestre letivo cadastrado.</div>
                                @else
                                    @include('partials.availability-grid')
                                @endif
                            </section>
                        @else
                            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="photo" class="form-label fw-semibold">Foto do usuário</label>
                                        <input type="file" class="form-control @error('photo') is-invalid @enderror"
                                            id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                                        <div class="form-text">JPG, PNG ou WEBP até 2MB.</div>
                                        @error('photo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror

                                        @if ($user->photo_path)
                                            <div class="mt-3">
                                                <img src="{{ Storage::disk('public')->url($user->photo_path) }}"
                                                    alt="Foto atual" class="img-thumbnail"
                                                    style="width:96px;height:96px;object-fit:cover;">
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        <label for="name" class="form-label fw-semibold">Nome completo</label>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                                            id="name" name="name" value="{{ old('name', $user->name) }}"
                                            required>
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="email" class="form-label fw-semibold">E-mail</label>
                                        <input type="email" class="form-control @error('email') is-invalid @enderror"
                                            id="email" name="email" value="{{ old('email', $user->email) }}"
                                            required>
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="registration_number" class="form-label fw-semibold">Matrícula /
                                            RA</label>
                                        <input type="text"
                                            class="form-control @error('registration_number') is-invalid @enderror"
                                            id="registration_number" name="registration_number"
                                            value="{{ old('registration_number', $user->registration_number) }}">
                                        @error('registration_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="role" class="form-label fw-semibold">Perfil</label>
                                        <input type="text" class="form-control" id="role"
                                            value="{{ $user->role_label }}" disabled readonly>
                                        <div class="form-text">O perfil de acesso é definido pelo Administrador de TI.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="password" class="form-label fw-semibold">Nova senha</label>
                                        <input type="password"
                                            class="form-control @error('password') is-invalid @enderror" id="password"
                                            name="password">
                                        <div class="form-text">Opcional. Mínimo 8 caracteres com letras e números.</div>
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="password_confirmation" class="form-label fw-semibold">Confirmar nova
                                            senha</label>
                                        <input type="password" class="form-control" id="password_confirmation"
                                            name="password_confirmation">
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <a href="{{ route('profile') }}" class="btn btn-light">Cancelar</a>
                                    <button type="submit" class="btn btn-primary">Salvar alterações</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
