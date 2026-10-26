@extends('layouts.public')

@section('title', 'Contact · Azure Bay Hotel')
@section('meta_description', 'Contact the Azure Bay Hotel reservations team by phone, email or using our enquiry form.')

@section('content')
<div class="page-hero" style="background-image:linear-gradient(rgba(24,28,32,.6),rgba(24,28,32,.6)),url('{{ asset('assets/img/gallery-dining.jpg') }}');">
    <div class="container">
        <div class="eyebrow text-gold mb-2">Get in touch</div>
        <h1 class="display-serif display-5 text-white mb-2">Contact us</h1>
        <p class="text-white-50 mb-0">Call, email, or send us a message using the form below.</p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5">
                <h2 class="display-serif h3 mb-4">Reach the hotel</h2>

                @php
                    $settings = app(\App\Services\SettingsService::class);
                    $hotelName = $settings->get('hotel_name', 'Azure Bay Hotel');
                    $hotelEmail = $settings->get('hotel_email', 'stay@azurebay.test');
                    $hotelPhone = $settings->get('hotel_phone', '+1 555 0100');
                    $hotelAddress = $settings->get('hotel_address', '1 Ocean Promenade, Azure Bay');
                @endphp

                <ul class="list-unstyled d-grid gap-3 mb-0">
                    <li class="d-flex gap-3">
                        <div class="icon-circle"><i class="bi bi-geo-alt"></i></div>
                        <div>
                            <div class="fw-semibold">{{ $hotelName }}</div>
                            <div class="text-muted-soft small">{{ $hotelAddress }}</div>
                        </div>
                    </li>
                    <li class="d-flex gap-3">
                        <div class="icon-circle"><i class="bi bi-telephone"></i></div>
                        <div>
                            <div class="fw-semibold">Reception</div>
                            <div class="text-muted-soft small">{{ $hotelPhone }}</div>
                        </div>
                    </li>
                    <li class="d-flex gap-3">
                        <div class="icon-circle"><i class="bi bi-envelope"></i></div>
                        <div>
                            <div class="fw-semibold">Email</div>
                            <div class="text-muted-soft small">{{ $hotelEmail }}</div>
                        </div>
                    </li>
                    <li class="d-flex gap-3">
                        <div class="icon-circle"><i class="bi bi-clock"></i></div>
                        <div>
                            <div class="fw-semibold">Front desk</div>
                            <div class="text-muted-soft small">Open 24 hours, every day</div>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="col-lg-7">
                <div class="card p-4 p-md-5 shadow-sm">
                    <h2 class="display-serif h4 mb-4">Send an enquiry</h2>

                    <form method="POST" action="{{ route('public.contact.store') }}" novalidate>
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="name">Your name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" required maxlength="120">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="email">Email <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email') }}" required maxlength="180">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                       value="{{ old('phone') }}" maxlength="40">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="subject">Subject</label>
                                <input type="text" id="subject" name="subject" class="form-control @error('subject') is-invalid @enderror"
                                       value="{{ old('subject') }}" maxlength="150">
                                @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold" for="message">Message <span class="text-danger">*</span></label>
                                <textarea id="message" name="message" rows="5"
                                          class="form-control @error('message') is-invalid @enderror"
                                          required minlength="10" maxlength="5000">{{ old('message') }}</textarea>
                                @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-gold px-4 py-2">
                                    <i class="bi bi-send me-1"></i> Send enquiry
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
