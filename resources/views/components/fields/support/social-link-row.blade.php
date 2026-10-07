@props(['index', 'link'])

<div class="social-link-row mb-4 flex items-end gap-2" x-data="{ open: false }">
    <!-- Platform Dropdown -->
    <div class="flex-1">
        <label class="tf-label">{{ __('shared.social_links_editor_platform') }}</label>
        <div class="relative">
            <button type="button"
                    @click="open = !open"
                    @click.away="open = false"
                    class="tf-dropdown-trigger border-primary/20 focus:border-primary text-start">
                <span class="flex items-center">
                    @if(($link['platform'] ?? '') == 'facebook')
                        <i class="bi bi-facebook me-2"></i>Facebook
                    @elseif(($link['platform'] ?? '') == 'twitter')
                        <i class="bi bi-twitter-x me-2"></i>Twitter/X
                    @elseif(($link['platform'] ?? '') == 'instagram')
                        <i class="bi bi-instagram me-2"></i>Instagram
                    @elseif(($link['platform'] ?? '') == 'linkedin')
                        <i class="bi bi-linkedin me-2"></i>LinkedIn
                    @elseif(($link['platform'] ?? '') == 'youtube')
                        <i class="bi bi-youtube me-2"></i>YouTube
                    @elseif(($link['platform'] ?? '') == 'tiktok')
                        <i class="bi bi-tiktok me-2"></i>TikTok
                    @elseif(($link['platform'] ?? '') == 'snapchat')
                        <i class="bi bi-snapchat me-2"></i>Snapchat
                    @elseif(($link['platform'] ?? '') == 'whatsapp')
                        <i class="bi bi-whatsapp me-2"></i>WhatsApp
                    @elseif(($link['platform'] ?? '') == 'telegram')
                        <i class="bi bi-telegram me-2"></i>Telegram
                    @elseif(($link['platform'] ?? '') == 'discord')
                        <i class="bi bi-discord me-2"></i>Discord
                    @elseif(($link['platform'] ?? '') == 'reddit')
                        <i class="bi bi-reddit me-2"></i>Reddit
                    @elseif(($link['platform'] ?? '') == 'pinterest')
                        <i class="bi bi-pinterest me-2"></i>Pinterest
                    @elseif(($link['platform'] ?? '') == 'twitch')
                        <i class="bi bi-twitch me-2"></i>Twitch
                    @elseif(($link['platform'] ?? '') == 'github')
                        <i class="bi bi-github me-2"></i>GitHub
                    @elseif(($link['platform'] ?? '') == 'spotify')
                        <i class="bi bi-spotify me-2"></i>Spotify
                    @elseif(($link['platform'] ?? '') == 'skype')
                        <i class="bi bi-skype me-2"></i>Skype
                    @elseif(($link['platform'] ?? '') == 'slack')
                        <i class="bi bi-slack me-2"></i>Slack
                    @elseif(($link['platform'] ?? '') == 'medium')
                        <i class="bi bi-medium me-2"></i>Medium
                    @elseif(($link['platform'] ?? '') == 'vimeo')
                        <i class="bi bi-vimeo me-2"></i>Vimeo
                    @elseif(($link['platform'] ?? '') == 'messenger')
                        <i class="bi bi-messenger me-2"></i>Messenger
                    @elseif(($link['platform'] ?? '') == 'wechat')
                        <i class="bi bi-wechat me-2"></i>WeChat
                    @elseif(($link['platform'] ?? '') == 'line')
                        <i class="bi bi-line me-2"></i>Line
                    @else
                        {{ __('shared.social_links_editor_select_platform') }}
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
                 class="tf-dropdown-menu w-full mt-1">
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
                    <div class="tf-dropdown-item-sm"
                         @click="$el.closest('.social-link-row').querySelector('.platform-value').value = '{{ $value }}';
                                 $el.closest('.social-link-row').querySelector('button span').innerHTML = '<i class=\'bi {{ $platform['icon'] }} me-2\'></i>{{ $platform['name'] }}';
                                 open = false">
                        <i class="bi {{ $platform['icon'] }} me-2"></i>{{ $platform['name'] }}
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- URL Input -->
    <div class="flex-1">
        <label class="tf-label">{{ __('shared.social_links_editor_url') }}</label>
        <input type="url"
               class="tf-input"
               name="social_links[{{ $index }}][url]"
               value="{{ $link['url'] ?? '' }}"
               placeholder="https://example.com/username"
               required>
    </div>

    <!-- Remove Button -->
    <div>
        <button type="button" onclick="this.closest('.social-link-row').remove()"
                class="p-3 text-red-500 border-2 border-red-200 rounded-xl hover:bg-red-50 hover:border-red-300 transition-all duration-300 remove-social-link">
            <i class="bi bi-trash"></i>
        </button>
    </div>
</div>
