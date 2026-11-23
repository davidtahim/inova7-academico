@extends('layouts.app')

@section('page-title', $user->exists ? 'Editar Usuário: ' . $user->name : 'Novo Usuário')

@section('content')
    <div class="container py-4">
        <div class="page-head mb-4">
            <div>
                <h2>{{ $user->exists ? 'Editar Perfil' : 'Novo Usuário' }}</h2>
                <p>{{ $user->exists ? 'Atualize os dados do usuário.' : 'Cadastre um novo usuário no sistema.' }}</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Voltar</a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form action="{{ route('admin.users.update', $user) }}" method="POST" class="needs-validation">
                    @csrf
                    @method('PUT')

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Nome *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                                value="{{ old('name', $user->name ?? '') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">E-mail *</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                                value="{{ old('email', $user->email ?? '') }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="registration_number" class="form-label">Matrícula</label>
                            <input type="text" class="form-control @error('registration_number') is-invalid @enderror" id="registration_number" name="registration_number"
                                value="{{ old('registration_number', $user->registration_number ?? '') }}">
                            @error('registration_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="role" class="form-label">Papel *</label>
                            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                                <option value="">Selecione um papel</option>
                                @foreach ($roleOptions as $value => $label)
                                    <option value="{{ $value }}" {{ old('role', $user->role ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">
                                Senha
                                <small class="text-muted">(deixe em branco para manter a senha atual)</small>
                            </label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Mínimo de 8 caracteres com letras e números</small>
                        </div>

                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">Confirmar Senha</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                    {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Usuário ativo
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            Atualizar Usuário
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

