@props(['index', 'link'])

<div class="social-link-row mb-4 flex items-end gap-2" x-data="{ open: false }">
    <!-- Platform Dropdown -->
    <div class="flex-1">
        <label class="block text-sm font-medium text-gray-600 mb-1">Platform</label>
        <div class="relative">
            <button type="button"
                    @click="open = !open"
                    @click.away="open = false"
                    class="w-full px-4 py-3 text-base text-left border-2 border-primary/20 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none cursor-pointer flex items-center justify-between">
                <span class="flex items-center">
                    @if(($link['platform'] ?? '') == 'facebook')
                        <i class="bi bi-facebook mr-2"></i>Facebook
                    @elseif(($link['platform'] ?? '') == 'twitter')
                        <i class="bi bi-twitter-x mr-2"></i>Twitter/X
                    @elseif(($link['platform'] ?? '') == 'instagram')
                        <i class="bi bi-instagram mr-2"></i>Instagram
                    @elseif(($link['platform'] ?? '') == 'linkedin')
                        <i class="bi bi-linkedin mr-2"></i>LinkedIn
                    @elseif(($link['platform'] ?? '') == 'youtube')
                        <i class="bi bi-youtube mr-2"></i>YouTube
                    @elseif(($link['platform'] ?? '') == 'tiktok')
                        <i class="bi bi-tiktok mr-2"></i>TikTok
                    @elseif(($link['platform'] ?? '') == 'snapchat')
                        <i class="bi bi-snapchat mr-2"></i>Snapchat
                    @elseif(($link['platform'] ?? '') == 'whatsapp')
                        <i class="bi bi-whatsapp mr-2"></i>WhatsApp
                    @elseif(($link['platform'] ?? '') == 'telegram')
                        <i class="bi bi-telegram mr-2"></i>Telegram
                    @elseif(($link['platform'] ?? '') == 'discord')
                        <i class="bi bi-discord mr-2"></i>Discord
                    @elseif(($link['platform'] ?? '') == 'reddit')
                        <i class="bi bi-reddit mr-2"></i>Reddit
                    @elseif(($link['platform'] ?? '') == 'pinterest')
                        <i class="bi bi-pinterest mr-2"></i>Pinterest
                    @elseif(($link['platform'] ?? '') == 'twitch')
                        <i class="bi bi-twitch mr-2"></i>Twitch
                    @elseif(($link['platform'] ?? '') == 'github')
                        <i class="bi bi-github mr-2"></i>GitHub
                    @elseif(($link['platform'] ?? '') == 'spotify')
                        <i class="bi bi-spotify mr-2"></i>Spotify
                    @elseif(($link['platform'] ?? '') == 'skype')
                        <i class="bi bi-skype mr-2"></i>Skype
                    @elseif(($link['platform'] ?? '') == 'slack')
                        <i class="bi bi-slack mr-2"></i>Slack
                    @elseif(($link['platform'] ?? '') == 'medium')
                        <i class="bi bi-medium mr-2"></i>Medium
                    @elseif(($link['platform'] ?? '') == 'vimeo')
                        <i class="bi bi-vimeo mr-2"></i>Vimeo
                    @elseif(($link['platform'] ?? '') == 'messenger')
                        <i class="bi bi-messenger mr-2"></i>Messenger
                    @elseif(($link['platform'] ?? '') == 'wechat')
                        <i class="bi bi-wechat mr-2"></i>WeChat
                    @elseif(($link['platform'] ?? '') == 'line')
                        <i class="bi bi-line mr-2"></i>Line
                    @else
                        Select Platform
                    @endif
                </span>
                <i class="bi bi-chevron-down transition-transform" :class="{ 'rotate-180': open }"></i>
            </button>
            <input type="hidden" name="social_links[{{ $index }}][platform]" value="{{ $link['platform'] ?? '' }}" class="platform-value" required>

            <!-- Dropdown Menu -->
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                @php
                    $platforms = [
                        'facebook' => ['icon' => 'bi-facebook', 'name' => 'Facebook'],
                        'twitter' => ['icon' => 'bi-twitter-x', 'name' => 'Twitter/X'],
                        'instagram' => ['icon' => 'bi-instagram', 'name' => 'Instagram'],
                        'linkedin' => ['icon' => 'bi-linkedin', 'name' => 'LinkedIn'],
                        'youtube' => ['icon' => 'bi-youtube', 'name' => 'YouTube'],
                        'tiktok' => ['icon' => 'bi-tiktok', 'name' => 'TikTok'],
                        'snapchat' => ['icon' => 'bi-snapchat', 'name' => 'Snapchat'],
                        'whatsapp' => ['icon' => 'bi-whatsapp', 'name' => 'WhatsApp'],
                        'telegram' => ['icon' => 'bi-telegram', 'name' => 'Telegram'],
                        'discord' => ['icon' => 'bi-discord', 'name' => 'Discord'],
                        'reddit' => ['icon' => 'bi-reddit', 'name' => 'Reddit'],
                        'pinterest' => ['icon' => 'bi-pinterest', 'name' => 'Pinterest'],
                        'twitch' => ['icon' => 'bi-twitch', 'name' => 'Twitch'],
                        'github' => ['icon' => 'bi-github', 'name' => 'GitHub'],
                        'spotify' => ['icon' => 'bi-spotify', 'name' => 'Spotify'],
                        'skype' => ['icon' => 'bi-skype', 'name' => 'Skype'],
                        'slack' => ['icon' => 'bi-slack', 'name' => 'Slack'],
                        'medium' => ['icon' => 'bi-medium', 'name' => 'Medium'],
                        'vimeo' => ['icon' => 'bi-vimeo', 'name' => 'Vimeo'],
                        'messenger' => ['icon' => 'bi-messenger', 'name' => 'Messenger'],
                        'wechat' => ['icon' => 'bi-wechat', 'name' => 'WeChat'],
                        'line' => ['icon' => 'bi-line', 'name' => 'Line'],
                    ];
                @endphp
                @foreach($platforms as $value => $platform)
                    <div class="px-4 py-2 hover:bg-primary hover:text-white cursor-pointer flex items-center transition-colors"
                         @click="$el.closest('.social-link-row').querySelector('.platform-value').value = '{{ $value }}';
                                 $el.closest('.social-link-row').querySelector('button span').innerHTML = '<i class=\'bi {{ $platform['icon'] }} mr-2\'></i>{{ $platform['name'] }}';
                                 open = false">
                        <i class="bi {{ $platform['icon'] }} mr-2"></i>{{ $platform['name'] }}
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- URL Input -->
    <div class="flex-1">
        <label class="block text-sm font-medium text-gray-600 mb-1">URL</label>
        <input type="url"
               class="w-full px-4 py-3 text-base border-2 border-primary/20 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none"
               name="social_links[{{ $index }}][url]"
               value="{{ $link['url'] ?? '' }}"
               placeholder="https://example.com/username"
               required>
    </div>

    <!-- Remove Button -->
    <div>
        <button type="button"
                class="p-3 text-red-500 border-2 border-red-200 rounded-xl hover:bg-red-50 hover:border-red-300 transition-all duration-300 remove-social-link">
            <i class="bi bi-trash"></i>
        </button>
    </div>
</div>
