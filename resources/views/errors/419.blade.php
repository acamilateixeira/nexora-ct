@extends('errors.layout')
@section('codigo', 'Sessão expirada')
@section('titulo', 'Sua sessão expirou')
@section('texto', 'Por segurança, formulários abertos há muito tempo deixam de valer. Nada foi enviado. Abra a tela de novo e repita a operação.')
@section('acoes')
    <a href="{{ url()->previous() }}" class="btn-primary">Voltar e tentar de novo</a>
@endsection
