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

                            @if ($user->role === 'teacher')
                                <hr class="my-4">

                                <div class="mb-4">
                                    <h5 class="fw-bold mb-3">Disciplinas</h5>
                                    <div class="row g-2">
                                        @foreach ($subjects as $subject)
                                            <div class="col-md-6 col-xl-4">
                                                <label class="border rounded p-2 d-flex align-items-center gap-2 mb-0">
                                                    <input type="checkbox" name="subjects[]" value="{{ $subject->id }}"
                                                        {{ in_array($subject->id, old('subjects', $professor?->subjects->pluck('id')->all() ?? []), true) ? 'checked' : '' }}>
                                                    <span>{{ $subject->name }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <h5 class="fw-bold mb-3">Disponibilidade</h5>
                                    <div id="availability-list" class="d-grid gap-3">
                                        @php
                                            $slots = old('availability', $availability->toArray() ?: []);
                                        @endphp

                                        @if (empty($slots))
                                            <div class="border rounded p-3 availability-row">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-2">
                                                        <label class="form-label small fw-semibold">Dia</label>
                                                        <select name="availability[0][weekday]" class="form-select">
                                                            <option value="">Selecione</option>
                                                            @foreach (['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'] as $index => $day)
                                                                <option value="{{ $index + 1 }}">{{ $day }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small fw-semibold">Início</label>
                                                        <input type="time" name="availability[0][starts_at]"
                                                            class="form-control">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small fw-semibold">Fim</label>
                                                        <input type="time" name="availability[0][ends_at]"
                                                            class="form-control">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small fw-semibold">Preferência</label>
                                                        <select name="availability[0][preference]" class="form-select">
                                                            <option value="available">Disponível</option>
                                                            <option value="preferred">Preferencial</option>
                                                            <option value="unavailable">Indisponível</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold">Observação</label>
                                                        <input type="text" name="availability[0][notes]"
                                                            class="form-control" placeholder="Ex.: só manhã">
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            @foreach ($slots as $index => $slot)
                                                <div class="border rounded p-3 availability-row">
                                                    <div class="row g-2 align-items-end">
                                                        <div class="col-md-2">
                                                            <label class="form-label small fw-semibold">Dia</label>
                                                            <select name="availability[{{ $index }}][weekday]"
                                                                class="form-select">
                                                                <option value="">Selecione</option>
                                                                @foreach (['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'] as $dayIndex => $day)
                                                                    <option value="{{ $dayIndex + 1 }}"
                                                                        {{ old('availability.' . $index . '.weekday', $slot['weekday'] ?? '') == $dayIndex + 1 ? 'selected' : '' }}>
                                                                        {{ $day }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small fw-semibold">Início</label>
                                                            <input type="time"
                                                                name="availability[{{ $index }}][starts_at]"
                                                                class="form-control"
                                                                value="{{ old('availability.' . $index . '.starts_at', $slot['starts_at'] ?? '') }}">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small fw-semibold">Fim</label>
                                                            <input type="time"
                                                                name="availability[{{ $index }}][ends_at]"
                                                                class="form-control"
                                                                value="{{ old('availability.' . $index . '.ends_at', $slot['ends_at'] ?? '') }}">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small fw-semibold">Preferência</label>
                                                            <select name="availability[{{ $index }}][preference]"
                                                                class="form-select">
                                                                <option value="available"
                                                                    {{ old('availability.' . $index . '.preference', $slot['preference'] ?? 'available') === 'available' ? 'selected' : '' }}>
                                                                    Disponível</option>
                                                                <option value="preferred"
                                                                    {{ old('availability.' . $index . '.preference', $slot['preference'] ?? 'available') === 'preferred' ? 'selected' : '' }}>
                                                                    Preferencial</option>
                                                                <option value="unavailable"
                                                                    {{ old('availability.' . $index . '.preference', $slot['preference'] ?? 'available') === 'unavailable' ? 'selected' : '' }}>
                                                                    Indisponível</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label small fw-semibold">Observação</label>
                                                            <input type="text"
                                                                name="availability[{{ $index }}][notes]"
                                                                class="form-control"
                                                                value="{{ old('availability.' . $index . '.notes', $slot['notes'] ?? '') }}"
                                                                placeholder="Ex.: só manhã">
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    <button type="button" class="btn btn-outline-secondary btn-sm mt-3"
                                        onclick="addAvailabilitySlot()">+ Adicionar horário</button>
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

    @if ($user->role === 'teacher')
        <script>
            function addAvailabilitySlot() {
                const container = document.getElementById('availability-list');
                const index = container.querySelectorAll('.availability-row').length;
                const template = `
                    <div class="border rounded p-3 availability-row">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Dia</label>
                                <select name="availability[${index}][weekday]" class="form-select">
                                    <option value="">Selecione</option>
                                    <option value="1">Segunda</option>
                                    <option value="2">Terça</option>
                                    <option value="3">Quarta</option>
                                    <option value="4">Quinta</option>
                                    <option value="5">Sexta</option>
                                    <option value="6">Sábado</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Início</label>
                                <input type="time" name="availability[${index}][starts_at]" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Fim</label>
                                <input type="time" name="availability[${index}][ends_at]" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Preferência</label>
                                <select name="availability[${index}][preference]" class="form-select">
                                    <option value="available">Disponível</option>
                                    <option value="preferred">Preferencial</option>
                                    <option value="unavailable">Indisponível</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Observação</label>
                                <input type="text" name="availability[${index}][notes]" class="form-control" placeholder="Ex.: só manhã">
                            </div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', template);
            }
        </script>
    @endif
@endsection
