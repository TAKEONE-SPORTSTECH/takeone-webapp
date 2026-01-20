# Reusable Form Components

This directory contains reusable Blade components for forms across the application.

## Available Components

### 1. Country Dropdown (`<x-country-dropdown />`)

A dropdown with country flags that stores the ISO3 country code.

**Features:**
- Flag icons for each country
- Loads data from `/data/countries.json`
- Preserves selected value on validation errors
- Displays validation errors
- Clean, modern UI with flag icons

**Usage:**

```blade
<x-country-dropdown 
    name="country" 
    id="country" 
    :value="old('country')" 
    :required="true"
    :error="$errors->first('country')" />
```

**Props:**
- `name` (default: 'country') - The name attribute for the select
- `id` (default: 'country') - The ID for the select element
- `value` (default: '') - The initial/selected value (ISO3 code)
- `required` (default: false) - Whether the field is required
- `error` (default: null) - Error message to display

**Example in a form:**

```blade
<div class="mb-3">
    <label for="country" class="form-label">Country</label>
    <x-country-dropdown 
        name="country" 
        id="country" 
        :value="old('country', 'USA')" 
        :required="true"
        :error="$errors->first('country')" />
</div>
```

---

### 2. Call Code Dropdown (`<x-call-code-dropdown />`)

A dropdown with country flags and call codes that stores the call code.

**Features:**
- Flag icons for each country
- Displays country name and call code
- Loads data from `/data/countries.json`
- Preserves selected value on validation errors
- Displays validation errors

**Usage:**

```blade
<x-call-code-dropdown 
    name="country_code" 
    id="country_code" 
    :value="old('country_code', '+971')" 
    :required="true"
    :error="$errors->first('country_code')" />
```

**Props:**
- `name` (default: 'call_code') - The name attribute for the select
- `id` (default: 'call_code') - The ID for the select element
- `value` (default: '') - The initial/selected value (call code)
- `required` (default: false) - Whether the field is required
- `error` (default: null) - Error message to display

**Example in a form:**

```blade
<div class="mb-3">
    <label for="country_code" class="form-label">Country Code</label>
    <x-call-code-dropdown 
        name="country_code" 
        id="country_code" 
        :value="old('country_code', '+971')" 
        :required="true"
        :error="$errors->first('country_code')" />
</div>
```

---

### 3. Currency Dropdown (`<x-currency-dropdown />`)

A dropdown with country flags and currency symbols that stores the currency code.

**Features:**
- Flag icons for each country
- Displays country name, currency code, and currency symbol
- Loads data from `/data/countries.json`
- Preserves selected value on validation errors
- Displays validation errors

**Usage:**

```blade
<x-currency-dropdown 
    name="currency" 
    id="currency" 
    :value="old('currency', 'USD')" 
    :required="true"
    :error="$errors->first('currency')" />
```

**Props:**
- `name` (default: 'currency') - The name attribute for the select
- `id` (default: 'currency') - The ID for the select element
- `value` (default: '') - The initial/selected value (currency code)
- `required` (default: false) - Whether the field is required
- `error` (default: null) - Error message to display

**Example in a form:**

```blade
<div class="mb-3">
    <label for="currency" class="form-label">Currency</label>
    <x-currency-dropdown 
        name="currency" 
        id="currency" 
        :value="old('currency', 'USD')" 
        :required="true"
        :error="$errors->first('currency')" />
</div>
```

---

### 4. Timezone Dropdown (`<x-timezone-dropdown />`)

A dropdown with country flags and timezones that stores the timezone.

**Features:**
- Flag icons for each country
- Displays country name and timezone
- Loads data from `/data/countries.json`
- Preserves selected value on validation errors
- Displays validation errors

**Usage:**

```blade
<x-timezone-dropdown 
    name="timezone" 
    id="timezone" 
    :value="old('timezone', 'Asia/Dubai')" 
    :required="true"
    :error="$errors->first('timezone')" />
```

**Props:**
- `name` (default: 'timezone') - The name attribute for the select
- `id` (default: 'timezone') - The ID for the select element
- `value` (default: '') - The initial/selected value (timezone)
- `required` (default: false) - Whether the field is required
- `error` (default: null) - Error message to display

