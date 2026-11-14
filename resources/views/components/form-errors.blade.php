@props(['except' => []])

@php $errosSemCampo = collect($errors->getMessages())->except($except)->flatten(); @endphp

@if ($errosSemCampo->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'rounded border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger']) }} role="alert">
        @foreach ($errosSemCampo as $mensagem)
            <p>{{ $mensagem }}</p>
        @endforeach
    </div>
@endif
