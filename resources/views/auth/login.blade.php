<x-guest-layout>
    <x-slot name="title">Entrar</x-slot>

    <p class="eyebrow mb-3">Acesso</p>
    <h1 class="font-display text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">Entrar</h1>
    <p class="mt-2 text-sm text-muted">Ainda não tem conta? <a class="font-semibold text-brand hover:underline" href="{{ route('register') }}">Criar conta</a></p>

    <x-auth-session-status class="mt-6" :status="session('status')" />
    <x-form-errors class="mt-6" :except="['email', 'password']" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <x-label for="email" value="E-mail" />
            <x-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="email" />
        </div>

        <div>
            <div class="mb-1.5 flex items-baseline justify-between">
                <x-label for="password" value="Senha" class="mb-0" />
                @if (Route::has('password.request'))
                    <a class="text-[11px] font-semibold text-muted hover:text-brand" href="{{ route('password.request') }}">Esqueci a senha</a>
                @endif
            </div>
            <x-input id="password" type="password" name="password" required autocomplete="current-password" />
        </div>

        <x-button class="w-full">Entrar</x-button>
    </form>
</x-guest-layout>
