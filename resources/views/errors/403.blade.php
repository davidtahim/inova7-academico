@extends('layouts.app')
@section('title', 'Acesso restrito • Inova7 Acadêmico')
@section('page-title', 'Acesso restrito')
@section('content')
    @php
        $message = $exception?->getMessage();
        // Mensagens genéricas do framework viram uma explicação em português.
        if (! $message || $message === 'This action is unauthorized.' || $message === 'Forbidden') {
            $message = 'Esta área não faz parte das funções do seu perfil. Se você precisa dela no seu trabalho, peça acesso ao Administrador de TI.';
        }
    @endphp
    @include('errors.partials.message', ['code' => 403, 'heading' => 'Você não tem acesso a esta página', 'detail' => $message])
@endsection
