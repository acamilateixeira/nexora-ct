@props(['name' => 'valor', 'value' => null])

@php
    $erro = $errors->has($name);
    $value = is_scalar($value) ? (string) $value : '';
@endphp

<div class="relative">
    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 font-display text-sm font-semibold text-muted">R$</span>
    <input id="{{ $name }}" name="{{ $name }}" type="text" inputmode="numeric" autocomplete="off" placeholder="0,00" maxlength="20" value="{{ $value }}" data-dinheiro
        @if ($erro) aria-invalid="true" aria-describedby="{{ $name }}-erro" @endif
        {{ $attributes->merge(['class' => 'field pl-11 font-display text-lg font-semibold tracking-tight' . ($erro ? ' border-danger focus:border-danger focus:ring-danger/15' : '')]) }}>
</div>
<x-field-error :campo="$name" />
