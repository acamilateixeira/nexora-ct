<x-app-layout>
    <x-slot name="title">Depositar</x-slot>

    <div class="mx-auto max-w-lg">
        <x-page-header eyebrow="Passo 1 de 2" title="Depósito" />

        <div class="card p-6 sm:p-8">
            <x-form-errors class="mb-6" :except="['valor']" />

            <form method="POST" action="{{ route('depositos.revisar') }}" class="space-y-6" data-envio-unico>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                <div>
                    <x-label for="valor" value="Valor do depósito" />
                    <x-money-input :value="$valor" required autofocus />
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('dashboard') }}" class="text-center text-xs font-semibold text-muted hover:text-ink">Cancelar</a>
                    <x-button>
                        Revisar depósito
                        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
