<x-app-layout>
    <x-slot name="title">Revisar transferência</x-slot>

    @php $saldoDepois = $saldo->subtract($valor); @endphp

    <div class="mx-auto max-w-lg">
        <x-page-header eyebrow="Passo 2 de 2" title="Revisar transferência" />

        <div class="card overflow-hidden">
            <div class="border-b border-line bg-sand px-6 py-7 text-center sm:px-8">
                <p class="text-xs text-muted">Valor da transferência</p>
                <p class="mt-1 font-display text-4xl font-semibold tracking-[-0.04em]">{{ $valor->format() }}</p>
            </div>

            <dl class="divide-y divide-line px-6 text-sm sm:px-8">
                <div class="flex justify-between gap-4 py-4">
                    <dt class="text-muted">Para</dt>
                    <dd class="text-right"><span class="block font-semibold">{{ $nome }}</span><span class="text-xs text-muted">{{ $email }}</span></dd>
                </div>
                <div class="flex justify-between gap-4 py-4">
                    <dt class="text-muted">Saldo atual</dt>
                    <dd class="tabular-nums">{{ $saldo->format() }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-4">
                    <dt class="text-muted">Saldo após a transferência</dt>
                    <dd class="font-semibold tabular-nums {{ $saldoDepois->isNegative() ? 'text-danger' : '' }}">{{ $saldoDepois->format() }}</dd>
                </div>
            </dl>

            @if ($saldoDepois->isNegative())
                <p class="mx-6 mb-2 rounded border border-danger/20 bg-danger-soft px-4 py-3 text-xs text-danger sm:mx-8" role="alert">Saldo insuficiente para essa transferência. Corrija o valor ou faça um depósito antes.</p>
            @endif

            <form method="POST" action="{{ route('transferencias.store') }}" class="flex flex-col-reverse gap-3 px-6 pb-7 pt-4 sm:flex-row sm:items-center sm:justify-between sm:px-8" data-envio-unico>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <input type="hidden" name="email" value="{{ $email }}">
                <input type="hidden" name="valor" value="{{ $valor->toDecimalString() }}">

                <a href="{{ route('transferencias.create', ['email' => $email, 'valor' => $valor->toDecimalString()]) }}" class="text-center text-xs font-semibold text-muted hover:text-ink">← Corrigir dados</a>
                <x-button :disabled="$saldoDepois->isNegative()">Confirmar transferência</x-button>
            </form>
        </div>
    </div>
</x-app-layout>
