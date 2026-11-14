<header class="border-b border-line">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-8 gap-y-4 px-4 py-5 sm:h-20 sm:flex-nowrap sm:px-8 sm:py-0">
        <a href="{{ route('dashboard') }}" class="flex items-baseline gap-2">
            <x-application-logo />
            <span class="text-xs font-semibold text-muted">carteira</span>
        </a>

        <nav aria-label="Principal" class="order-3 flex w-full justify-between gap-6 sm:order-none sm:ml-auto sm:w-auto sm:justify-start sm:gap-7">
            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard', 'operacoes.*')">Extrato</x-nav-link>
            <x-nav-link :href="route('depositos.create')" :active="request()->routeIs('depositos.*')">Depositar</x-nav-link>
            <x-nav-link :href="route('transferencias.create')" :active="request()->routeIs('transferencias.*')">Transferir</x-nav-link>
        </nav>

        <div class="ml-auto flex items-center gap-3 sm:ml-0">
            <span class="hidden max-w-[10rem] truncate text-xs text-muted md:inline" title="{{ Auth::user()->email }}">{{ Auth::user()->name }}</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-stone font-display text-xs font-bold uppercase md:hidden" aria-hidden="true">{{ mb_substr(Auth::user()->name, 0, 1) }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-full border border-line px-3 py-1.5 text-[11px] font-semibold text-muted transition hover:border-ink hover:text-ink">Sair</button>
            </form>
        </div>
    </div>
</header>
