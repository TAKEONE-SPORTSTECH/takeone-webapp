@props(['position' => 'top-right'])

@once
@push('scripts')
<script>
    // The single toast renderer is window.showToast (layouts/app.blade.php → the
    // toastManager container). This legacy Toast.* API now just delegates to it,
    // so there is exactly ONE toast container app-wide — no more duplicates.
    window.Toast = {
        show(type, title, message, duration)  { window.showToast?.(type, title, message, duration); },
        success(title, message, duration)     { window.showToast?.('success', title, message, duration); },
        error(title, message, duration)       { window.showToast?.('error', title, message, duration); },
        warning(title, message, duration)     { window.showToast?.('warning', title, message, duration); },
        info(title, message, duration)        { window.showToast?.('info', title, message, duration); },
    };
</script>
@endpush
@endonce
