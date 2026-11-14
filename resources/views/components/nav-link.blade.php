@props(['active'])

<a {{ $attributes->merge(['class' => 'relative py-1 text-xs font-semibold transition hover:text-brand ' . (($active ?? false) ? 'text-brand after:absolute after:inset-x-0 after:-bottom-1 after:h-0.5 after:bg-brand' : 'text-ink')]) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
