<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.head')
    </head>
    <body class="font-sans">
        <a href="#conteudo" class="fixed left-4 -top-20 z-50 bg-ink px-3 py-2 text-sm text-white focus:top-3">Pular para o conteúdo</a>

        <div class="flex min-h-screen flex-col">
            @include('layouts.navigation')

            <main id="conteudo" class="mx-auto w-full max-w-5xl flex-1 px-4 py-10 sm:px-8 sm:py-14">
                {{ $slot }}
            </main>

            <footer class="border-t border-line">
                <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-6 sm:px-8">
                    <x-monogram class="text-2xl" />
                    <p class="text-right text-[11px] text-muted">Carteira digital · desafio técnico de Camila Teixeira</p>
                </div>
            </footer>
        </div>
    </body>
</html>
