@props(['campo'])

@if ($campo !== null && $errors->has($campo))
    <p id="{{ $campo }}-erro" class="mt-1.5 flex items-start gap-1.5 text-xs text-danger">
        <svg class="mt-px h-3.5 w-3.5 shrink-0" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1a7 7 0 100 14A7 7 0 008 1zm-.75 3.5h1.5v4.5h-1.5V4.5zm0 6h1.5V12h-1.5v-1.5z"/></svg>
        <span>{{ $errors->first($campo) }}</span>
    </p>
@endif
