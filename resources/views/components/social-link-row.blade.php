@props(['index', 'link'])

<div class="social-link-row mb-3 d-flex align-items-end">
    <div class="me-2 flex-grow-1">
        <label class="form-label">Platform</label>
        <div class="custom-select-wrapper">
            <button type="button" class="form-select text-start custom-select-btn" data-index="{{ $index }}">
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
                    Select Platform
                @endif
            </button>
            <input type="hidden" name="social_links[{{ $index }}][platform]" value="{{ $link['platform'] ?? '' }}" class="platform-value" required>
            <div class="custom-select-dropdown" style="display: none;">
                <div class="custom-select-option" data-value="facebook"><i class="bi bi-facebook me-2"></i>Facebook</div>
                <div class="custom-select-option" data-value="twitter"><i class="bi bi-twitter-x me-2"></i>Twitter/X</div>
                <div class="custom-select-option" data-value="instagram"><i class="bi bi-instagram me-2"></i>Instagram</div>
                <div class="custom-select-option" data-value="linkedin"><i class="bi bi-linkedin me-2"></i>LinkedIn</div>
                <div class="custom-select-option" data-value="youtube"><i class="bi bi-youtube me-2"></i>YouTube</div>
                <div class="custom-select-option" data-value="tiktok"><i class="bi bi-tiktok me-2"></i>TikTok</div>
                <div class="custom-select-option" data-value="snapchat"><i class="bi bi-snapchat me-2"></i>Snapchat</div>
                <div class="custom-select-option" data-value="whatsapp"><i class="bi bi-whatsapp me-2"></i>WhatsApp</div>
                <div class="custom-select-option" data-value="telegram"><i class="bi bi-telegram me-2"></i>Telegram</div>
                <div class="custom-select-option" data-value="discord"><i class="bi bi-discord me-2"></i>Discord</div>
                <div class="custom-select-option" data-value="reddit"><i class="bi bi-reddit me-2"></i>Reddit</div>
                <div class="custom-select-option" data-value="pinterest"><i class="bi bi-pinterest me-2"></i>Pinterest</div>
                <div class="custom-select-option" data-value="twitch"><i class="bi bi-twitch me-2"></i>Twitch</div>
                <div class="custom-select-option" data-value="github"><i class="bi bi-github me-2"></i>GitHub</div>
                <div class="custom-select-option" data-value="spotify"><i class="bi bi-spotify me-2"></i>Spotify</div>
                <div class="custom-select-option" data-value="skype"><i class="bi bi-skype me-2"></i>Skype</div>
                <div class="custom-select-option" data-value="slack"><i class="bi bi-slack me-2"></i>Slack</div>
                <div class="custom-select-option" data-value="medium"><i class="bi bi-medium me-2"></i>Medium</div>
                <div class="custom-select-option" data-value="vimeo"><i class="bi bi-vimeo me-2"></i>Vimeo</div>
                <div class="custom-select-option" data-value="messenger"><i class="bi bi-messenger me-2"></i>Messenger</div>
                <div class="custom-select-option" data-value="wechat"><i class="bi bi-wechat me-2"></i>WeChat</div>
                <div class="custom-select-option" data-value="line"><i class="bi bi-line me-2"></i>Line</div>
            </div>
        </div>
    </div>
    <div class="me-2 flex-grow-1">
        <label class="form-label">URL</label>
        <input type="url" class="form-control" name="social_links[{{ $index }}][url]" value="{{ $link['url'] ?? '' }}" placeholder="https://example.com/username" required>
    </div>
    <div class="mb-0">
        <button type="button" class="btn btn-outline-danger btn-sm remove-social-link">
            <i class="bi bi-trash"></i>
        </button>
    </div>
</div>
