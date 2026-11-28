@extends('layouts.app')

@section('page-title', 'Visualizar Usuário')

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2>👁️ Visualizar Usuário</h2>
                <p class="text-muted mb-0">{{ $user->name }}</p>
            </div>
            <div class="btn-group" role="group">
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary">
                    ✏️ Editar
                </a>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                    ← Voltar
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Dados Pessoais -->
                <div class="card border-0 shadow-sm card-soft mb-4">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">👤 Dados Pessoais</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <small class="text-muted d-block mb-1">Nome Completo</small>
                                <strong>{{ $user->name }}</strong>
                            </div>
                            <div class="col-md-6 mb-4">
                                <small class="text-muted d-block mb-1">E-mail Institucional</small>
                                <strong>{{ $user->email }}</strong>
                            </div>
                            <div class="col-md-6 mb-0">
                                <small class="text-muted d-block mb-1">Matrícula / CPF</small>
                                @if ($user->registration_number)
                                    <strong><code>{{ $user->registration_number }}</code></strong>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                            <div class="col-md-6 mb-0">
                                <small class="text-muted d-block mb-1">ID do Sistema</small>
                                <strong><code>#{{ $user->id }}</code></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Perfil e Acesso -->
                <div class="card border-0 shadow-sm card-soft mb-4">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">🔐 Perfil e Acesso</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <small class="text-muted d-block mb-2">Perfil de Acesso</small>
                                @php
                                    $roles = \App\Models\User::roleOptions();
                                    $roleDescriptions = [
                                        'admin' => 'Acesso irrestrito ao sistema, gestão de usuários e configurações globais',
                                        'coordinator' => 'Gestão de matrizes curriculares, ofertas de turmas e alocação de professores',
                                        'teacher' => 'Preenchimento de disponibilidade de horários e visualização de grade alocada',
                                        'staff' => 'Consulta de turmas, salas, horários e suporte acadêmico',
                                        'student' => 'Consulta de grade horária e disciplinas da matriz',
                                    ];
                                    $roleColors = [
                                        'admin' => 'danger',
                                        'coordinator' => 'primary',
                                        'teacher' => 'info',
                                        'staff' => 'warning',
                                        'student' => 'success',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $roleColors[$user->role] ?? 'secondary' }} p-2">
                                    {{ $roles[$user->role] ?? ucfirst($user->role) }}
                                </span>
                                <small class="d-block mt-2 text-muted">
                                    {{ $roleDescriptions[$user->role] ?? 'Perfil não descrito' }}
                                </small>
                            </div>
                            <div class="col-md-6 mb-4">
                                <small class="text-muted d-block mb-2">Status de Acesso</small>
                                @if ($user->is_active)
                                    <span class="badge bg-success p-2">✓ ATIVO</span>
                                    <small class="d-block mt-2 text-muted">Usuário pode fazer login no sistema</small>
                                @else
                                    <span class="badge bg-secondary p-2">✗ INATIVO</span>
                                    <small class="d-block mt-2 text-muted">Usuário está bloqueado e não pode fazer login</small>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Primeira Entrada?</small>
                                @if ($user->must_change_password)
                                    <span class="badge bg-warning p-2">🔑 Deve trocar senha</span>
                                @else
                                    <span class="badge bg-info p-2">✓ Senha confirmada</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Histórico e Timestamps -->
                <div class="card border-0 shadow-sm card-soft">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">📅 Histórico</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <small class="text-muted d-block mb-1">Usuário Criado em</small>
                                <strong>{{ $user->created_at->format('d/m/Y') }}</strong>
                                <small class="text-muted d-block">{{ $user->created_at->format('H:i:s') }}</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <small class="text-muted d-block mb-1">Última Atualização</small>
                                <strong>{{ $user->updated_at->format('d/m/Y') }}</strong>
                                <small class="text-muted d-block">{{ $user->updated_at->format('H:i:s') }}</small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Último Acesso</small>
                                @if ($user->last_login_at)
                                    <strong>{{ $user->last_login_at->format('d/m/Y') }}</strong>
                                    <small class="text-muted d-block">{{ $user->last_login_at->format('H:i:s') }}</small>
                                @else
                                    <span class="text-muted">Nunca acessou o sistema</span>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Tempo desde Criação</small>
                                <strong>{{ $user->created_at->diffForHumans() }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar com Ações -->
            <div class="col-lg-4">
                <!-- Card de Ações -->
                <div class="card border-0 shadow-sm card-soft mb-3">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">⚡ Ações</h5>
                    </div>
                    <div class="card-body d-grid gap-2">
                        @can('impersonate', $user)
                            <!-- Entrar como o professor: preencher disponibilidade e disciplinas por ele -->
                            <form method="POST" action="{{ route('impersonate.start', $user) }}" class="d-grid">
                                @csrf
                                <button type="submit" class="btn btn-dark">👤 Entrar como este professor</button>
                            </form>
                            <div class="form-text mt-0 mb-1">Preencha a disponibilidade e as disciplinas por ele, sem a senha. Para sair, use "Voltar para minha conta" no topo.</div>
                        @endcan
                        <!-- Editar -->
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary">
                            ✏️ Editar Usuário
                        </a>

                        <!-- Redefinir Senha -->
                        <button type="button" class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                            🔑 Redefinir Senha
                        </button>

                        <!-- Alternar Status -->
                        @if (!($user->isAdminTi() && Auth::id() === $user->id))
                            <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn w-100" 
                                    style="background-color: {{ $user->is_active ? '#fd7e14' : '#198754' }}; color: white;">
                                    {{ $user->is_active ? '🔒 Desativar' : '🔓 Ativar' }}
                                </button>
                            </form>
                        @endif

                        <hr class="my-2">

                        <!-- Deletar -->
                        @if (!(Auth::id() === $user->id))
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" 
                                onsubmit="return confirm('⚠️ ATENÇÃO: Tem certeza que deseja DELETAR este usuário? Esta ação NÃO pode ser revertida!\n\nTodos os dados associados a este usuário serão perdidos.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger w-100">
                                    🗑️ Deletar Usuário
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <!-- Card de Resumo -->
                <div class="card border-0 shadow-sm card-soft mb-3">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">📊 Resumo</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Status Geral</small>
                                @if ($user->is_active && !$user->must_change_password)
                                    <span class="badge bg-success">Pronto para uso</span>
                                @elseif ($user->is_active && $user->must_change_password)
                                    <span class="badge bg-warning">Aguardando primeira entrada</span>
                                @else
                                    <span class="badge bg-secondary">Bloqueado</span>
                                @endif
                            </div>
                        </div>
                        <hr>
                        <div>
                            <small class="text-muted d-block mb-2">Permissões Incluídas</small>
                            @php
                                $permissions = match($user->role) {
                                    'admin' => ['Gerenciar usuários', 'Configurar sistema', 'Auditoria completa', 'Acesso irrestrito'],
                                    'coordinator' => ['Editar matrizes', 'Criar ofertas', 'Alocar professores', 'Publicar grade'],
                                    'teacher' => ['Marcar disponibilidade', 'Ver grade alocada', 'Responder sondagens'],
                                    'staff' => ['Consultar turmas', 'Ver horários', 'Atender discentes'],
                                    'student' => ['Ver grade', 'Ver disciplinas', 'Consultar salas'],
                                    default => ['Sem permissões']
                                };
                            @endphp
                            <ul class="small text-muted ps-3 mb-0">
                                @foreach ($permissions as $perm)
                                    <li>{{ $perm }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Card de Informações de Sistema -->
                <div class="card border-0 shadow-sm card-soft">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">ℹ️ Sistema</h5>
                    </div>
                    <div class="card-body">
                        <small class="text-muted">
                            ID: <code>{{ $user->id }}</code><br>
                            Email: <code>{{ $user->email }}</code><br>
                            <br>
                            <strong>Dica:</strong> Para fazer alterações, clique no botão "Editar Usuário" acima.
                        </small>
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
