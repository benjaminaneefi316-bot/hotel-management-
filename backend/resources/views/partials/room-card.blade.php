@php
    $image = $image ?? null;
@endphp
<div class="card card-hover h-100 overflow-hidden">
    <div class="position-relative">
        <x-image :src="$image" :alt="$alt ?? $title ?? 'Hotel room'" class="room-card-img" />
        @isset($badge)
            <div class="position-absolute top-0 end-0 m-3">{!! $badge !!}</div>
        @endisset
    </div>
    <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start">
            <h3 class="h5 mb-1">{{ $title ?? 'Room' }}</h3>
            @isset($price)
                <div class="text-gold fw-semibold text-nowrap">@money($price) <span class="text-muted-soft small">/night</span></div>
            @endisset
        </div>
        @isset($meta)
            <div class="text-muted-soft small mb-2">{!! $meta !!}</div>
        @endisset
        @isset($description)
            <p class="text-muted-soft small">{{ \Illuminate\Support\Str::limit($description, 110) }}</p>
        @endisset
        @isset($features)
            <div class="d-flex flex-wrap gap-1 mb-3">
                @foreach ($features as $feature)
                    <span class="badge badge-soft badge-soft-gold">{{ $feature }}</span>
                @endforeach
            </div>
        @endisset
        <div class="mt-auto">
            <a href="{{ $url ?? '#' }}" class="btn btn-charcoal w-100">{{ $cta ?? 'View details' }}</a>
        </div>
    </div>
</div>
