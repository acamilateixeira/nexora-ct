@props(['titulo'])

<aside {{ $attributes->merge(['class' => 'rounded-[5px] border border-line bg-sand px-5 py-4 text-xs']) }}>
    <p class="font-semibold text-ink">{{ $titulo }}</p>
    <p class="mt-1 leading-relaxed text-muted">
        Fale com o atendimento:
        <a class="font-semibold text-brand hover:underline" href="tel:{{ preg_replace('/\D/', '', config('carteira.atendimento.telefone')) }}">{{ config('carteira.atendimento.telefone') }}</a>
        ou <a class="font-semibold text-brand hover:underline" href="mailto:{{ config('carteira.atendimento.email') }}">{{ config('carteira.atendimento.email') }}</a>,
        {{ config('carteira.atendimento.horario') }}. Tenha em mãos o código da operação.
    </p>
</aside>
