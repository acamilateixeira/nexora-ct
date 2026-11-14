<x-app-layout>
    <x-slot name="title">Extrato</x-slot>

    @php
        $saldo = $carteira->saldo;
        $primeiroNome = explode(' ', trim(Auth::user()->name))[0];
    @endphp

    <x-page-header eyebrow="Minha carteira" :title="'Olá, ' . $primeiroNome" />

    <x-auth-session-status class="mb-6" :status="session('status')" />


    <section class="relative overflow-hidden rounded-[5px] bg-brand px-6 py-7 text-white sm:px-9 sm:py-9" aria-labelledby="saldo-titulo">

        <div class="relative flex flex-col gap-7 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p id="saldo-titulo" class="eyebrow mb-3 text-sun">Saldo</p>
                <p class="font-display text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">{{ $saldo->format() }}</p>
                @if ($carteira->bloqueado->isPositive())
                    <p class="mt-3 inline-flex rounded-[3px] bg-white/15 px-2.5 py-1.5 text-xs">Bloqueado para análise: {{ $carteira->bloqueado->format() }}</p>
                    <p class="mt-2 text-xs text-[#c9dcee]">Disponível para transferir: <span class="font-semibold text-white">{{ $carteira->disponivelParaTransferir()->format() }}</span></p>
                @endif
                @if ($saldo->isNegative())
                    <p class="mt-3 inline-flex rounded-[3px] bg-white/15 px-2.5 py-1.5 text-xs">Saldo negativo</p>
                @endif
            </div>

            <div class="flex gap-3">
                <a href="{{ route('depositos.create') }}" class="btn-light flex-1 sm:flex-none">
                    Depositar
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 3v10M3 8l5 5 5-5"/></svg>
                </a>
                <a href="{{ route('transferencias.create') }}" class="btn flex-1 border border-white/40 text-white hover:border-white sm:flex-none">
                    Transferir
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 12L12 4M6 4h6v6"/></svg>
                </a>
            </div>
        </div>
    </section>

    <section class="mt-12" aria-labelledby="extrato-titulo">
        <h2 id="extrato-titulo" class="font-display text-xl font-semibold tracking-tight">Extrato</h2>

        <form method="GET" action="{{ route('dashboard') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end" x-data="{ periodo: @js($periodo->chave()) }" aria-label="Filtros do extrato">
            <div>
                <x-label for="periodo" value="Período" />
                <select id="periodo" name="periodo" x-model="periodo" class="field py-2.5 pr-9">
                    @foreach (\App\ValueObjects\Periodo::RAPIDOS as $dias)
                        <option value="{{ $dias }}" @if ($periodo->chave() === $dias) selected @endif>Últimos {{ $dias }} dias</option>
                    @endforeach
                    <option value="personalizado" @if ($periodo->chave() === 'personalizado') selected @endif>Escolher datas</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3" x-show="periodo === 'personalizado'" @if ($periodo->chave() !== 'personalizado') x-cloak @endif>
                <div>
                    <x-label for="de" value="De" />
                    <input id="de" name="de" type="date" class="field py-2.5" value="{{ $periodo->inicio()->format('Y-m-d') }}" max="{{ now(config('app.display_timezone'))->format('Y-m-d') }}" :disabled="periodo !== 'personalizado'">
                </div>
                <div>
                    <x-label for="ate" value="Até" />
                    <input id="ate" name="ate" type="date" class="field py-2.5" value="{{ $periodo->fim()->format('Y-m-d') }}" max="{{ now(config('app.display_timezone'))->format('Y-m-d') }}" :disabled="periodo !== 'personalizado'">
                </div>
            </div>

            <div>
                <x-label for="tipo" value="Tipo" />
                <select id="tipo" name="tipo" class="field py-2.5 pr-9">
                    <option value="todos" @if ($tipo === 'todos') selected @endif>Entradas e saídas</option>
                    <option value="entradas" @if ($tipo === 'entradas') selected @endif>Só entradas</option>
                    <option value="saidas" @if ($tipo === 'saidas') selected @endif>Só saídas</option>
                </select>
            </div>

            <button type="submit" class="btn-quiet py-2.5">Filtrar</button>
        </form>

        @if ($periodo->aviso())
            <p class="mt-4 rounded border border-line bg-sand px-4 py-3 text-xs text-muted" role="status">{{ $periodo->aviso() }}</p>
        @endif

        <dl class="mt-5 grid grid-cols-3 divide-x divide-line rounded-[5px] border border-line bg-paper text-center">
            <div class="px-2 py-3">
                <dt class="text-[11px] text-muted">Entradas</dt>
                <dd class="mt-0.5 font-display text-sm font-semibold tabular-nums text-ok sm:text-base">{{ $entradas->format() }}</dd>
            </div>
            <div class="px-2 py-3">
                <dt class="text-[11px] text-muted">Saídas</dt>
                <dd class="mt-0.5 font-display text-sm font-semibold tabular-nums sm:text-base">{{ $saidas->format() }}</dd>
            </div>
            <div class="px-2 py-3">
                <dt class="text-[11px] text-muted">Movimentações</dt>
                <dd class="mt-0.5 font-display text-sm font-semibold tabular-nums sm:text-base">{{ number_format($quantidade, 0, ',', '.') }}</dd>
            </div>
        </dl>
        <p class="mt-2 text-[11px] text-muted">{{ $periodo->rotulo() }}</p>

        @if ($lancamentos->isEmpty())
            <div class="card mt-4 flex flex-col items-center px-6 py-14 text-center">
                <x-monogram class="text-5xl text-line [&>span]:text-accent/50" />
                @if ($quantidade === 0 && $periodo->chave() === \App\ValueObjects\Periodo::PADRAO && $tipo === 'todos' && $carteira->saldo->isZero())
                    <p class="mt-5 font-display text-lg font-semibold">Nenhuma movimentação ainda</p>
                    <p class="mt-1 max-w-xs text-sm text-muted">Depósitos, transferências e estornos vão aparecer aqui.</p>
                    <a href="{{ route('depositos.create') }}" class="btn-primary mt-6">Fazer o primeiro depósito</a>
                @else
                    <p class="mt-5 font-display text-lg font-semibold">Nada neste período</p>
                    <p class="mt-1 max-w-xs text-sm text-muted">Nenhuma movimentação com esses filtros. Tente um período maior ou outras datas.</p>
                @endif
            </div>
        @else
            @php
                $tz = config('app.display_timezone');
                $diaAnterior = null;
                // o saldo do dia é o da última linha do dia; só é confiável sem filtro de tipo e com o dia inteiro na página
                $mostrarSaldoDoDia = $tipo === 'todos';
                $primeiraDaPagina = true;
            @endphp
            <div class="card mt-4 overflow-hidden">
                <div class="hidden grid-cols-[150px_minmax(0,1fr)_130px_130px] gap-4 border-b border-line bg-sand px-6 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted md:grid">
                    <span>Data</span><span>Lançamento</span><span class="text-right">Valor (R$)</span><span class="text-right">Saldo (R$)</span>
                </div>
                <ul>
                    @foreach ($lancamentos as $lancamento)
                        @php
                            $operacao = $lancamento->operacao;
                            $entrada = ! $lancamento->valor->isNegative();
                            $contraparte = $lancamento->contraparte();
                            $quando = $lancamento->created_at->setTimezone($tz);
                            $dia = $quando->format('Y-m-d');
                            $contestacao = $operacao->solicitacaoEstorno;
                        @endphp
                        @if ($dia !== $diaAnterior)
                            <li class="flex items-baseline justify-between gap-4 border-b border-line bg-stone/60 px-4 py-2 text-xs sm:px-6">
                                <span class="font-semibold">{{ ucfirst($quando->locale('pt_BR')->translatedFormat('l, d/m/Y')) }}</span>
                                @if ($mostrarSaldoDoDia && ! ($primeiraDaPagina && ! $lancamentos->onFirstPage()))
                                    <span class="text-muted">Saldo do dia <span class="font-semibold tabular-nums {{ $lancamento->saldo_apos->isNegative() ? 'text-danger' : 'text-ink' }}">{{ $lancamento->saldo_apos->format() }}</span></span>
                                @endif
                            </li>
                            @php $diaAnterior = $dia; @endphp
                        @endif
                        @php $primeiraDaPagina = false; @endphp
                        <li class="border-b border-line last:border-0">
                            <a href="{{ route('operacoes.show', $operacao) }}" class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1 px-4 py-3.5 transition hover:bg-sand sm:px-6 md:grid-cols-[150px_minmax(0,1fr)_130px_130px] md:items-center">
                                <span class="col-span-2 text-[11px] tabular-nums text-muted md:col-span-1 md:text-xs">{{ $quando->format('d/m/Y H:i') }}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold">{{ $lancamento->titulo() }}</span>
                                    @php
                                        $detalhe = array_filter([
                                            $contraparte ? ($entrada ? 'De ' : 'Para ') . $contraparte->name : null,
                                            $operacao->operador ? $operacao->motivo : null,
                                        ]);
                                    @endphp
                                    @if ($detalhe)
                                        <span class="block break-words text-xs text-muted">{{ implode(' · ', $detalhe) }}</span>
                                    @endif
                                    @if ($contestacao && $contestacao->estaPendente() && ! $operacao->estaEstornada())
                                        <span class="tag mt-1 bg-brand-soft text-brand">
                                            {{ $entrada ? ($contestacao->valor_bloqueado > 0 ? 'bloqueado para análise' : 'em análise') : 'contestação em análise' }}
                                        </span>
                                    @endif
                                </span>
                                <span class="text-right">
                                    <span class="block font-display text-sm font-semibold tabular-nums {{ $entrada ? 'text-ok' : 'text-ink' }}">{{ $entrada ? '+' : '' }}{{ $lancamento->valor->format() }}</span>
                                    <span class="block text-[11px] tabular-nums md:hidden {{ $lancamento->saldo_apos->isNegative() ? 'font-semibold text-danger' : 'text-muted' }}">saldo {{ $lancamento->saldo_apos->format() }}</span>
                                </span>
                                <span class="hidden text-right text-sm tabular-nums md:block {{ $lancamento->saldo_apos->isNegative() ? 'font-semibold text-danger' : 'text-muted' }}"><span class="sr-only">Saldo após: </span>{{ $lancamento->saldo_apos->format() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="mt-2 text-[11px] text-muted">Mais recentes primeiro. O saldo de cada linha é o saldo logo depois daquele lançamento.</p>

            @if ($lancamentos->hasPages())
                <nav class="mt-5 flex items-center justify-between text-xs font-semibold" aria-label="Páginas do extrato">
                    @if ($lancamentos->onFirstPage())
                        <span class="text-line">← Mais recentes</span>
                    @else
                        <a href="{{ $lancamentos->previousPageUrl() }}" class="hover:text-brand">← Mais recentes</a>
                    @endif
                    @if ($lancamentos->hasMorePages())
                        <a href="{{ $lancamentos->nextPageUrl() }}" class="hover:text-brand">Mais antigas →</a>
                    @else
                        <span class="text-line">Mais antigas →</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
</x-app-layout>
