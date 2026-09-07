@extends('layouts.app')

@section('page-title', 'Editar perfil')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0 fw-bold">Editar perfil</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="{{ route('profile.update') }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label fw-semibold">Nome completo</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                                        id="name" name="name" value="{{ old('name', $user->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-semibold">E-mail</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                                        id="email" name="email" value="{{ old('email', $user->email) }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="registration_number" class="form-label fw-semibold">Matrícula / RA</label>
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
                                    <select class="form-select @error('role') is-invalid @enderror" id="role"
                                        name="role" required>
                                        @foreach ($roles as $key => $label)
                                            <option value="{{ $key }}"
                                                {{ old('role', $user->role) === $key ? 'selected' : '' }}>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('role')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="password" class="form-label fw-semibold">Nova senha</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror"
                                        id="password" name="password">
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
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
