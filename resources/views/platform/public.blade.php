<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $club->club_name }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f0f0f;
            color: #fff;
            min-height: 100vh;
        }

        /* Hero */
        .hero {
            position: relative;
            height: 45vh;
            min-height: 260px;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute; inset: 0;
            background-size: cover;
            background-position: center;
            filter: brightness(0.45);
        }

        .hero-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(to bottom, transparent 30%, #0f0f0f 100%);
        }

        .hero-content {
            position: relative; z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 24px 20px;
        }

        .club-logo {
            width: 70px; height: 70px;
            border-radius: 16px;
            border: 2px solid rgba(255,255,255,0.2);
            object-fit: cover;
            margin-bottom: 12px;
            background: #1a1a1a;
        }

        .club-name {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }

        .club-address {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.5);
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Stats row */
        .stats-bar {
            display: flex;
            justify-content: space-around;
            background: #1a1a1a;
            border-bottom: 1px solid #2a2a2a;
            padding: 16px 0;
        }

        .stat {
            text-align: center;
        }

        .stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #fff;
        }

        .stat-label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* Body */
        .body {
            padding: 20px;
            max-width: 480px;
            margin: 0 auto;
        }

        /* Section */
        .section-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.35);
            margin-bottom: 12px;
        }

        /* Social links */
        .socials {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 28px;
        }

        .social-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            color: #fff;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 500;
            transition: background 0.2s;
        }

        .social-btn:hover { background: #252525; color: #fff; }

        /* Trainers */
        .trainers { margin-bottom: 28px; }

        .trainer-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #1e1e1e;
        }

        .trainer-item:last-child { border-bottom: none; }

        .trainer-pfp {
            width: 44px; height: 44px;
            border-radius: 50%;
            object-fit: cover;
            background: #252525;
            flex-shrink: 0;
        }

        .trainer-pfp-placeholder {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: #252525;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .trainer-name { font-size: 0.9rem; font-weight: 600; }
        .trainer-role { font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-top: 2px; }

        /* Packages */
        .packages { margin-bottom: 28px; }

        .package-item {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .package-name { font-size: 0.9rem; font-weight: 600; }
        .package-meta { font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-top: 3px; }
        .package-price { font-size: 1.1rem; font-weight: 800; color: #fff; white-space: nowrap; }
        .package-duration { font-size: 0.7rem; color: rgba(255,255,255,0.4); text-align: right; margin-top: 2px; }

        /* CTA */
        .cta {
            position: sticky;
            bottom: 0;
            background: linear-gradient(to top, #0f0f0f 80%, transparent);
            padding: 20px;
            text-align: center;
        }

        .cta-btn {
            display: inline-block;
            background: #fff;
            color: #000;
            font-weight: 700;
            font-size: 1rem;
            padding: 14px 40px;
            border-radius: 999px;
            text-decoration: none;
            width: 100%;
            max-width: 400px;
            transition: opacity 0.2s;
        }

        .cta-btn:hover { opacity: 0.9; color: #000; }

        /* Gallery strip */
        .gallery-strip {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            margin-bottom: 28px;
            padding-bottom: 4px;
            scrollbar-width: none;
        }

        .gallery-strip::-webkit-scrollbar { display: none; }

        .gallery-strip img {
            width: 100px; height: 100px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
        }

        /* Divider */
        .divider { border: none; border-top: 1px solid #1e1e1e; margin: 0 0 24px; }
    </style>
</head>
<body>

    {{-- Hero --}}
    <div class="hero">
        <div class="hero-bg" style="background-image: url('{{ $club->cover_image ? asset('storage/' . $club->cover_image) : ($club->galleryImages->first() ? asset('storage/' . $club->galleryImages->first()->image_path) : '') }}');"></div>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            @if($club->logo)
            <img src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}" class="club-logo">
            @endif
            <h1 class="club-name">{{ $club->club_name }}</h1>
            @if($club->address)
            <p class="club-address"><i class="bi bi-geo-alt-fill"></i> {{ $club->address }}</p>
            @endif
        </div>
    </div>

    {{-- Stats --}}
    <div class="stats-bar">
        <div class="stat">
            <div class="stat-value">{{ number_format($averageRating, 1) }}</div>
            <div class="stat-label">Rating</div>
        </div>
        <div class="stat">
            <div class="stat-value">{{ $club->activities->count() }}+</div>
            <div class="stat-label">Classes</div>
        </div>
        <div class="stat">
            <div class="stat-value">{{ $activeMembersCount }}+</div>
            <div class="stat-label">Members</div>
        </div>
        @if($club->established_date)
        <div class="stat">
            <div class="stat-value">{{ \Carbon\Carbon::parse($club->established_date)->diffInYears(now()) }}y</div>
            <div class="stat-label">Experience</div>
        </div>
        @endif
    </div>

    <div class="body">

        {{-- Gallery --}}
        @if($club->galleryImages->count() > 0)
        <div class="gallery-strip">
            @foreach($club->galleryImages->take(8) as $img)
            <img src="{{ asset('storage/' . $img->image_path) }}" alt="">
            @endforeach
        </div>
        @endif

        {{-- Social Links --}}
        @if($club->socialLinks->count() > 0 || $club->phone)
        <p class="section-title">Connect</p>
        <div class="socials">
            @foreach($club->socialLinks as $link)
            @php
                $icon = 'bi-link-45deg'; $label = $link->platform;
                $p = strtolower($link->platform);
                if (str_contains($p, 'whatsapp'))  { $icon = 'bi-whatsapp';   $label = 'WhatsApp'; }
                elseif (str_contains($p, 'instagram')) { $icon = 'bi-instagram'; $label = 'Instagram'; }
                elseif (str_contains($p, 'facebook'))  { $icon = 'bi-facebook';  $label = 'Facebook'; }
                elseif (str_contains($p, 'twitter') || str_contains($p, 'x')) { $icon = 'bi-twitter-x'; $label = 'X'; }
                elseif (str_contains($p, 'youtube'))   { $icon = 'bi-youtube';   $label = 'YouTube'; }
                elseif (str_contains($p, 'tiktok'))    { $icon = 'bi-tiktok';    $label = 'TikTok'; }
            @endphp
            <a href="{{ $link->url }}" target="_blank" class="social-btn">
                <i class="bi {{ $icon }}"></i> {{ $label }}
            </a>
            @endforeach
            @if($club->phone)
            <a href="tel:{{ is_array($club->phone) ? ($club->phone['number'] ?? '') : $club->phone }}" class="social-btn">
                <i class="bi bi-telephone-fill"></i> Call Us
            </a>
            @endif
        </div>
        <hr class="divider">
        @endif

        {{-- Trainers --}}
        @if($club->instructors->count() > 0)
        <p class="section-title">Our Trainers</p>
        <div class="trainers">
            @foreach($club->instructors->take(5) as $instructor)
            @php $trainerUser = $instructor->user; @endphp
            <div class="trainer-item">
                @if($trainerUser && $trainerUser->profile_picture)
                <img src="{{ asset('storage/' . $trainerUser->profile_picture) }}" class="trainer-pfp" alt="">
                @else
                <div class="trainer-pfp-placeholder">{{ strtoupper(substr($trainerUser->full_name ?? 'T', 0, 1)) }}</div>
                @endif
                <div>
                    <div class="trainer-name">{{ $trainerUser->full_name ?? 'Trainer' }}</div>
                    <div class="trainer-role">{{ $instructor->role ?? $instructor->bio ?? 'Instructor' }}</div>
                </div>
            </div>
            @endforeach
        </div>
        <hr class="divider">
        @endif

        {{-- Packages --}}
        @if($club->packages->count() > 0)
        <p class="section-title">Packages</p>
        <div class="packages">
            @foreach($club->packages->take(4) as $package)
            <div class="package-item">
                <div>
                    <div class="package-name">{{ $package->name }}</div>
                    <div class="package-meta">
                        @if($package->gender) {{ ucfirst($package->gender) }} &middot; @endif
                        @if($package->age_min || $package->age_max) Ages {{ $package->age_min ?? '?' }}-{{ $package->age_max ?? '?' }} @endif
                    </div>
                </div>
                <div style="text-align:right;">
                    @if($package->price)
                    <div class="package-price">{{ $club->currency ?? 'BHD' }} {{ number_format($package->price, 0) }}</div>
                    @endif
                    @if($package->duration_months)
                    <div class="package-duration">{{ $package->duration_months }} months</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <div style="height: 80px;"></div>
    </div>

    {{-- CTA --}}
    <div class="cta">
        <a href="{{ route('register') }}" class="cta-btn">Join {{ $club->club_name }}</a>
    </div>

</body>
</html>
