@extends('layouts.app')

@section('page-title', 'Usuários')

@section('content')
    <div class="container py-4">
        <div class="page-head">
            <div>
                <h2>Usuários</h2>
                <p>Gerenciamento de usuários do sistema.</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3 compact-search-card">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('admin.users.index') }}" class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="text" name="q" class="form-control form-control-sm"
                        value="{{ old('q', $query ?? '') }}" placeholder="Buscar por nome, e-mail ou matrícula">
                    <select name="role" class="form-control form-control-sm">
                        <option value="">Todos os papéis</option>
                        @foreach ($roleOptions as $value => $label)
                            <option value="{{ $value }}" {{ $roleFilter === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    @if (($query ?? '') !== '' || ($roleFilter ?? '') !== '')
                        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm card-soft admin-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle admin-matrix-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Matrícula</th>
                                <th>Papel</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $user)
                                <tr>
                                    <td><span class="matrix-name-cell">{{ $user->name }}</span></td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->registration_number ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-info">{{ $user->role_label }}</span>
                                    </td>
                                    <td>
                                        <span class="status-badge {{ $user->is_active ? 'ativa' : 'inativa' }}">
                                            {{ $user->is_active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-actions">
                                            <a href="{{ route('admin.users.edit', $user) }}"
                                                class="btn btn-sm btn-outline-primary">Editar Perfil</a>
                                            @if ($user->id !== auth()->id())
                                                <form action="{{ route('admin.users.destroy', $user) }}"
                                                    method="POST" class="d-inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Deseja remover este usuário?')">Excluir</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

