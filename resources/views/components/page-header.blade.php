@props(['eyebrow', 'title'])

<div class="mb-8">
    <p class="eyebrow mb-3">{{ $eyebrow }}</p>
    <h1 class="font-display text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">{{ $title }}</h1>
    @if (trim($slot))
        <p class="mt-3 max-w-lg text-sm leading-relaxed text-muted">{{ $slot }}</p>
    @endif
</div>
