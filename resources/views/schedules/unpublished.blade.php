@extends('layouts.app')

@section('page-title', 'Horários das turmas')

@section('content')
    <div class="container py-5">
        <div class="card border-0 shadow-sm text-center mx-auto" style="max-width: 560px;">
            <div class="card-body py-5">
                <div class="fs-1" aria-hidden="true">🗓️</div>
                <h2 class="h5 mt-2">Os horários de {{ $term->code }} ainda não foram publicados</h2>
                <p class="text-muted mb-0">A coordenação está montando a grade. Assim que for publicada, ela aparece aqui.</p>
            </div>
        </div>
    </div>
@endsection
