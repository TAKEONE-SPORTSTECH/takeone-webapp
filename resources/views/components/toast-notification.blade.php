@props(['position' => 'top-right'])

@once
@push('styles')
<style>
    /* Toast Notification Styles */
    .toast-container {
        position: fixed;
        z-index: 9999;
        pointer-events: none;
    }
    .toast-container.top-right {
        top: 20px;
        right: 20px;
    }
    .toast-container.top-left {
        top: 20px;
        left: 20px;
    }
    .toast-container.top-center {
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
    }
    .toast-container.bottom-right {
        bottom: 20px;
        right: 20px;
    }
    .toast-container.bottom-left {
        bottom: 20px;
        left: 20px;
    }
    .toast-container.bottom-center {
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
    }
    .toast-notification {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        min-width: 300px;
        max-width: 400px;
        margin-bottom: 10px;
        pointer-events: auto;
        animation: toastSlideIn 0.3s ease-out;
    }
    .toast-notification.hiding {
        animation: toastFadeOut 0.3s ease-in forwards;
    }
    .toast-notification.success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }
    .toast-notification.error {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    .toast-notification.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }
    .toast-notification.info {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }
    .toast-notification .toast-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }
    .toast-notification .toast-content {
        flex: 1;
    }
    .toast-notification .toast-title {
        font-weight: 600;
        font-size: 15px;
        margin-bottom: 2px;
    }
    .toast-notification .toast-message {
        font-size: 13px;
        opacity: 0.9;
    }
    .toast-notification .toast-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        cursor: pointer;
        padding: 4px 8px;
        font-size: 16px;
        line-height: 1;
        border-radius: 6px;
        transition: background 0.2s;
    }
    .toast-notification .toast-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }
    .toast-notification .toast-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        background: rgba(255, 255, 255, 0.4);
        border-radius: 0 0 12px 12px;
        animation: toastProgress 3s linear forwards;
    }
    @keyframes toastSlideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes toastFadeOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
    @keyframes toastProgress {
        from { width: 100%; }
        to { width: 0%; }
    }
</style>
@endpush

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
