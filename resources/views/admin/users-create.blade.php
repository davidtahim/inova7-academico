@extends('layouts.app')

@section('page-title', 'Novo Usuário')

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2>➕ Novo Usuário</h2>
                <p class="text-muted mb-0">Cadastro de novo usuário no sistema</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                ← Voltar
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <strong>Erros encontrados:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm card-soft">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">📝 Dados do Usuário</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.users.store') }}" method="POST" novalidate>
                            @csrf

                            <!-- Nome -->
                            <div class="mb-3">
                                <label for="name" class="form-label fw-500">Nome Completo *</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                    id="name" name="name" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- E-mail -->
                            <div class="mb-3">
                                <label for="email" class="form-label fw-500">E-mail Institucional *</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                    id="email" name="email" value="{{ old('email') }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Use o e-mail institucional da organização</small>
                            </div>

                            <!-- CPF/Matrícula -->
                            <div class="mb-3">
                                <label for="registration_number" class="form-label fw-500">Matrícula / CPF</label>
                                <input type="text" class="form-control @error('registration_number') is-invalid @enderror" 
                                    id="registration_number" name="registration_number" value="{{ old('registration_number') }}">
                                @error('registration_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Código de matrícula ou CPF (sem mascara)</small>
                            </div>

                            <!-- Perfil -->
                            <div class="mb-3">
                                <label for="role" class="form-label fw-500">Perfil de Acesso *</label>
                                <select class="form-select @error('role') is-invalid @enderror" 
                                    id="role" name="role" required>
                                    <option value="">— Selecione um perfil —</option>
                                    @foreach (\App\Models\User::roleOptions() as $roleKey => $roleLabel)
                                        <option value="{{ $roleKey }}" {{ old('role') === $roleKey ? 'selected' : '' }}>
                                            {{ $roleLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">O perfil define as permissões no sistema</small>
                            </div>

                            {{-- Gestor de laboratórios: área administrada --}}
                            <div class="mb-3" id="lab-area-field">
                                <label for="lab_area" class="form-label fw-500">Área dos laboratórios *</label>
                                <select class="form-select @error('lab_area') is-invalid @enderror" id="lab_area" name="lab_area">
                                    <option value="">— Selecione a área —</option>
                                    @foreach (\App\Support\LabAreas::options() as $areaKey => $areaLabel)
                                        <option value="{{ $areaKey }}" @selected(old('lab_area', $user->lab_area ?? null) === $areaKey)>{{ $areaLabel }}</option>
                                    @endforeach
                                </select>
                                @error('lab_area')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">O gestor aprova as reservas e mantém o cadastro dos laboratórios desta área.</small>
                            </div>
                            <script>
                                (function () {
                                    const role = document.getElementById('role'), field = document.getElementById('lab-area-field');
                                    const toggle = () => { const on = role.value === 'lab_manager'; field.hidden = !on; document.getElementById('lab_area').required = on; };
                                    role.addEventListener('change', toggle); toggle();
                                })();
                            </script>

                            <!-- Senha -->
                            <div class="mb-3">
                                <label for="password" class="form-label fw-500">Senha Temporária *</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                    id="password" name="password" required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">
                                    Mínimo 8 caracteres com letras e números. 
                                    O usuário será solicitado a mudar na primeira entrada.
                                </small>
                            </div>

                            <!-- Confirmação de Senha -->
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label fw-500">Confirmar Senha *</label>
                                <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" 
                                    id="password_confirmation" name="password_confirmation" required>
                                @error('password_confirmation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Status -->
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" 
                                        name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        Usuário Ativo
                                    </label>
                                </div>
                                <small class="text-muted">Usuários inativos não conseguem fazer login</small>
                            </div>

                            <!-- Botões -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                                    Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    ✓ Criar Usuário
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm card-soft">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">📌 Dicas</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong class="d-block mb-2">🔐 Segurança</strong>
                            <small class="text-muted">
                                A senha deve ter no mínimo 8 caracteres e conter letras e números. 
                                O usuário será solicitado a mudar a senha ao fazer login pela primeira vez.
                            </small>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <strong class="d-block mb-2">👥 Perfil de Acesso</strong>
                            <ul class="small text-muted ps-3 mb-0">
                                <li><strong>Admin de TI:</strong> Acesso irrestrito</li>
                                <li><strong>Coordenador:</strong> Gestão curricular</li>
                                <li><strong>Professor:</strong> Disponibilidade/Grade</li>
                                <li><strong>Funcionário:</strong> Consultas acadêmicas</li>
                                <li><strong>Aluno:</strong> Acesso à grade</li>
                            </ul>
                        </div>
                        <hr>
                        <div>
                            <strong class="d-block mb-2">📧 E-mail</strong>
                            <small class="text-muted">
                                Sempre use endereço institucional. Este será o login do usuário.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm card-soft mt-3">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">⚠️ Lembrete</h5>
                    </div>
                    <div class="card-body">
                        <small class="text-muted">
                            Após criar o usuário, você poderá:
                        </small>
                        <ul class="small text-muted ps-3 mb-0">
                            <li>Editar dados e perfil</li>
                            <li>Redefinir senha</li>
                            <li>Ativar/inativar acesso</li>
                            <li>Visualizar histórico</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
