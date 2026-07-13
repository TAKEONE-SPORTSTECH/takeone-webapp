{{-- Android app version helpers + live decoration of the drawer "Get the App / Update"
     entry. No-op in a normal web browser. Loaded once by the mobile layout. --}}
<script>
(function () {
    if (window.TakeoneApp) return;

    window.TakeoneApp = {
        isNative: function () {
            return !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());
        },
        _info: function () {
            try { return window.Capacitor.Plugins.App.getInfo(); } catch (e) { return Promise.resolve(null); }
        },
        _manifest: function () {
            return fetch('/app/manifest.json', { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                .then(function (r) { return r.json(); }).catch(function () { return null; });
        },
        // Resolves to {state:'browser'|'uptodate'|'update', current, latest, notes, url, ...}
        check: function () {
            if (!this.isNative()) return Promise.resolve({ state: 'browser' });
            return Promise.all([this._info(), this._manifest()]).then(function (arr) {
                var info = arr[0], man = arr[1];
                if (!info) return { state: 'browser' };
                var cur = parseInt(info.build || '0', 10);
                var lat = man ? parseInt(man.versionCode || '0', 10) : cur;
                return {
                    state: (man && lat > cur) ? 'update' : 'uptodate',
                    current: info.version, currentCode: cur,
                    latest: man ? man.versionName : info.version, latestCode: lat,
                    notes: man ? man.notes : '', url: man ? man.url : null,
                };
            });
        },
        // Downloads the latest APK natively and launches the installer. Falls back to
        // the system browser on older installs without the native download method.
        startUpdate: function (res) {
            var url = res && res.url;
            if (!url) { window.showToast && window.showToast('error', 'Update link unavailable'); return; }
            window.showToast && window.showToast('info', 'Downloading update…');
            var Cap = window.Capacitor;
            var MqttPush = Cap && Cap.Plugins && Cap.Plugins.MqttPush;
            if (MqttPush && MqttPush.downloadAndInstall) {
                MqttPush.downloadAndInstall({ url: url }).catch(function () {
                    try { window.open(url, '_blank'); } catch (e) { window.location.href = url; }
                });
            } else {
                try { window.open(url, '_blank'); } catch (e) { window.location.href = url; }
            }
        },
    };

    function decorate() {
        var nav = document.getElementById('get-app-nav');
        if (!nav || nav.dataset.decorated) return;
        if (!window.TakeoneApp.isNative()) return; // browser keeps the default "Get the App"
        nav.dataset.decorated = '1';

        var label = document.getElementById('get-app-label');
        var sub = document.getElementById('get-app-sub');
        var badge = document.getElementById('get-app-badge');
        if (sub) sub.textContent = 'Checking for updates…';

        window.TakeoneApp.check().then(function (res) {
            if (res.state === 'update') {
                if (label) label.textContent = 'Update available';
                if (sub) sub.textContent = 'Tap to update to v' + res.latest;
                if (badge) { badge.className = 'inline-flex items-center text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700'; badge.textContent = 'Update'; }
                nav.classList.add('ring-2', 'ring-amber-300');
                // Take over the click entirely: drop shell-link nav and run the update.
                nav.removeAttribute('data-shell-link');
                nav.onclick = function (e) { e.preventDefault(); e.stopPropagation(); window.TakeoneApp.startUpdate(res); };
            } else {
                if (label) label.textContent = 'TAKEONE App';
                if (sub) sub.textContent = "You're up to date · v" + (res.current || '');
                if (badge) { badge.className = 'inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700'; badge.innerHTML = '<i class="bi bi-check-lg"></i> Latest'; }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', decorate);
    window.addEventListener('shell:navigated', decorate);
    if (document.readyState !== 'loading') decorate();
})();
</script>
