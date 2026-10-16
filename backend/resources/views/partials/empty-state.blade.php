@php
    $icon = $icon ?? 'bi-inbox';
    $title = $title ?? 'Nothing here yet';
    $message = $message ?? 'There is no data to display.';
@endphp
<div class="text-center py-5">
    <div class="icon-circle mx-auto mb-3" style="width:72px;height:72px;font-size:1.9rem;">
        <i class="bi {{ $icon }}"></i>
    </div>
    <h4 class="h5 mb-1">{{ $title }}</h4>
    <p class="text-muted-soft mb-3">{{ $message }}</p>
    @isset($action)
        {!! $action !!}
    @endisset
</div>
