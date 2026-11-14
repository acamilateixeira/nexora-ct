<x-guest-layout>
    <x-slot name="title">Recuperar senha</x-slot>

    <p class="eyebrow mb-3">Recuperar acesso</p>
    <h1 class="font-display text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">Recuperar senha</h1>
    <p class="mt-2 text-sm leading-relaxed text-muted">Informe o e-mail da conta e enviaremos um link para você criar uma senha nova.</p>

    <x-auth-session-status class="mt-6" :status="session('status')" />
    <x-form-errors class="mt-6" :except="['email']" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5" data-envio-unico>
        @csrf

        <div>
            <x-label for="email" value="E-mail" />
            <x-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="email" />
        </div>

        <x-button class="w-full">Enviar link</x-button>
        <a href="{{ route('login') }}" class="block text-center text-xs font-semibold text-muted hover:text-brand">Voltar para o login</a>
    </form>
</x-guest-layout>
