<x-app-layout>
    <x-slot name="title">{{ $operacao->rotulo() }}</x-slot>

    @php
        $tz = config('app.display_timezone');
        $estornada = $operacao->estaEstornada();
    @endphp

    <div class="mx-auto max-w-lg">
        <a href="{{ route('dashboard') }}" class="mb-6 inline-block text-xs font-semibold text-muted hover:text-brand">← Voltar ao extrato</a>

        <x-auth-session-status class="mb-6" :status="session('status')" />

        <article class="card overflow-hidden" aria-labelledby="comprovante-titulo">
            <header class="relative overflow-hidden bg-ink px-6 py-7 text-white sm:px-8">
                <div class="relative">
                    <p class="eyebrow mb-3 text-sun">Comprovante</p>
                    <h1 id="comprovante-titulo" class="font-display text-lg font-semibold">{{ $operacao->rotulo() }}</h1>
                    <p class="mt-3 font-display text-4xl font-semibold tracking-[-0.04em] {{ $estornada ? 'line-through decoration-danger decoration-2' : '' }}">{{ $operacao->valor->format() }}</p>
                    <p class="mt-4">
                        @if ($estornada)
                            <span class="tag bg-lilac-soft text-lilac">Estornada</span>
                        @else
                            <span class="tag bg-ok-soft text-ok">Concluída</span>
                        @endif
                    </p>
                </div>
            </header>

            <dl class="divide-y divide-line px-6 text-sm sm:px-8">
                <div class="flex justify-between gap-4 py-4">
                    <dt class="text-muted">Data e hora</dt>
                    <dd class="tabular-nums">{{ $operacao->created_at->setTimezone($tz)->format('d/m/Y \à\s H:i') }}</dd>
                </div>

                @if ($operacao->origem)
                    <div class="flex justify-between gap-4 py-4">
                        <dt class="text-muted">De</dt>
                        <dd class="min-w-0 text-right"><span class="block font-semibold">{{ $operacao->origem->user->name }}</span><span class="block break-all text-xs text-muted">{{ $operacao->origem->user->email }}</span></dd>
                    </div>
                @endif

                @if ($operacao->destino)
                    <div class="flex justify-between gap-4 py-4">
                        <dt class="text-muted">Para</dt>
                        <dd class="min-w-0 text-right"><span class="block font-semibold">{{ $operacao->destino->user->name }}</span><span class="block break-all text-xs text-muted">{{ $operacao->destino->user->email }}</span></dd>
                    </div>
                @endif

                @if ($operacao->motivo)
                    <div class="flex justify-between gap-4 py-4">
                        <dt class="text-muted">Motivo</dt>
                        <dd class="max-w-[65%] break-words text-right">{{ $operacao->motivo }}</dd>
                    </div>
                @endif

                @if ($operacao->estorna)
                    <div class="flex justify-between gap-4 py-4">
                        <dt class="text-muted">Referente a</dt>
                        <dd class="text-right"><a class="font-semibold text-brand hover:underline" href="{{ route('operacoes.show', $operacao->estorna) }}">{{ $operacao->estorna->rotulo() }} de {{ $operacao->estorna->valor->format() }}</a></dd>
                    </div>
                @endif

                @if ($operacao->estorno)
                    <div class="flex justify-between gap-4 py-4">
                        <dt class="text-muted">Estornada em</dt>
                        <dd class="text-right"><a class="font-semibold text-brand hover:underline" href="{{ route('operacoes.show', $operacao->estorno) }}">{{ $operacao->estorno->created_at->setTimezone($tz)->format('d/m/Y \à\s H:i') }}</a></dd>
                    </div>
                @endif

                <div class="flex flex-col gap-1 py-4 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="text-muted">ID da transação</dt>
                    <dd class="break-all font-mono text-[11px] text-muted">{{ $operacao->uuid }}</dd>
                </div>
            </dl>

            <footer class="flex items-center justify-between border-t border-dashed border-line bg-sand px-6 py-3 sm:px-8">
                <x-application-logo size="text-sm" />
                <span class="text-[10px] text-muted">carteira digital</span>
            </footer>
        </article>

        @php
            $solicitacao = $operacao->solicitacaoEstorno;
            $remetente = $operacao->origem ? $operacao->origem->user->nomeAbreviado() : '';
            $destinatario = $operacao->destino ? $operacao->destino->user->nomeAbreviado() : '';
        @endphp

        @if ($operacao->tipo === \App\Models\Operacao::TIPO_DEPOSITO && ! $estornada)
            <x-atendimento class="mt-6" titulo="Algum problema com este depósito?" />
        @endif

        @if ($solicitacao && $operacao->carteira_origem_id === optional(Auth::user()->carteira)->id)
            <section class="card mt-6 p-6 sm:p-8" aria-labelledby="solicitacao-titulo">
                <h2 id="solicitacao-titulo" class="font-display text-base font-semibold">Contestação</h2>
                <dl class="mt-3 space-y-1 text-xs text-muted">
                    <div><dt class="inline">Enviada em</dt> <dd class="inline text-ink">{{ $solicitacao->created_at->setTimezone($tz)->format('d/m/Y \à\s H:i') }}</dd></div>
                    <div><dt class="inline">Motivo:</dt> <dd class="inline text-ink">{{ $solicitacao->motivo }}</dd></div>
                </dl>
                @if ($solicitacao->estaPendente())
                    <p class="mt-4 rounded border border-line bg-sand px-4 py-3 text-xs">Sua contestação está em análise. O resultado aparece aqui e no extrato.</p>
                @elseif ($solicitacao->status === \App\Models\SolicitacaoEstorno::STATUS_RECUSADA)
                    <p class="mt-4 rounded border border-line bg-sand px-4 py-3 text-xs">
                        Contestação não aprovada em {{ optional($solicitacao->respondida_em)->setTimezone($tz)->format('d/m/Y \à\s H:i') }}@if ($solicitacao->motivo_recusa): {{ $solicitacao->motivo_recusa }}@endif.
                    </p>
                    <x-atendimento class="mt-4" titulo="Dúvidas sobre a análise?" />
                @endif
            </section>
        @endif

        @can('solicitarEstorno', $operacao)
            <section class="card mt-6 p-6 sm:p-8" aria-labelledby="solicitar-titulo">
                <h2 id="solicitar-titulo" class="font-display text-base font-semibold">Contestar transferência</h2>
                <p class="mt-1 text-xs text-muted">Contestações são analisadas pela carteira. Durante a análise, o valor fica bloqueado com quem recebeu.</p>

                <x-form-errors class="mt-5" :except="['motivo']" />

                <form method="POST" action="{{ route('operacoes.solicitar-estorno', $operacao) }}" class="mt-5 space-y-4" data-envio-unico>
                    @csrf
                    <div>
                        <x-label for="motivo" value="Motivo" />
                        <x-input id="motivo" type="text" name="motivo" :value="old('motivo')" maxlength="255" required placeholder="Ex.: enviei o valor errado" />
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="btn-quiet">Contestar</button>
                    </div>
                </form>
            </section>
        @endcan

        @if ($solicitacao && $solicitacao->estaPendente() && $operacao->carteira_destino_id === optional(Auth::user()->carteira)->id)
            <p class="mt-6 rounded border border-line bg-sand px-4 py-3 text-xs">
                <span class="font-semibold">Valor bloqueado para análise.</span>
                @if ($solicitacao->valor_bloqueado > 0)
                    {{ \App\ValueObjects\Money::ofCents($solicitacao->valor_bloqueado)->format() }} deste recebimento ficam indisponíveis até a conclusão.
                @endif
            </p>
        @endif

        @can('estornar', $operacao)
            <section class="card mt-6 p-6 sm:p-8" aria-labelledby="devolver-titulo" x-data="{ confirmando: false }" @keydown.escape.window="confirmando = false">
                <h2 id="devolver-titulo" class="font-display text-base font-semibold">Devolver transferência</h2>

                <x-form-errors class="mt-5" :except="['motivo']" />

                <form method="POST" action="{{ route('operacoes.estornar', $operacao) }}" class="mt-5 space-y-4" x-ref="form" @submit.prevent="confirmando = true">
                    @csrf
                    <div>
                        <x-label for="motivo" value="Motivo da devolução" />
                        <x-input id="motivo" type="text" name="motivo" :value="old('motivo')" maxlength="255" required placeholder="Ex.: recebi por engano" />
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">Devolver {{ $operacao->valor->format() }}</button>
                    </div>
                </form>


                <div x-show="confirmando" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-ink/50 p-4 sm:items-center" @click.self="confirmando = false">
                    <div role="dialog" aria-modal="true" aria-labelledby="confirmar-titulo" aria-describedby="confirmar-texto" class="w-full max-w-sm rounded-[5px] bg-paper p-6 shadow-xl"
                        x-show="confirmando" x-transition x-trap.noscroll="confirmando">
                        <h3 id="confirmar-titulo" class="font-display text-lg font-semibold">Devolver {{ $operacao->valor->format() }} para {{ rtrim($remetente, '.') }}?</h3>
                        <p id="confirmar-texto" class="mt-2 text-sm text-muted">Esta ação não pode ser desfeita.</p>
                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" class="btn-quiet" @click="confirmando = false">Voltar</button>
                            <button type="button" class="btn-primary" @click="$el.disabled = true; $refs.form.submit()">Confirmar devolução</button>
                        </div>
                    </div>
                </div>
            </section>
        @endcan
    </div>
</x-app-layout>
