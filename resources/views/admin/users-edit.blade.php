@extends('layouts.app')

@section('page-title', 'Editar Usuário')

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2>✏️ Editar Usuário</h2>
                <p class="text-muted mb-0">{{ $user->name }}</p>
            </div>
            <div class="btn-group" role="group">
                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-info">
                    👁️ Visualizar
                </a>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                    ← Voltar
                </a>
            </div>
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

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm card-soft mb-3">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">📝 Dados do Usuário</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.users.update', $user) }}" method="POST" novalidate>
                            @csrf
                            @method('PUT')

                            <!-- Nome -->
                            <div class="mb-3">
                                <label for="name" class="form-label fw-500">Nome Completo *</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                    id="name" name="name" value="{{ old('name', $user->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- E-mail -->
                            <div class="mb-3">
                                <label for="email" class="form-label fw-500">E-mail Institucional *</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                    id="email" name="email" value="{{ old('email', $user->email) }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Use o e-mail institucional da organização</small>
                            </div>

                            <!-- CPF/Matrícula -->
                            <div class="mb-3">
                                <label for="registration_number" class="form-label fw-500">Matrícula / CPF</label>
                                <input type="text" class="form-control @error('registration_number') is-invalid @enderror" 
                                    id="registration_number" name="registration_number" value="{{ old('registration_number', $user->registration_number) }}">
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
                                    @foreach (\App\Models\User::roleOptions() as $roleKey => $roleLabel)
                                        <option value="{{ $roleKey }}" {{ old('role', $user->role) === $roleKey ? 'selected' : '' }}>
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

                            <!-- Status -->
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" 
                                        name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
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
                                    ✓ Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Card de Ações Rápidas -->
                <div class="card border-0 shadow-sm card-soft mb-3">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">⚡ Ações Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <!-- Redefinir Senha -->
                        <button type="button" class="btn btn-warning w-100 mb-2" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                            🔑 Redefinir Senha
                        </button>

                        <!-- Alternar Status -->
                        @if (!($user->isAdminTi() && Auth::id() === $user->id))
                            <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-sm w-100" 
                                    style="background-color: {{ $user->is_active ? '#fd7e14' : '#198754' }}; color: white;">
                                    {{ $user->is_active ? '🔒 Desativar Usuário' : '🔓 Ativar Usuário' }}
                                </button>
                            </form>
                        @endif

                        <hr>

                        <!-- Deletar Usuário -->
                        @if (!(Auth::id() === $user->id))
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" 
                                onsubmit="return confirm('⚠️ Tem certeza que deseja DELETAR este usuário? Esta ação NÃO pode ser revertida!');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger w-100">
                                    🗑️ Deletar Usuário
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <!-- Card de Informações -->
                <div class="card border-0 shadow-sm card-soft">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">ℹ️ Informações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">ID do Usuário</small>
                            <code>{{ $user->id }}</code>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Criado em</small>
                            {{ $user->created_at->format('d/m/Y H:i:s') }}
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Última atualização</small>
                            {{ $user->updated_at->format('d/m/Y H:i:s') }}
                        </div>
                        @if ($user->last_login_at)
                            <div class="mb-0">
                                <small class="text-muted d-block">Último acesso</small>
                                {{ $user->last_login_at->format('d/m/Y H:i:s') }}
                            </div>
                        @else
                            <div class="mb-0">
                                <small class="text-muted d-block">Último acesso</small>
                                <span class="badge bg-secondary">Nunca acessou</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Card de Status -->
                <div class="card border-0 shadow-sm card-soft mt-3">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">🎯 Status Atual</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small class="text-muted d-block mb-1">Perfil</small>
                            @php
                                $roles = \App\Models\User::roleOptions();
                                $roleColors = [
                                    'admin' => 'danger',
                                    'coordinator' => 'primary',
                                    'teacher' => 'info',
                                    'staff' => 'warning',
                                    'student' => 'success',
                                ];
                            @endphp
                            <span class="badge bg-{{ $roleColors[$user->role] ?? 'secondary' }}">
                                {{ $roles[$user->role] ?? ucfirst($user->role) }}
                            </span>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted d-block mb-1">Acesso</small>
                            @if ($user->is_active)
                                <span class="badge bg-success">✓ Ativo - Pode fazer login</span>
                            @else
                                <span class="badge bg-secondary">✗ Inativo - Bloqueado</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Redefinir Senha -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header admin-form-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">🔑 Redefinir Senha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.users.reset-password', $user) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p class="text-muted mb-3">
                            Digite a nova senha temporária para <strong>{{ $user->name }}</strong>. 
                            O usuário será solicitado a mudar na próxima entrada.
                        </p>

                        <!-- Senha -->
                        <div class="mb-3">
                            <label for="resetPassword" class="form-label fw-500">Nova Senha *</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                id="resetPassword" name="password" required>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted d-block mt-2">
                                Mínimo 8 caracteres com letras e números
                            </small>
                        </div>

                        <!-- Confirmar Senha -->
                        <div class="mb-3">
                            <label for="resetPasswordConfirmation" class="form-label fw-500">Confirmar Senha *</label>
                            <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" 
                                id="resetPasswordConfirmation" name="password_confirmation" required>
                            @error('password_confirmation')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <small>
                                <strong>⚠️ Lembrete:</strong> Após redefinir, o usuário receberá a mensagem 
                                de que deve alterar a senha no próximo login.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">🔑 Redefinir Senha</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
