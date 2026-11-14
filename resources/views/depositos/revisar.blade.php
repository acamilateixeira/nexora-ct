<x-app-layout>
    <x-slot name="title">Revisar depósito</x-slot>

    <div class="mx-auto max-w-lg">
        <x-page-header eyebrow="Passo 2 de 2" title="Revisar depósito" />

        <div class="card overflow-hidden">
            <div class="border-b border-line bg-sand px-6 py-7 text-center sm:px-8">
                <p class="text-xs text-muted">Valor do depósito</p>
                <p class="mt-1 font-display text-4xl font-semibold tracking-[-0.04em]">{{ $valor->format() }}</p>
            </div>

            <dl class="divide-y divide-line px-6 text-sm sm:px-8">
                <div class="flex justify-between gap-4 py-4">
                    <dt class="text-muted">Saldo atual</dt>
                    <dd class="tabular-nums {{ $saldo->isNegative() ? 'text-danger' : '' }}">{{ $saldo->format() }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-4">
                    <dt class="text-muted">Saldo após o depósito</dt>
                    <dd class="font-semibold tabular-nums {{ $saldoDepois->isNegative() ? 'text-danger' : '' }}">{{ $saldoDepois->format() }}</dd>
                </div>
            </dl>

            <form method="POST" action="{{ route('depositos.store') }}" class="flex flex-col-reverse gap-3 px-6 pb-7 pt-4 sm:flex-row sm:items-center sm:justify-between sm:px-8" data-envio-unico>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <input type="hidden" name="valor" value="{{ $valor->toDecimalString() }}">

                <a href="{{ route('depositos.create', ['valor' => $valor->toDecimalString()]) }}" class="text-center text-xs font-semibold text-muted hover:text-ink">← Corrigir valor</a>
                <x-button>Confirmar depósito</x-button>
            </form>
        </div>
    </div>
</x-app-layout>
