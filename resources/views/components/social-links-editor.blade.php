@props([
    'links' => [],
    'containerId' => 'socialLinksContainer',
])

@php
    $linksArray = [];
    foreach ($links as $link) {
        if (is_array($link)) {
            $linksArray[] = ['platform' => $link['platform'] ?? '', 'url' => $link['url'] ?? ''];
        } else {
            $linksArray[] = ['platform' => $link->platform ?? '', 'url' => $link->url ?? ''];
        }
    }
@endphp

<div class="space-y-4">
    <div class="flex justify-between items-center">
        <h5 class="form-label mb-0 font-semibold">Social Media Links</h5>
        <button type="button" class="btn btn-outline-primary btn-sm"
                onclick="socialLinksEditor_{{ $containerId }}.addRow()">
            <i class="bi bi-plus"></i> Add Link
        </button>
    </div>
    <div id="{{ $containerId }}">
        @foreach($linksArray as $index => $link)
            @include('components.social-link-row', ['index' => $index, 'link' => $link])
        @endforeach
    </div>
</div>

<script>
(function() {
    const CONTAINER_ID = '{{ $containerId }}';
    let rowIndex = {{ count($linksArray) }};

    const platforms = {
        facebook:  ['bi-facebook',  'Facebook'],
        twitter:   ['bi-twitter-x', 'Twitter/X'],
        instagram: ['bi-instagram', 'Instagram'],
        linkedin:  ['bi-linkedin',  'LinkedIn'],
        youtube:   ['bi-youtube',   'YouTube'],
        tiktok:    ['bi-tiktok',    'TikTok'],
        snapchat:  ['bi-snapchat',  'Snapchat'],
        whatsapp:  ['bi-whatsapp',  'WhatsApp'],
        telegram:  ['bi-telegram',  'Telegram'],
        discord:   ['bi-discord',   'Discord'],
        reddit:    ['bi-reddit',    'Reddit'],
        pinterest: ['bi-pinterest', 'Pinterest'],
        twitch:    ['bi-twitch',    'Twitch'],
        github:    ['bi-github',    'GitHub'],
        spotify:   ['bi-spotify',   'Spotify'],
        skype:     ['bi-skype',     'Skype'],
        slack:     ['bi-slack',     'Slack'],
        medium:    ['bi-medium',    'Medium'],
        vimeo:     ['bi-vimeo',     'Vimeo'],
        messenger: ['bi-messenger', 'Messenger'],
        wechat:    ['bi-wechat',    'WeChat'],
        line:      ['bi-line',      'Line'],
    };

    function buildRow(index, platform = '', url = '') {
        const optionsHtml = Object.entries(platforms).map(([val, [icon, name]]) =>
            `<div class="tf-dropdown-item-sm"
                  @click="$el.closest('.social-link-row').querySelector('.platform-value').value = '${val}';
                          $el.closest('.social-link-row').querySelector('button span').innerHTML = '<i class=\\'bi ${icon} mr-2\\'></i>${name}';
                          open = false">
                <i class="bi ${icon} mr-2"></i>${name}
            </div>`
        ).join('');

        const activePlatform = platform && platforms[platform]
            ? `<i class="bi ${platforms[platform][0]} mr-2"></i>${platforms[platform][1]}`
            : 'Select Platform';

        const row = document.createElement('div');
        row.className = 'social-link-row mb-4 flex items-end gap-2';
        row.setAttribute('x-data', '{ open: false }');
        row.innerHTML = `
            <div class="flex-1">
                <label class="tf-label">Platform</label>
                <div class="relative">
                    <button type="button"
                            @click="open = !open"
                            @click.away="open = false"
                            class="tf-dropdown-trigger border-primary/20 focus:border-primary text-left">
                        <span class="flex items-center">${activePlatform}</span>
                        <i class="bi bi-chevron-down transition-transform" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <input type="hidden" name="social_links[${index}][platform]" value="${platform}" class="platform-value" required>
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         class="tf-dropdown-menu w-full mt-1">
                        ${optionsHtml}
                    </div>
                </div>
            </div>
            <div class="flex-1">
                <label class="tf-label">URL</label>
                <input type="url"
                       class="tf-input"
                       name="social_links[${index}][url]"
                       value="${url}"
                       placeholder="https://example.com/username"
                       required>
            </div>
            <div>
                <button type="button"
                        onclick="this.closest('.social-link-row').remove()"
                        class="p-3 text-red-500 border-2 border-red-200 rounded-xl hover:bg-red-50 hover:border-red-300 transition-all duration-300">
                    <i class="bi bi-trash"></i>
                </button>
            </div>`;
        return row;
    }

    // Scoped remove handler for server-rendered rows
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById(CONTAINER_ID);
        if (container) {
            container.addEventListener('click', function (e) {
                const btn = e.target.closest('.remove-social-link');
                if (btn) btn.closest('.social-link-row').remove();
            });
        }
    });

    window.socialLinksEditor_{{ $containerId }} = {
        addRow(platform = '', url = '') {
            const container = document.getElementById(CONTAINER_ID);
            if (!container) return;
            const row = buildRow(rowIndex, platform, url);
            container.appendChild(row);
            if (window.Alpine) Alpine.initTree(row);
            rowIndex++;
        }
    };
})();
</script>
