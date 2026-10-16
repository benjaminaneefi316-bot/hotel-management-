@props([
    'src' => null,
    'alt' => 'Azure Bay Hotel',
    'class' => '',
    'style' => null,
    'loading' => 'lazy',
])
@php
    $placeholder = asset('assets/img/placeholder.svg');
    $raw = is_string($src) ? trim($src) : '';

    if ($raw === '') {
        $resolved = $placeholder;
    } elseif (\Illuminate\Support\Str::startsWith($raw, ['http://', 'https://', '//'])) {
        // Absolute remote URL — use as-is.
        $resolved = $raw;
    } else {
        // Root-relative storage path (e.g. /assets/img/room-deluxe.jpg).
        $resolved = asset(ltrim($raw, '/'));
    }
@endphp
<img src="{{ $resolved }}"
     alt="{{ $alt }}"
     @if ($class !== '') class="{{ $class }}" @endif
     @if ($style !== null) style="{{ $style }}" @endif
     loading="{{ $loading }}"
     decoding="async"
     onerror="this.onerror=null;this.src='{{ $placeholder }}';">
