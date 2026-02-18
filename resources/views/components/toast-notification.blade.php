@props(['position' => 'top-right'])

@once
{{-- Styles moved to app.css (Phase 6) --}}

@push('scripts')
<script>
    const Toast = {
        container: null,
        position: '{{ $position }}',

        init() {
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = `toast-container ${this.position}`;
                document.body.appendChild(this.container);
            }
            return this.container;
        },

        show(type, title, message, duration = 3000) {
            const container = this.init();

            const icons = {
                success: '<i class="bi bi-check-lg"></i>',
                error: '<i class="bi bi-x-lg"></i>',
                warning: '<i class="bi bi-exclamation-lg"></i>',
                info: '<i class="bi bi-info-lg"></i>'
            };

            const toast = document.createElement('div');
            toast.className = `toast-notification ${type}`;
            toast.style.position = 'relative';
            toast.innerHTML = `
                <span class="toast-icon">${icons[type] || icons.info}</span>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" aria-label="Close">×</button>
                <div class="toast-progress" style="animation-duration: ${duration}ms"></div>
            `;

            // Close button handler
            toast.querySelector('.toast-close').addEventListener('click', () => {
                this.hide(toast);
            });

            container.appendChild(toast);

            // Auto remove
            if (duration > 0) {
                setTimeout(() => this.hide(toast), duration);
            }

            return toast;
        },

        hide(toast) {
            if (!toast || !toast.parentElement) return;
            toast.classList.add('hiding');
            setTimeout(() => {
                if (toast.parentElement) toast.remove();
            }, 300);
        },

        success(title, message, duration) {
            return this.show('success', title, message, duration);
        },

        error(title, message, duration) {
            return this.show('error', title, message, duration);
        },

        warning(title, message, duration) {
            return this.show('warning', title, message, duration);
        },

        info(title, message, duration) {
            return this.show('info', title, message, duration);
        }
    };

    // Global function for backward compatibility
    function showToast(type, title, message, duration = 3000) {
        return Toast.show(type, title, message, duration);
    }
</script>
@endpush
@endonce
