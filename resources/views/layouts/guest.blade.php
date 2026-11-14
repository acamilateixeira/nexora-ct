<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.head')
    </head>
    <body class="font-sans">
        <div class="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
            <aside class="relative hidden overflow-hidden bg-brand p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <x-application-logo size="text-[26px]" class="[&>span]:text-sun" />

                <div class="relative z-10 max-w-md">
                    <p class="eyebrow mb-6 text-sun">Carteira digital</p>
                    <p class="font-display text-[34px] font-semibold leading-[1.2] tracking-[-0.03em]">
                        Saldo, depósitos, transferências e estornos <span class="text-[#9fd3f5]">em um só extrato.</span>
                    </p>
                </div>
                <p class="relative z-10 text-[11px] text-[#c9dcee]">Desafio técnico · Camila Teixeira</p>
            </aside>

            <main class="flex flex-col px-4 py-8 sm:px-10">
                <div class="lg:hidden">
                    <x-application-logo />
                </div>
                <div class="mx-auto flex w-full max-w-sm flex-1 flex-col pt-14 pb-10 lg:justify-center lg:py-10">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
