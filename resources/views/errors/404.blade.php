@extends('layouts.app')
@section('title', 'Página não encontrada • Inova7 Acadêmico')
@section('page-title', 'Página não encontrada')
@section('content')
    @include('errors.partials.message', [
        'code' => 404,
        'heading' => 'Não encontramos esta página',
        'detail' => 'O endereço pode ter mudado ou o registro foi removido. Use o menu ao lado para navegar.',
    ])
@endsection
