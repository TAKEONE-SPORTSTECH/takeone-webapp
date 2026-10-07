{{-- System · Light · Dark. Remembered on this device (cookie `t1_theme`) and
     applied at once — no reload. --}}
<div class="t1 t1-theme" role="group" aria-label="{{ __('ui::messages.theme') }}"
     x-data="{ th: document.documentElement.dataset.t1Theme || 'light',
               set(v) { this.th = v; document.documentElement.dataset.t1Theme = v;
                        document.cookie = 't1_theme=' + v + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); } }">
    <button type="button" :class="{ on: th === 'system' }" @click="set('system')"><i class="bi bi-circle-half"></i>{{ __('ui::messages.theme_system') }}</button>
    <button type="button" :class="{ on: th === 'light' }" @click="set('light')"><i class="bi bi-sun"></i>{{ __('ui::messages.theme_light') }}</button>
    <button type="button" :class="{ on: th === 'dark' }" @click="set('dark')"><i class="bi bi-moon-stars"></i>{{ __('ui::messages.theme_dark') }}</button>
</div>
