@props(['disabled' => false])

@php
    $campo = $attributes->get('name');
    $erro = $campo !== null && $errors->has($campo);
    // old() retorna array quando o campo é enviado como campo[]=x; nesse caso não preenche
    $valor = $attributes->get('value');
    $valor = is_scalar($valor) ? (string) $valor : null;
@endphp

<input {{ $disabled ? 'disabled' : '' }} @if ($valor !== null) value="{{ $valor }}" @endif @if ($erro) aria-invalid="true" aria-describedby="{{ $campo }}-erro" @endif
    {!! $attributes->except('value')->merge(['class' => 'field' . ($erro ? ' border-danger focus:border-danger focus:ring-danger/15' : '')]) !!}>
<x-field-error :campo="$campo" />
