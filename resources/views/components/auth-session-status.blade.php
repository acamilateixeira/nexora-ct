@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded border border-ok/20 bg-ok-soft px-4 py-3 text-sm text-ok']) }} role="status">
        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
        <span>{{ $status }}</span>
    </div>
@endif
