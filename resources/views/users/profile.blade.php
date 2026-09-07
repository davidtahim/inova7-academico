@extends('layouts.app')

@section('page-title', 'Perfil')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Perfil do usuário</h5>
                        <span class="badge bg-light text-primary">{{ $user->role_label }}</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar avatar-lg d-inline-flex align-items-center justify-content-center rounded-circle bg-secondary text-white fw-bold"
                                    style="width:72px;height:72px;font-size:1.5rem;">
                                    {{ strtoupper(Str::of($user->name)->split('/\s+/')->map(fn($part) => Str::substr($part, 0, 1))->take(2)->implode('')) }}
                                </div>
                                <div>
                                    <h3 class="mb-1">{{ $user->name }}</h3>
                                    <p class="text-muted mb-0">{{ $user->email }}</p>
                                </div>
                            </div>
                            <a href="{{ route('profile.edit') }}" class="btn btn-outline-primary btn-sm">Editar perfil</a>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-uppercase text-secondary">Matrícula</small>
                                    <div class="fw-semibold mt-1">{{ $user->registration_number ?? 'Não informado' }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-uppercase text-secondary">Perfil</small>
                                    <div class="fw-semibold mt-1">{{ $user->role_label }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-uppercase text-secondary">Status</small>
                                    <div class="fw-semibold mt-1">{{ $user->is_active ? 'Ativo' : 'Inativo' }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-uppercase text-secondary">Acesso</small>
                                    <div class="fw-semibold mt-1">
                                        {{ $user->role === 'admin' ? 'Administrador' : ($user->role === 'coordinator' ? 'Coordenador' : ($user->role === 'teacher' ? 'Professor' : ($user->role === 'student' ? 'Aluno' : 'Funcionário'))) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div>
                            <h5 class="mb-3">Perfis disponíveis</h5>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($roles as $key => $label)
                                    <span
                                        class="badge rounded-pill {{ $user->role === $key ? 'bg-primary' : 'bg-light text-dark border' }} px-3 py-2">
                                        {{ $label }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
