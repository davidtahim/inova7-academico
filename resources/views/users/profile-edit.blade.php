@extends('layouts.app')

@section('page-title', 'Editar perfil')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-xl-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0 fw-bold">Editar perfil</h5>
                    </div>
                    <div class="card-body p-4">
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

                            @if ($user->role === 'teacher')
                                <hr class="my-4">

                                <div id="disciplinas" class="mb-4">
                                    <h6 class="fw-bold mb-3">Disciplinas atribuídas pelo coordenador</h6>
                                    @if ($subjects->isEmpty())
                                        <div class="alert alert-warning mb-0">
                                            Nenhuma disciplina foi atribuída ao professor neste semestre. O coordenador deve
                                            alocar as disciplinas antes da disponibilização.
                                        </div>
                                    @else
                                        <div class="row g-2">
                                            @foreach ($subjects as $subject)
                                                <div class="col-md-6 col-xl-4">
                                                    <div
                                                        class="border rounded p-2 d-flex align-items-center gap-2 mb-0 bg-light">
                                                        <span class="badge text-bg-primary rounded-pill">✓</span>
                                                        <span>{{ $subject->name }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div id="disponibilidade">
                                    <h6 class="fw-bold mb-3">Disponibilidade</h6>
                                    <div class="alert alert-info mb-3">
                                        A disponibilidade é preenchida pela oferta Ubíqua. Se a importação não estiver
                                        disponível, a equipe de TI pode cadastrar e corrigir os dados manualmente.
                                    </div>

                                    <div class="alert alert-light border mb-3">
                                        <strong>Turnos padrão da disponibilidade:</strong>
                                        <div class="mt-2 small text-secondary">
                                            <div>Manhã: 07:30 às 08:20 | 10:50 às 11:40</div>
                                            <div>Noite: 18:30 às 19:20 | 21:00 às 21:50</div>
                                        </div>
                                    </div>

                                    @if ($availability->isEmpty())
                                        <div class="alert alert-secondary mb-0">
                                            Nenhuma disponibilidade registrada para o semestre atual. Verifique a importação
                                            da oferta Ubíqua ou solicite suporte da área de TI.
                                        </div>
                                    @else
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Dia</th>
                                                        <th>Horário</th>
                                                        <th>Preferência</th>
                                                        <th>Observação</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($availability as $slot)
                                                        <tr>
                                                            <td>{{ ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][($slot->weekday ?? 1) - 1] ?? '—' }}
                                                            </td>
                                                            <td>{{ $slot->starts_at }} às {{ $slot->ends_at }}</td>
                                                            <td>{{ $slot->preference === 'preferred' ? 'Preferencial' : ($slot->preference === 'unavailable' ? 'Indisponível' : 'Disponível') }}
                                                            </td>
                                                            <td>{{ $slot->notes ?: '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            @endif

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
