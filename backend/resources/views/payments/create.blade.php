@extends('layouts.app')

@section('title', 'Record payment')
@section('eyebrow', 'Money')
@section('heading', 'Record payment')

@section('actions')
    <a href="{{ route('payments.index') }}" class="btn btn-soft"><i class="bi bi-arrow-left me-1"></i> Back to payments</a>
@endsection

@section('content')
    @php
        $currency = app(\App\Services\SettingsService::class)->get('hotel_currency', 'USD');
    @endphp

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('payments.store') }}">
                        @csrf

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="booking_id" class="form-label">Booking <span class="text-danger">*</span></label>
                                <select id="booking_id" name="booking_id" class="form-select @error('booking_id') is-invalid @enderror" required>
                                    <option value="">Select a booking…</option>
                                    @foreach ($bookings as $booking)
                                        <option value="{{ $booking->id }}"
                                                data-balance="{{ number_format($booking->balance(), 2, '.', '') }}"
                                                data-currency="{{ $currency }}"
                                                @selected((int) old('booking_id', $selectedBookingId) === $booking->id)>
                                            {{ $booking->reference }} · {{ $booking->guest?->fullName() ?? '—' }}
                                            · balance {{ number_format($booking->balance(), 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('booking_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text" id="balance-hint"></div>
                            </div>

                            <div class="col-md-6">
                                <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $currency }}</span>
                                    <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           value="{{ old('amount') }}" required>
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="currency" class="form-label">Currency</label>
                                <input type="text" id="currency" name="currency" maxlength="3"
                                       class="form-control @error('currency') is-invalid @enderror"
                                       value="{{ old('currency', $currency) }}">
                                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-3">
                                <label for="method" class="form-label">Method <span class="text-danger">*</span></label>
                                <select id="method" name="method" class="form-select @error('method') is-invalid @enderror" required>
                                    @foreach ($methods as $methodOption)
                                        <option value="{{ $methodOption->value }}" @selected(old('method', 'cash') === $methodOption->value)>
                                            {{ $methodOption->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    @foreach ($statuses as $statusOption)
                                        @if (in_array($statusOption->value, ['paid', 'pending'], true))
                                            <option value="{{ $statusOption->value }}" @selected(old('status', 'paid') === $statusOption->value)>
                                                {{ $statusOption->label() }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="provider" class="form-label">Provider</label>
                                <input type="text" id="provider" name="provider" maxlength="60"
                                       class="form-control @error('provider') is-invalid @enderror"
                                       value="{{ old('provider', 'front_desk') }}" placeholder="cash_desk, stripe, …">
                                @error('provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label for="provider_reference" class="form-label">Provider reference</label>
                                <input type="text" id="provider_reference" name="provider_reference" maxlength="120"
                                       class="form-control @error('provider_reference') is-invalid @enderror"
                                       value="{{ old('provider_reference') }}" placeholder="Tokenised reference — never a card number">
                                @error('provider_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Only store a tokenised reference. Card numbers, CVVs and PINs must never be entered here.</div>
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea id="notes" name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-gold"><i class="bi bi-check-lg me-1"></i> Record payment</button>
                            <a href="{{ route('payments.index') }}" class="btn btn-soft">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        'use strict';

        var select = document.getElementById('booking_id');
        var amount = document.getElementById('amount');
        var hint = document.getElementById('balance-hint');
        if (!select || !hint) { return; }

        function sync() {
            var option = select.options[select.selectedIndex];
            var balance = option ? option.getAttribute('data-balance') : null;

            if (balance !== null && select.value) {
                hint.textContent = 'Outstanding balance: ' + balance;
                if (amount && !amount.value) { amount.value = balance; }
            } else {
                hint.textContent = '';
            }
        }

        select.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
