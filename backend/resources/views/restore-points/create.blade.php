@extends('layouts.app')

@section('title', 'Capture restore point')
@section('eyebrow', 'System')
@section('heading', 'Capture restore point')

@section('actions')
    <a href="{{ route('restore-points.index') }}" class="btn btn-soft"><i class="bi bi-arrow-left me-1"></i> Back</a>
@endsection

@section('content')
    <div class="alert alert-light border d-flex align-items-start">
        <i class="bi bi-shield-check text-gold me-2 mt-1"></i>
        <div class="small mb-0">
            Select which configuration values to capture. Secrets (passwords, tokens, keys, salts, peppers, OTPs) are
            matched by pattern and can never be included, even if selected.
        </div>
    </div>

    <form method="POST" action="{{ route('restore-points.store') }}">
        @csrf

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted-soft mb-3" style="letter-spacing:.12em;">Restore point</h2>

                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" maxlength="120" required
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', 'Config '.now()->format('Y-m-d H:i')) }}">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">You will type this exact name to confirm a restore.</div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" rows="2" maxlength="1000"
                                      class="form-control @error('description') is-invalid @enderror"
                                      placeholder="Why are you capturing this?">{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="include_role_assignments"
                                   name="include_role_assignments" value="1"
                                   @checked(old('include_role_assignments', $includeRolesByDefault))>
                            <label class="form-check-label" for="include_role_assignments">
                                Also capture role assignments
                                <span class="text-muted-soft small d-block">
                                    Captures the role set of every non-archived user. Restoring may update privileges
                                    (but never deactivates or resurrects accounts).
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Settings to capture</span>
                        <button type="button" class="btn btn-sm btn-soft" id="toggle-all">Toggle all</button>
                    </div>
                    <div class="card-body" style="max-height:520px;overflow-y:auto;">
                        @if ($settings->isEmpty())
                            <p class="text-muted-soft small mb-0">No eligible settings are configured.</p>
                        @else
                            <p class="small text-muted-soft">By default every eligible setting is captured. Uncheck any you want to exclude.</p>
                            @foreach ($settings as $setting)
                                <div class="form-check mb-2">
                                    <input class="form-check-input setting-check" type="checkbox"
                                           name="settings[]" value="{{ $setting->key }}"
                                           id="setting-{{ $setting->key }}"
                                           @checked(in_array($setting->key, old('settings', $settings->pluck('key')->all()), true))>
                                    <label class="form-check-label small" for="setting-{{ $setting->key }}">
                                        {{ \Illuminate\Support\Str::headline($setting->key) }}
                                        <span class="text-muted-soft d-block">{{ $setting->key }}</span>
                                    </label>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-gold flex-fill"><i class="bi bi-camera me-1"></i> Capture</button>
                    <a href="{{ route('restore-points.index') }}" class="btn btn-soft">Cancel</a>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        'use strict';
        var toggle = document.getElementById('toggle-all');
        if (!toggle) { return; }

        toggle.addEventListener('click', function () {
            var boxes = document.querySelectorAll('.setting-check');
            var allChecked = Array.prototype.every.call(boxes, function (b) { return b.checked; });
            boxes.forEach(function (b) { b.checked = !allChecked; });
        });
    })();
</script>
@endpush
