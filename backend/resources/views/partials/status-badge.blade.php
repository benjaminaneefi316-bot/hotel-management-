@php
    $raw = $value instanceof \BackedEnum ? $value->value : (string) $value;
    $map = [
        'pending' => 'badge-soft-warning',
        'confirmed' => 'badge-soft-info',
        'checked_in' => 'badge-soft-success',
        'checked_out' => 'badge-soft-neutral',
        'cancelled' => 'badge-soft-danger',
        'no_show' => 'badge-soft-danger',
        'available' => 'badge-soft-success',
        'occupied' => 'badge-soft-info',
        'cleaning' => 'badge-soft-warning',
        'maintenance' => 'badge-soft-danger',
        'out_of_service' => 'badge-soft-neutral',
        'paid' => 'badge-soft-success',
        'refunded' => 'badge-soft-neutral',
        'failed' => 'badge-soft-danger',
        'voided' => 'badge-soft-neutral',
        'active' => 'badge-soft-success',
        'inactive' => 'badge-soft-neutral',
        'archived' => 'badge-soft-danger',
        'completed' => 'badge-soft-neutral',
        'success' => 'badge-soft-success',
        'failure' => 'badge-soft-danger',
        'denied' => 'badge-soft-danger',
        'running' => 'badge-soft-warning',
        'verified' => 'badge-soft-success',
    ];
    $label = ($value instanceof \BackedEnum && method_exists($value, 'label'))
        ? $value->label()
        : ucfirst(str_replace('_', ' ', $raw));
    $class = $map[$raw] ?? 'badge-soft-neutral';
@endphp
<span class="badge badge-soft {{ $class }}">{{ $label }}</span>
