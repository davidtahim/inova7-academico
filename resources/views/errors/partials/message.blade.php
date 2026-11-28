{{-- Página de erro amigável: diz o que aconteceu, por quê e para onde ir. --}}
<section class="d-flex justify-content-center py-5">
    <div class="card border-0 shadow-sm" style="max-width: 560px; width: 100%;">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="display-6 fw-bold text-secondary mb-2" aria-hidden="true">{{ $code }}</div>
            <h2 class="h4 mb-3">{{ $heading }}</h2>
            <p class="text-secondary mb-4">{{ $detail }}</p>
            @auth
                <p class="small text-muted mb-4">
                    Você está conectado como <strong>{{ auth()->user()->name }}</strong>
                    · perfil {{ auth()->user()->role_label }}
                </p>
            @endauth
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary">
                    {{ auth()->check() ? 'Ir para o meu início' : 'Entrar' }}
                </a>
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-outline-secondary">Voltar</a>
            </div>
        </div>
    </div>
</section>
