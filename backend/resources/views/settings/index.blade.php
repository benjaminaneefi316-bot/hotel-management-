@extends('layouts.app')

@section('title', 'Settings')
@section('eyebrow', 'System')
@section('heading', 'Settings')

@section('content')
    @php
        $groupTitles = [
            'hotel' => 'Hotel identity',
            'policy' => 'Stay policy',
            'finance' => 'Financials',
            'loyalty' => 'Loyalty & invoicing',
        ];
    @endphp

    <div class="alert alert-light border d-flex align-items-start">
        <i class="bi bi-info-circle text-gold me-2 mt-1"></i>
        <div class="small mb-0">
            Values marked <span class="badge badge-soft badge-soft-gold">Snapshot-safe</span> are on the
            configuration whitelist and can be captured by a restore point and rolled back. Settings not marked
            this way are never included in a snapshot.
        </div>
    </div>

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PUT')

        @forelse ($groups as $group => $settings)
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <span class="fw-semibold">{{ $groupTitles[$group] ?? ucfirst((string) $group) }}</span>
                    <span class="text-muted-soft small">· {{ $settings->count() }} setting(s)</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($settings as $setting)
                            @php
                                $isEligible = in_array($setting->key, $eligible, true);
                                $inputName = 'settings['.$setting->key.']';
                                $current = old($inputName, $setting->value);
                            @endphp
                            <div class="col-lg-6">
                                <label for="setting-{{ $setting->key }}" class="form-label d-flex align-items-center gap-2">
                                    <span>{{ \Illuminate\Support\Str::headline($setting->key) }}</span>
                                    @if ($isEligible)
                                        <span class="badge badge-soft badge-soft-gold">Snapshot-safe</span>
                                    @endif
                                    <span class="text-muted-soft small ms-auto">{{ $setting->key }}</span>
                                </label>

                                @if ($setting->type === 'boolean')
                                    <select id="setting-{{ $setting->key }}" name="{{ $inputName }}" class="form-select">
                                        <option value="1" @selected((string) $current === '1')>Enabled</option>
                                        <option value="0" @selected((string) $current !== '1')>Disabled</option>
                                    </select>
                                @elseif ($setting->type === 'integer')
                                    <input type="number" step="1" id="setting-{{ $setting->key }}" name="{{ $inputName }}"
                                           class="form-control" value="{{ $current }}">
                                @elseif ($setting->type === 'decimal')
                                    <input type="number" step="0.01" id="setting-{{ $setting->key }}" name="{{ $inputName }}"
                                           class="form-control" value="{{ $current }}">
                                @else
                                    <input type="text" id="setting-{{ $setting->key }}" name="{{ $inputName }}"
                                           class="form-control" value="{{ $current }}">
                                @endif

                                @if ($setting->description)
                                    <div class="form-text">{{ $setting->description }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            @include('partials.empty-state', [
                'icon' => 'bi-sliders',
                'title' => 'No settings',
                'message' => 'No configuration values have been seeded yet.',
            ])
        @endforelse

        @if ($groups->isNotEmpty())
            <div class="d-flex gap-2 sticky-bottom bg-sand py-3">
                <button type="submit" class="btn btn-gold"><i class="bi bi-check-lg me-1"></i> Save settings</button>
                <span class="small text-muted-soft align-self-center">Every change is written to the audit trail.</span>
            </div>
        @endif
    </form>
@endsection
