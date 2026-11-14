<x-guest-layout>
    <x-slot name="title">Nova senha</x-slot>

    <p class="eyebrow mb-3">Recuperar acesso</p>
    <h1 class="font-display text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">Criar nova senha</h1>

    <x-form-errors class="mt-6" :except="['email', 'password', 'password_confirmation']" />

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5" data-envio-unico>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-label for="email" value="E-mail" />
            <x-input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="email" />
        </div>

        <div x-data="senha">
            <x-label for="password" value="Senha nova" />
            <x-input id="password" type="password" name="password" required autocomplete="new-password" x-model="valor" />
            <x-password-requirements />
        </div>

        <div>
            <x-label for="password_confirmation" value="Confirme a senha" />
            <x-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <x-button class="w-full">Salvar senha</x-button>
    </form>
</x-guest-layout>
