@once
<div id="confirmDialog"
     class="fixed inset-0 z-[60] hidden"
     role="dialog">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50 transition-opacity" id="confirmDialogBackdrop"></div>

    <!-- Dialog -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all"
             id="confirmDialogPanel">
            <div class="p-6 text-center">
                <!-- Icon -->
                <div id="confirmDialogIcon"
                     class="mx-auto w-14 h-14 rounded-full flex items-center justify-center mb-4">
                </div>

                <!-- Title -->
                <h3 id="confirmDialogTitle" class="text-lg font-semibold text-foreground mb-2"></h3>

                <!-- Message -->
                <p id="confirmDialogMessage" class="text-sm text-muted-foreground mb-6"></p>

                <!-- Buttons -->
                <div class="flex gap-3 justify-center">
                    <button type="button"
                            id="confirmDialogCancel"
                            class="px-5 py-2.5 text-sm font-medium text-foreground bg-white border border-border rounded-lg hover:bg-muted/50 transition-colors">
                        Cancel
                    </button>
                    <button type="button"
                            id="confirmDialogConfirm"
                            class="px-5 py-2.5 text-sm font-medium text-white rounded-lg transition-colors">
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
window.confirmAction = function(options = {}) {
    const defaults = {
        title: 'Are you sure?',
        message: 'This action cannot be undone.',
        type: 'danger',
        confirmText: 'Delete',
        cancelText: 'Cancel',
    };
    const opts = { ...defaults, ...options };

    return new Promise((resolve) => {
        const dialog = document.getElementById('confirmDialog');
        const icon = document.getElementById('confirmDialogIcon');
        const title = document.getElementById('confirmDialogTitle');
        const message = document.getElementById('confirmDialogMessage');
        const confirmBtn = document.getElementById('confirmDialogConfirm');
        const cancelBtn = document.getElementById('confirmDialogCancel');
        const backdrop = document.getElementById('confirmDialogBackdrop');
        const panel = document.getElementById('confirmDialogPanel');

        // Set content
        title.textContent = opts.title;
        message.textContent = opts.message;
        confirmBtn.textContent = opts.confirmText;
        cancelBtn.textContent = opts.cancelText;

        // Set type styling
        const types = {
            danger: {
                iconBg: 'bg-destructive/10',
                iconHtml: '<i class="bi bi-exclamation-triangle text-destructive text-2xl"></i>',
                btnClass: 'bg-destructive hover:bg-destructive/90',
            },
            warning: {
                iconBg: 'bg-warning/10',
                iconHtml: '<i class="bi bi-exclamation-triangle text-warning text-2xl"></i>',
                btnClass: 'bg-warning hover:bg-warning/90',
            },
            info: {
                iconBg: 'bg-info/10',
                iconHtml: '<i class="bi bi-info-circle text-info text-2xl"></i>',
                btnClass: 'bg-info hover:bg-info/90',
            },
        };

        const style = types[opts.type] || types.danger;
        icon.className = `mx-auto w-14 h-14 rounded-full flex items-center justify-center mb-4 ${style.iconBg}`;
        icon.innerHTML = style.iconHtml;
        confirmBtn.className = `px-5 py-2.5 text-sm font-medium text-white rounded-lg transition-colors ${style.btnClass}`;

        // Show dialog
        dialog.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Animate in
        requestAnimationFrame(() => {
            backdrop.style.opacity = '1';
            panel.style.transform = 'scale(1)';
            panel.style.opacity = '1';
        });

        function close(result) {
            backdrop.style.opacity = '0';
            panel.style.transform = 'scale(0.95)';
            panel.style.opacity = '0';
            setTimeout(() => {
                dialog.classList.add('hidden');
                document.body.style.overflow = '';
            }, 200);
            // Cleanup
            confirmBtn.removeEventListener('click', onConfirm);
            cancelBtn.removeEventListener('click', onCancel);
            backdrop.removeEventListener('click', onCancel);
            document.removeEventListener('keydown', onKeydown);
            resolve(result);
        }

        function onConfirm() { close(true); }
        function onCancel() { close(false); }
        function onKeydown(e) { if (e.key === 'Escape') onCancel(); }

        confirmBtn.addEventListener('click', onConfirm);
        cancelBtn.addEventListener('click', onCancel);
        backdrop.addEventListener('click', onCancel);
        document.addEventListener('keydown', onKeydown);

        // Init animation state
        backdrop.style.opacity = '0';
        panel.style.transform = 'scale(0.95)';
        panel.style.opacity = '0';
        panel.style.transition = 'all 0.2s ease-out';
        backdrop.style.transition = 'opacity 0.2s ease-out';
    });
};
</script>
@endpush
@endonce
