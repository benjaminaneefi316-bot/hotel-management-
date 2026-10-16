@php
    $settings = app(\App\Services\SettingsService::class);
    $hotelName = $settings->get('hotel_name', config('app.name', 'Azure Bay Hotel'));
    $hotelPhone = $settings->get('hotel_phone', '+355 68 444 0438');
    $hotelEmail = $settings->get('hotel_email', 'stay@azurebay.test');
    $hotelAddress = $settings->get('hotel_address', 'Rruga Mitat Hoxha 110, Sarandë, Albania');
    $mapQuery = urlencode($hotelAddress);
@endphp
<footer id="footer" class="site-footer pt-5">
    <div class="container pb-3">
        <div class="row g-4 justify-content-center">
            <div class="col-lg-3 col-md-6">
                <a class="text-decoration-none d-inline-block mb-3" href="{{ route('public.home') }}">
                    <span class="display-serif text-white fs-4">Azure&nbsp;Bay</span>
                    <span class="text-gold small d-block" style="letter-spacing:.3em;font-size:.6rem;">HOTEL &amp; RESORT</span>
                </a>
                <p class="small pt-1 mb-0">{{ $hotelName }} is an exquisite oasis of calm and comfort in the heart of Saranda, a magical destination on the Ionian coast.</p>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="mb-3">Get In Touch:</h6>
                <ul class="list-unstyled small d-grid gap-2 mb-0">
                    <li><i class="bi bi-telephone text-gold me-2"></i><a class="text-decoration-none" href="tel:{{ preg_replace('/[^0-9+]/', '', $hotelPhone) }}">{{ $hotelPhone }}</a></li>
                    <li><i class="bi bi-envelope text-gold me-2"></i><a class="text-decoration-none" href="mailto:{{ $hotelEmail }}">{{ $hotelEmail }}</a></li>
                    <li><i class="bi bi-house text-gold me-2"></i>{{ $hotelAddress }}</li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6">
                <h6 class="mb-3">Menu:</h6>
                <ul class="list-unstyled small d-grid gap-2 mb-0">
                    <li><a class="text-decoration-none" href="{{ route('public.home') }}">Home</a></li>
                    <li><a class="text-decoration-none" href="{{ route('public.accommodation') }}">Rooms</a></li>
                    <li><a class="text-decoration-none" href="{{ route('public.gallery') }}">Gallery</a></li>
                    <li><a class="text-decoration-none" href="{{ route('public.about') }}">About</a></li>
                    <li><a class="text-decoration-none" href="{{ route('public.contact') }}">Contact</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="mb-3">Location:</h6>
                <div class="ratio ratio-4x3 footer-map">
                    <iframe
                        src="https://www.google.com/maps?q={{ $mapQuery }}&output=embed"
                        title="Map showing {{ $hotelName }} at {{ $hotelAddress }}"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen></iframe>
                </div>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 small">
            <span>&copy; {{ date('Y') }} {{ $hotelName }}. All rights reserved.</span>
            <span class="text-center text-md-end">
                <a class="text-decoration-none" href="{{ route('public.booking') }}">Book online</a>
                <span class="text-muted mx-1">&middot;</span>
                <a class="text-decoration-none" href="{{ route('public.contact') }}">Plan your stay</a>
            </span>
        </div>
    </div>
</footer>
