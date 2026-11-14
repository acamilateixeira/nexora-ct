<x-guest-layout>
    <x-slot name="title">Criar conta</x-slot>

    <p class="eyebrow mb-3">Cadastro</p>
    <h1 class="font-display text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">Criar conta</h1>
    <p class="mt-2 text-sm text-muted">Já tem conta? <a class="font-semibold text-brand hover:underline" href="{{ route('login') }}">Entrar</a></p>

    <x-form-errors class="mt-6" :except="['name', 'email', 'password', 'password_confirmation']" />

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5" data-envio-unico>
        @csrf

        <div>
            <x-label for="name" value="Nome" />
            <x-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
        </div>

        <div>
            <x-label for="email" value="E-mail" />
            <x-input id="email" type="email" name="email" :value="old('email')" required autocomplete="email" />
        </div>

        <div x-data="senha">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-label for="password" value="Senha" />
                    <x-input id="password" type="password" name="password" required autocomplete="new-password" x-model="valor" />
                </div>
                <div>
                    <x-label for="password_confirmation" value="Confirme a senha" />
                    <x-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                </div>
            </div>
            <x-password-requirements />
        </div>

        <x-button class="w-full">Criar conta</x-button>
    </form>
</x-guest-layout>
