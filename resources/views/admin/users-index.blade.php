@extends('layouts.app')

@section('page-title', 'Gestão de Usuários e Acessos')

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2>👥 Gestão de Usuários</h2>
                <p class="text-muted mb-0">Administração de perfis, permissões e acessos do sistema</p>
            </div>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                + Novo Usuário
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Filtros e Busca -->
        <div class="card border-0 shadow-sm mb-4 card-soft">
            <div class="card-header admin-form-header">
                <h5 class="mb-0">🔍 Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" 
                            placeholder="Buscar por nome, email ou matrícula..."
                            value="{{ $searchTerm ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <select name="role" class="form-select">
                            <option value="">Todos os Perfis</option>
                            @foreach ($roles as $roleKey => $roleLabel)
                                <option value="{{ $roleKey }}" {{ ($selectedRole ?? '') === $roleKey ? 'selected' : '' }}>
                                    {{ $roleLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">Todos os Status</option>
                            <option value="active" {{ ($selectedStatus ?? '') === 'active' ? 'selected' : '' }}>✓ Ativo</option>
                            <option value="inactive" {{ ($selectedStatus ?? '') === 'inactive' ? 'selected' : '' }}>✗ Inativo</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-secondary">Limpar</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabela de Usuários -->
        <div class="card border-0 shadow-sm card-soft">
            <div class="card-header admin-form-header">
                <h5 class="mb-0">📋 {{ $users->total() }} Usuário(s)</h5>
            </div>
            <div class="card-body">
                @if ($users->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Matrícula</th>
                                    <th>Perfil</th>
                                    <th>Status</th>
                                    <th>Criado em</th>
                                    <th style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    <tr>
                                        <td>
                                            <strong>{{ $user->name }}</strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $user->email }}</small>
                                        </td>
                                        <td>
                                            @if ($user->registration_number)
                                                <code>{{ $user->registration_number }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
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
                                        </td>
                                        <td>
                                            @if ($user->is_active)
                                                <span class="badge bg-success">✓ Ativo</span>
                                            @else
                                                <span class="badge bg-secondary">✗ Inativo</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ $user->created_at->format('d/m/Y H:i') }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-primary" title="Visualizar">
                                                    👁️
                                                </a>
                                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-info" title="Editar">
                                                    ✏️
                                                </a>
                                                @if (!($user->isAdminTi() && Auth::id() === $user->id))
                                                    <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-warning btn-sm" title="Alternar status">
                                                            {{ $user->is_active ? '🔒' : '🔓' }}
                                                        </button>
                                                    </form>
                                                @endif
                                                @if (!(Auth::id() === $user->id))
                                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja deletar este usuário?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Deletar">
                                                            🗑️
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <div class="mt-4">
                        {{ $users->links('pagination::bootstrap-5') }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <p class="text-muted">Nenhum usuário encontrado com os filtros aplicados.</p>
                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
                            + Criar Novo Usuário
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Legenda de Perfis -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm card-soft">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">📌 Perfis de Acesso</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <span class="badge bg-danger me-2">Admin de TI</span>
                                    <small class="text-muted">Acesso irrestrito, gestão de usuários e sistema</small>
                                </div>
                                <div class="mb-3">
                                    <span class="badge bg-primary me-2">Coordenador</span>
                                    <small class="text-muted">Gestão de matrizes, ofertas e alocação</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <span class="badge bg-info me-2">Professor</span>
                                    <small class="text-muted">Disponibilidade e grade alocada</small>
                                </div>
                                <div class="mb-3">
                                    <span class="badge bg-warning me-2">Funcionário</span>
                                    <small class="text-muted">Consultas acadêmicas e suporte</small>
                                </div>
                                <div class="mb-0">
                                    <span class="badge bg-success me-2">Aluno</span>
                                    <small class="text-muted">Consulta de grade horária</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm card-soft">
                    <div class="card-header admin-form-header">
                        <h5 class="mb-0">⚙️ Resumo do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-6">
                                <div class="stat-box">
                                    <div class="stat-number">{{ $users->total() }}</div>
                                    <div class="stat-label">Total de Usuários</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-box">
                                    <div class="stat-number">{{ \App\Models\User::where('is_active', true)->count() }}</div>
                                    <div class="stat-label">Usuários Ativos</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .stat-box {
            padding: 1rem;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: bold;
            color: #0d6efd;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
    </style>
@endsection

