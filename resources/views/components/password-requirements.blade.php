<ul {{ $attributes->merge(['class' => 'mt-3 grid gap-1.5 text-[11px] sm:grid-cols-2']) }} aria-label="Requisitos da senha">
    <template x-for="requisito in requisitos" :key="requisito.texto">
        <li class="flex items-center gap-2" :class="requisito.ok ? 'text-ok' : 'text-muted'">
            <svg x-show="requisito.ok" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M3 8.5l3.5 3.5L13 5"/></svg>
            <svg x-show="! requisito.ok" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="8" cy="8" r="5.5"/></svg>
            <span x-text="requisito.texto"></span>
            <span class="sr-only" x-text="requisito.ok ? '(atendido)' : '(pendente)'"></span>
        </li>
    </template>
</ul>
<noscript><p class="mt-1.5 text-[11px] text-muted">Mínimo de 8 caracteres, com maiúscula, minúscula, número e símbolo, sem sequências como 1234.</p></noscript>
