@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Configurações</a></li>
                    <li class="breadcrumb-item"><a href="#">Usuários</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Novo usuário</li>
                </ol>
            </nav>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0 fw-bold">Novo Usuário do Sistema</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('users.store') }}" method="POST">
                        @csrf

                        <!-- Nome Completo -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Nome Completo</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" 
                                   placeholder="Digite o nome completo do usuário" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- E-mail e Matrícula -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">E-mail Institucional</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                       id="email" name="email" value="{{ old('email') }}" 
                                       placeholder="exemplo@uni7.edu.br" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="registration_number" class="form-label fw-semibold">Matrícula / RA (Opcional)</label>
                                <input type="text" class="form-control @error('registration_number') is-invalid @enderror" 
                                       id="registration_number" name="registration_number" value="{{ old('registration_number') }}" 
                                       placeholder="Ex: 56015806">
                                @error('registration_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Perfil e Ativação -->
                        <div class="row mb-4 align-items-end">
                            <div class="col-md-6">
                                <label for="role" class="form-label fw-semibold">Perfil de Acesso</label>
                                <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                                    <option value="" disabled selected>Selecione o Perfil</option>
                                    <option value="student">Aluno</option>
                                    <option value="teacher">Professor</option>
                                    <option value="coordinator">Coordenador</option>
                                    <option value="staff">Funcionário (Secretaria/CRA)</option>
                                    <option value="admin">Administrador de TI</option>
                                </select>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 pb-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="is_active">Permitir login imediatamente</label>
                                </div>
                            </div>
                        </div>

                        <hr class="text-muted mb-4">

                        <!-- Senhas -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="password" class="form-label fw-semibold">Senha Temporária</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                       id="password" name="password" required>
                                <div class="form-text">Mínimo de 8 caracteres, com letras e números.</div>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="password_confirmation" class="form-label fw-semibold">Confirmar Senha</label>
                                <input type="password" class="form-control" 
                                       id="password_confirmation" name="password_confirmation" required>
                            </div>
                        </div>

                        <!-- Botões de Ação -->
                        <div class="d-flex justify-content-end gap-2">
                            <a href="#" class="btn btn-light px-4">Cancelar</a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold">Salvar Acesso</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection