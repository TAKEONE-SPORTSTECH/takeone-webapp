@extends('layouts.app')

@section('content')
<!-- Flag Icons CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h3 class="mb-0">Example Form with Reusable Components</h3>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="#">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <!-- Example 1: Mobile Number with Country Code -->
                                <div class="mb-3">
                                    <label for="mobile_number" class="form-label">Mobile Number</label>
                                    <x-country-code-dropdown
                                        name="country_code"
                                        id="country_code"
                                        :value="old('country_code', '+971')"
                                        :required="true"
                                        :error="$errors->first('country_code')">
                                        <input id="mobile_number" type="tel"
                                               class="form-control @error('mobile_number') is-invalid @enderror"
                                               name="mobile_number"
                                               value="{{ old('mobile_number') }}"
                                               required
                                               placeholder="Phone number">
                                    </x-country-code-dropdown>
                                    @error('mobile_number')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <!-- Example 2: Emergency Contact with Country Code -->
                                <div class="mb-3">
                                    <label for="emergency_number" class="form-label">Emergency Contact</label>
                                    <x-country-code-dropdown
                                        name="emergency_country_code"
                                        id="emergency_country_code"
                                        :value="old('emergency_country_code', '+971')"
                                        :required="false"
                                        :error="$errors->first('emergency_country_code')">
                                        <input id="emergency_number" type="tel"
                                               class="form-control"
                                               name="emergency_number"
                                               value="{{ old('emergency_number') }}"
                                               placeholder="Emergency phone number">
                                    </x-country-code-dropdown>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <!-- Example 3: Nationality Dropdown -->
                                <div class="mb-3">
                                    <label for="nationality" class="form-label">Nationality</label>
                                    <x-nationality-dropdown
                                        name="nationality"
                                        id="nationality"
                                        :value="old('nationality')"
                                        :required="true"
                                        :error="$errors->first('nationality')" />
                                </div>

                                <!-- Example 4: Second Nationality (Optional) -->
                                <div class="mb-3">
                                    <label for="second_nationality" class="form-label">Second Nationality (Optional)</label>
                                    <x-nationality-dropdown
                                        name="second_nationality"
                                        id="second_nationality"
                                        :value="old('second_nationality')"
                                        :required="false"
                                        :error="$errors->first('second_nationality')" />
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3">
                            <h5>Component Features:</h5>
                            <ul class="mb-0">
                                <li><strong>Country Code Dropdown:</strong> Bootstrap-based with flag icons and search</li>
                                <li><strong>Nationality Dropdown:</strong> Select2-based with flag icons and search</li>
                                <li>Both components preserve values on validation errors</li>
                                <li>Both components support required/optional fields</li>
                                <li>Both components display validation errors</li>
                                <li>Multiple instances can be used on the same page</li>
                            </ul>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Submit Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Usage Instructions -->
            <div class="card shadow mt-4">
                <div class="card-header bg-white">
                    <h4 class="mb-0">How to Use These Components</h4>
                </div>
                <div class="card-body">
                    <h5>1. Country Code Dropdown</h5>
                    <pre class="bg-light p-3 rounded"><code>&lt;x-country-code-dropdown
    name="country_code"
    id="country_code"
    :value="old('country_code', '+971')"
    :required="true"
    :error="$errors->first('country_code')"&gt;
    &lt;input type="tel" class="form-control" name="mobile_number" placeholder="Phone number"&gt;
&lt;/x-country-code-dropdown&gt;</code></pre>

                    <h5 class="mt-4">2. Nationality Dropdown</h5>
                    <pre class="bg-light p-3 rounded"><code>&lt;x-nationality-dropdown
    name="nationality"
    id="nationality"
    :value="old('nationality')"
    :required="true"
    :error="$errors->first('nationality')" /&gt;</code></pre>

                    <h5 class="mt-4">3. Required Dependencies</h5>
                    <pre class="bg-light p-3 rounded"><code>&lt;!-- CSS --&gt;
&lt;link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css"&gt;
&lt;link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /&gt;

&lt;!-- JS --&gt;
&lt;script src="https://code.jquery.com/jquery-3.6.0.min.js"&gt;&lt;/script&gt;
&lt;script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"&gt;&lt;/script&gt;

&lt;!-- Component Scripts --&gt;
@stack('styles')
@stack('scripts')</code></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Load component scripts -->
@stack('styles')
@stack('scripts')
@endsection