**Example in a form:**

```blade
<div class="mb-3">
    <label for="timezone" class="form-label">Timezone</label>
    <x-timezone-dropdown 
        name="timezone" 
        id="timezone" 
        :value="old('timezone', 'Asia/Dubai')" 
        :required="true"
        :error="$errors->first('timezone')" />
</div>
```

---

## Dependencies

All components require:

### Flag Icons
```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">
```

### Countries Data File
All components load country data from `/public/data/countries.json` which contains:
- Country name
- ISO2 code (2-letter)
- ISO3 code (3-letter)
- Flag code (for flag-icons)
- Call code (phone country code)
- Currency code
- Currency symbol
- Timezone

**Note:** Israel is excluded from the countries list as requested.

---

## Complete Example Form

Here's a complete example of a form using all components:

```blade
@extends('layouts.app')

@section('content')
<!-- Flag Icons CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>User Information</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="#">
                        @csrf

                        <!-- Country Dropdown -->
                        <x-country-dropdown 
                            name="country" 
                            id="country" 
                            :value="old('country', 'USA')" 
                            :required="true"
                            :error="$errors->first('country')" />

                        <!-- Call Code Dropdown -->
                        <x-call-code-dropdown 
                            name="country_code" 
                            id="country_code" 
                            :value="old('country_code', '+971')" 
                            :required="true"
                            :error="$errors->first('country_code')" />

                        <!-- Currency Dropdown -->
                        <x-currency-dropdown 
                            name="currency" 
                            id="currency" 
                            :value="old('currency', 'USD')" 
                            :required="true"
                            :error="$errors->first('currency')" />

                        <!-- Timezone Dropdown -->
                        <x-timezone-dropdown 
                            name="timezone" 
                            id="timezone" 
                            :value="old('timezone', 'Asia/Dubai')" 
                            :required="true"
                            :error="$errors->first('timezone')" />

                        <button type="submit" class="btn btn-primary">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load component scripts -->
@stack('scripts')
@endsection
```

---

## Notes

1. **Data Loading**: All components use JavaScript's `fetch()` API to load country data from `/data/countries.json`. This ensures the data is loaded asynchronously and doesn't block page rendering.

2. **Value Preservation**: All components support Laravel's `old()` helper to preserve selected values after validation errors.

3. **Validation**: All components integrate with Laravel's validation system and display error messages when validation fails.

4. **Flag Icons**: All components use the flag-icons library (v6.6.6) for displaying country flags.

5. **Multiple Instances**: You can use multiple instances of any component on the same page. Each instance will work independently.

6. **Custom Styling**: Each component includes custom CSS for flag positioning and sizing.

7. **Unique Values**: 
   - Currency dropdown shows unique currencies (countries with same currency are grouped)
   - Timezone dropdown shows unique timezones (countries with same timezone are grouped)

8. **Israel Excluded**: As requested, Israel is not included in the countries.json file.

---

## Component Comparison

| Component | Displays | Stores | Example Value |
|-----------|----------|--------|---------------|
| Country Dropdown | Flag + Country Name | ISO3 Code | USA, GBR, ARE |
| Call Code Dropdown | Flag + Name + Call Code | Call Code | +1, +44, +971 |
| Currency Dropdown | Flag + Name + Currency | Currency Code | USD, EUR, AED |
| Timezone Dropdown | Flag + Name + Timezone | Timezone | America/New_York, Europe/London, Asia/Dubai |

---

## Troubleshooting

**Flags not displaying:**
- Ensure flag-icons CSS is loaded in your layout or view
- Check browser console for any CSS loading errors

**Dropdown not populating:**
- Check that `/public/data/countries.json` exists and is accessible
- Check browser console for any fetch errors
- Ensure the file path is correct

**Values not preserving after validation:**
- Ensure you're passing the correct value to the `:value` prop
- Use `old('field_name')` to get the previous value
- Check that the field name matches your validation rules

**Multiple instances conflicting:**
- Each component uses a unique ID based on the `id` prop
- Ensure each instance has a unique `id` prop value
