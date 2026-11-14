<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#004b89">
        <title>@yield('titulo') · camila / carteira</title>
        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%23004b89'/><text x='16' y='22' font-family='Arial' font-size='16' font-weight='700' fill='%23ffe500' text-anchor='middle'>ct</text></svg>">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap">
        @vite('resources/css/app.css')
    </head>
    <body class="font-sans">
        <div class="flex min-h-screen flex-col px-4 py-8 sm:px-10">
            <header><a href="{{ url('/') }}"><x-application-logo /></a></header>

            <main class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-12">
                <p class="eyebrow mb-3">@yield('codigo')</p>
                <h1 class="font-display text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">@yield('titulo')</h1>
                <p class="mt-3 text-sm leading-relaxed text-muted">@yield('texto')</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    @section('acoes')
                        <a href="{{ url('/') }}" class="btn-primary">Ir para o extrato</a>
                    @show
                </div>
            </main>

            <footer><x-monogram class="text-2xl" /></footer>
        </div>
    </body>
</html>
