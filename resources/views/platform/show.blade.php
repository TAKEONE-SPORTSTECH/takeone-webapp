@extends('layouts.app')

@push('styles')
<style>
    .page-container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 15px;
    }

    /* HERO BANNER */
    .hero-banner {
        min-height: 580px;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 45px;
        overflow: hidden;
        border-radius: 0;
    }

    .hero-bg-image {
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, rgba(0,0,0,0.2), rgba(0,0,0,0.95)),
            var(--hero-bg) center/cover;
        z-index: 0;
    }

    .banner-top, .banner-bottom {
        position: relative;
        z-index: 1;
    }

    .banner-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .club-logo-wrapper {
        width: 150px;
        height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .glass-hub {
        display: flex;
        gap: 8px;
    }

    .hub-link {
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-decoration: none;
        border-radius: 22px;
        transition: 0.3s;
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(12px);
        padding: 10px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .hub-link:hover {
        background: var(--color-primary);
        transform: translateY(-3px);
        color: white;
    }

    .banner-bottom {
        margin-bottom: 75px;
    }

    .stats-row {
        display: flex;
        gap: 50px;
        flex-wrap: wrap;
    }

    .stat-item {
        border-left: 3px solid var(--color-primary);
        padding-left: 20px;
    }

    .stat-item h3 {
        color: white;
        font-weight: 800;
        margin: 0;
        font-size: 1.8rem;
    }

    .stat-item span {
        color: rgba(255,255,255,0.5);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        font-weight: 700;
    }

    /* CONTENT CARD */
    .content-card {
        background: white;
        margin-top: -65px;
        position: relative;
        z-index: 10;
        padding: 45px;
        box-shadow: 0 25px 60px rgba(0,0,0,0.1);
    }

    /* NAV PILLS */
    .content-card .nav-pills {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 5px;
        margin-bottom: 2.5rem;
        padding: 0;
        list-style: none;
    }

    .content-card .nav-pills .nav-link {
        border-radius: 18px;
        padding: 15px 30px;
        font-weight: 700;
        color: #64748b;
        margin: 0 5px;
        border: 1px solid transparent;
        background: none;
        cursor: pointer;
        transition: 0.3s;
        font-size: 14px;
    }

    .content-card .nav-pills .nav-link.active {
        background: var(--color-primary) !important;
        color: white !important;
        box-shadow: 0 12px 24px color-mix(in srgb, var(--color-primary) 25%, transparent);
    }

    .content-card .nav-pills .nav-link:hover:not(.active) {
        background: #f1f5f9;
    }

    /* PERK CARDS */
    .perk-card {
        border-radius: 24px;
        overflow: hidden;
        position: relative;
        height: 200px;
        border: none;
        transition: 0.3s;
    }

    .perk-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: 0.5s;
    }

    .perk-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
        padding: 20px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
    }

    .perk-card:hover img { transform: scale(1.1); }
    .perk-card:hover { box-shadow: 0 10px 15px -3px rgba(15,23,42,0.25); transform: translateY(-4px); }

    .perk-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        background: var(--color-primary);
        color: white;
        padding: 5px 12px;
        border-radius: 10px;
        font-weight: 800;
        font-size: 0.8rem;
        z-index: 2;
    }

    /* TRAINER CARDS */
    .mini-trainer {
        background: #f8fafc;
        border-radius: 20px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 15px;
        border: 1px solid #e2e8f0;
        transition: 0.3s;
    }

    .mini-trainer:hover {
        border-color: var(--color-primary);
        background: white;
    }

    .mini-pfp {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        object-fit: cover;
    }

    .mini-pfp-placeholder {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        background: var(--color-primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 700;
    }

    /* FACILITY PREVIEWS */
    .fac-preview {
        border-radius: 20px;
        height: 160px;
        object-fit: cover;
        width: 100%;
    }

    .fac-placeholder {
        border-radius: 20px;
        height: 160px;
        width: 100%;
        background: linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 70%, transparent));
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* PACKAGE CARDS */
    .grid-packages {
        display: grid;
        grid-template-columns: repeat(1, 1fr);
        gap: 1.5rem;
    }
    @media (min-width: 768px) { .grid-packages { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1024px) { .grid-packages { grid-template-columns: repeat(3, 1fr); } }

    .package-card {
        border: 1px solid #edf2f7;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
        transition: all 0.3s;
        border-radius: 16px;
    }

    .package-card:hover {
        box-shadow: 0 10px 15px -3px rgba(15,23,42,0.25);
        transform: translateY(-4px);
    }

    .package-img-wrapper {
        width: 100%;
        aspect-ratio: 16/9;
        overflow: hidden;
        background: #f1f5f9;
    }

    .package-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: top;
    }

    .activity-item {
        border: 1px solid #e2e8f0;
        padding: 12px;
        background: rgba(248,250,252,0.5);
        margin-bottom: 8px;
        border-radius: 8px;
    }

    .instructor-tag {
        display: flex;
        align-items: center;
        gap: 6px;
        background: color-mix(in srgb, var(--color-primary) 10%, transparent);
        border-radius: 100px;
        padding: 4px 8px;
    }

    .instructor-img {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 1px solid color-mix(in srgb, var(--color-primary) 20%, transparent);
        object-fit: cover;
    }

    .badge-pill {
        font-size: 10px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 100px;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
    }

    .bg-secondary-light {
        background-color: #f1f5f9;
        color: #475569;
        border: none;
    }

    /* CLASS CARDS (SCHEDULE) */
    .class-card {
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        display: flex;
        gap: 14px;
        padding: 16px;
        align-items: stretch;
        transition: 0.25s;
        margin-bottom: 1rem;
    }

    .class-card:hover {
        background: white;
        box-shadow: 0 10px 18px rgba(15,23,42,0.08);
        transform: translateY(-2px);
    }

    .class-thumb {
        width: 96px;
        border-radius: 14px;
        overflow: hidden;
        flex-shrink: 0;
    }

    .class-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .class-meta {
        font-size: 10px;
    }

    .status-chip {
        font-size: 10px;
        font-weight: 700;
        border-radius: 999px;
        padding: 3px 10px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid transparent;
    }

    .status-ongoing { background: #ecfdf3; color: #166534; border-color: #bbf7d0; }
    .status-bookable { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .status-full { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .status-finished { background: #f9fafb; color: #6b7280; border-color: #e5e7eb; }

    .pill-tag {
        font-size: 10px;
        border-radius: 999px;
        padding: 3px 8px;
        background: #e2e8f0;
        color: #475569;
    }

    /* EVENTS */
    .events-lane {
        position: relative;
        padding-left: 34px;
    }

    .events-lane::before {
        content: '';
        position: absolute;
        left: 14px;
        top: 0;
        bottom: 0;
        width: 3px;
        border-radius: 999px;
        background: linear-gradient(to bottom, #fecaca, #fee2e2);
    }

    .event-node {
        position: absolute;
        left: 8px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #fff;
        border: 3px solid var(--color-primary);
        box-shadow: 0 0 0 4px rgba(248,113,113,0.25);
    }

    .event-card {
        position: relative;
        margin-bottom: 24px;
        border-radius: 22px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        background: radial-gradient(circle at top left, rgba(248,250,252,0.9), #ffffff);
        display: flex;
        flex-direction: column;
        transition: 0.25s;
    }

    .event-card:hover {
        box-shadow: 0 18px 35px rgba(15,23,42,0.12);
        transform: translateY(-3px);
    }

    .event-header {
        display: flex;
        padding: 16px 20px 12px;
        gap: 14px;
    }

    .event-date-pill {
        min-width: 60px;
        text-align: center;
        border-radius: 16px;
        background: #0f172a;
        color: #e5e7eb;
        padding: 8px 6px;
    }

    .event-date-pill .day { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
    .event-date-pill .date { font-size: 18px; font-weight: 800; line-height: 1; }
    .event-date-pill .month { font-size: 11px; text-transform: uppercase; }

    .event-body-main { flex: 1; }
    .event-title { font-size: 15px; font-weight: 800; margin-bottom: 4px; }
    .event-meta { font-size: 11px; color: #6b7280; }
    .event-tagline { padding: 0 20px 12px; font-size: 11px; color: #6b7280; }

    .event-chip-row {
        padding: 0 20px 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .event-chip {
        font-size: 10px;
        border-radius: 999px;
        padding: 4px 9px;
        border: 1px solid #e2e8f0;
        background: #f9fafb;
        color: #475569;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .event-footer {
        border-top: 1px dashed #e2e8f0;
        padding: 10px 16px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f9fafb;
    }

    .event-capacity {
        font-size: 11px;
        color: #6b7280;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .capacity-bar {
        height: 6px;
        border-radius: 999px;
        background: #e2e8f0;
        width: 110px;
        overflow: hidden;
    }

    .capacity-fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(to right, var(--color-primary), #fb923c);
    }

    .event-cta-open {
        font-size: 12px;
        border-radius: 999px;
        padding: 6px 14px;
        border: none;
        background: #22c55e;
        color: white;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    .event-cta-wait {
        font-size: 12px;
        border-radius: 999px;
        padding: 6px 14px;
        border: 1px solid #e2e8f0;
        background: white;
        color: #6b7280;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .event-ribbon {
        position: absolute;
        top: 14px;
        right: -32px;
        background: #0f172a;
        color: #e5e7eb;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 6px 40px;
        transform: rotate(15deg);
        box-shadow: 0 10px 20px rgba(15,23,42,0.3);
        z-index: 2;
    }

    .event-ribbon.limited { background: #f97316; }

    /* STATISTICS */
    .stat-card {
        border-radius: 20px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 18px 18px 16px;
        height: 100%;
    }

    .stat-legend {
        list-style: none;
        padding: 0;
        margin: 8px 0 0;
        font-size: 11px;
    }

    .stat-legend li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 4px;
        gap: 6px;
    }

    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 6px;
        display: inline-block;
    }

    .flag-pill {
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 11px;
        background: white;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .bar-wrapper-fixed {
        position: relative;
        width: 100%;
        max-width: 100%;
        height: 220px;
        max-height: 220px;
    }

    /* RATING BREAKDOWN */
    .rating-breakdown-card {
        border-radius: 20px;
        background: #f9fafb;
        color: #111827;
        padding: 18px 20px;
        margin-top: 24px;
        border: 1px solid #e5e7eb;
    }

    .rating-main-score { font-size: 32px; font-weight: 800; line-height: 1; }
    .rating-stars { color: #facc15; font-size: 14px; }
    .rating-subtext { font-size: 11px; color: #6b7280; }

    .aspect-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .aspect-label {
        width: 150px;
        font-size: 11px;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .aspect-bar {
        flex: 1;
        height: 6px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }

    .aspect-fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(to right, #22c55e, #a3e635);
    }

    .aspect-score { width: 40px; text-align: right; font-size: 11px; color: #111827; }

    .rating-badge-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }

    .rating-badge {
        border-radius: 999px;
        padding: 4px 10px;
        background: white;
        color: #111827;
        font-size: 11px;
        border: 1px solid #e5e7eb;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .rating-trend {
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
        color: #16a34a;
        margin-top: 4px;
    }

    /* TIMELINE */
    .news-timeline {
        position: relative;
        padding-left: 30px;
    }

    .news-timeline::before {
        content: '';
        position: absolute;
        left: 12px;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(to bottom, #e2e8f0, #f1f5f9);
    }

    .timeline-date {
        position: relative;
        margin: 8px 0 12px;
        padding-left: 8px;
    }

    .timeline-date span {
        display: inline-block;
        background: #0f172a;
        color: #e5e7eb;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 999px;
        box-shadow: 0 4px 10px rgba(15,23,42,0.25);
    }

    .news-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        transition: 0.2s;
        position: relative;
    }

    .news-card:hover {
        box-shadow: 0 12px 24px rgba(0,0,0,0.08);
        transform: translateY(-2px);
    }

    .news-dot {
        position: absolute;
        left: -22px;
        top: 24px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: white;
        border: 3px solid var(--color-primary);
    }

    .news-header {
        padding: 20px 24px 16px;
        border-bottom: 1px solid #f1f5f9;
    }

    .news-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        object-fit: cover;
    }

    .news-content {
        padding: 0 24px 24px;
    }

    .news-img {
        width: 100%;
        height: 280px;
        object-fit: cover;
        border-radius: 16px;
        margin-bottom: 16px;
        margin-top: 16px;
    }

    .news-actions {
        border-top: 1px solid #f1f5f9;
        padding-top: 16px;
    }

    .news-action {
        color: #64748b;
        text-decoration: none;
        padding: 8px 12px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 14px;
        transition: 0.2s;
    }

    .news-action:hover {
        background: #f8fafc;
        color: var(--color-primary);
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .hero-banner { min-height: 400px; padding: 20px; }
        .club-logo-wrapper { width: 80px; height: 80px; }
        .banner-bottom { margin-bottom: 40px; }
        .stats-row { gap: 20px; }
        .stat-item h3 { font-size: 1.2rem; }
        .content-card { padding: 20px; margin-top: -40px; }
        .content-card .nav-pills .nav-link { padding: 10px 16px; font-size: 12px; }
        .news-img { height: 180px; }
        .class-card { flex-direction: column; }
        .class-thumb { width: 100%; height: 120px; }
    }
</style>
@endpush

@section('content')
<div class="page-container">
    {{-- HERO BANNER --}}
    <div class="hero-banner" style="--hero-bg: url('{{ $club->cover_image ? asset('storage/' . $club->cover_image) : ($club->galleryImages->first() ? asset('storage/' . $club->galleryImages->first()->image_path) : '') }}');">
        <div class="hero-bg-image"></div>

        {{-- Banner Top: Logo + Social Links --}}
        <div class="banner-top">
            <div>
                @if($club->logo)
                <div class="club-logo-wrapper">
                    <img src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}" style="width:100%">
                </div>
                @endif
            </div>
            <div class="glass-hub">
                @foreach($club->socialLinks as $link)
                    @php
                        $icon = 'bi-link-45deg';
                        $platform = strtolower($link->platform);
                        if (str_contains($platform, 'whatsapp')) $icon = 'bi-whatsapp';
                        elseif (str_contains($platform, 'instagram')) $icon = 'bi-instagram';
                        elseif (str_contains($platform, 'facebook')) $icon = 'bi-facebook';
                        elseif (str_contains($platform, 'twitter') || str_contains($platform, 'x')) $icon = 'bi-twitter-x';
                        elseif (str_contains($platform, 'youtube')) $icon = 'bi-youtube';
                        elseif (str_contains($platform, 'tiktok')) $icon = 'bi-tiktok';
                        elseif (str_contains($platform, 'linkedin')) $icon = 'bi-linkedin';
                    @endphp
                    <a href="{{ $link->url }}" target="_blank" class="hub-link" title="{{ $link->platform }}">
                        <i class="bi {{ $icon }}"></i>
                    </a>
                @endforeach
                @if($club->phone)
                <a href="tel:{{ is_array($club->phone) ? ($club->phone['number'] ?? '') : $club->phone }}" class="hub-link" title="Call">
                    <i class="bi bi-telephone"></i>
                </a>
                @endif
            </div>
        </div>

        {{-- Banner Bottom: Club Info + Stats --}}
        <div class="banner-bottom">
            <h1 class="text-2xl md:text-4xl font-extrabold text-white mb-2 uppercase">{{ $club->club_name }}</h1>
            <p class="text-white/50 text-lg">
                @if($club->address)
                <i class="bi bi-geo-alt-fill mr-2 text-primary"></i>{{ $club->address }}
                @endif
                @if($club->established_date)
                <i class="bi bi-fire ml-3 mr-2 text-primary"></i>
                <span>Since {{ \Carbon\Carbon::parse($club->established_date)->format('Y') }} &middot; {{ \Carbon\Carbon::parse($club->established_date)->diffInYears(now()) }} years</span>
                @endif
            </p>
            <div class="stats-row mt-4">
                <div class="stat-item">
                    <h3>{{ number_format($averageRating, 1) }}/5</h3>
                    <span>Rating</span>
                </div>
                <div class="stat-item">
                    <h3>{{ $club->peak_hours ?? '24/7' }}</h3>
                    <span>Access</span>
                </div>
                <div class="stat-item">
                    <h3>{{ $club->activities->count() }}+</h3>
                    <span>Classes</span>
                </div>
                <div class="stat-item">
                    <h3>{{ $activeMembersCount }}+</h3>
                    <span>Active Members</span>
                </div>
            </div>
        </div>
    </div>

    {{-- CONTENT CARD WITH TABS --}}
    <div class="content-card">
        {{-- Tab Navigation --}}
        <ul class="nav nav-pills nav-fill" id="mainTabs">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-overview">Overview</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-packages">Packages</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-scheduled">Schedule</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-events">Events</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-stats">Statistics</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-timeline">Timeline</button></li>
        </ul>

        <div class="tab-content">

            {{-- ==================== OVERVIEW TAB ==================== --}}
            <div class="tab-pane fade show active" id="tab-overview">

                {{-- Member Perks --}}
                <div class="mb-12">
                    <div class="flex justify-between items-end mb-4">
                        <div>
                            <h4 class="text-xl font-extrabold mb-1">Member Exclusive Perks</h4>
                            <p class="text-muted-foreground text-sm">Partnering businesses and offers available when you register.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="perk-card">
                            <span class="perk-badge">-20% OFF</span>
                            <div class="w-full h-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center">
                                <i class="bi bi-cup-hot text-white text-5xl"></i>
                            </div>
                            <div class="perk-overlay">
                                <h5 class="text-white font-bold mb-0">Partner Cafe</h5>
                                <p class="text-white/50 text-sm mb-0">Post-workout nutrition & coffee</p>
                            </div>
                        </div>
                        <div class="perk-card">
                            <span class="perk-badge">-15% OFF</span>
                            <div class="w-full h-full bg-gradient-to-br from-blue-400 to-cyan-500 flex items-center justify-center">
                                <i class="bi bi-bandaid text-white text-5xl"></i>
                            </div>
                            <div class="perk-overlay">
                                <h5 class="text-white font-bold mb-0">Physio Clinic</h5>
                                <p class="text-white/50 text-sm mb-0">Recovery & Sports Massage</p>
                            </div>
                        </div>
                        <div class="perk-card">
                            <span class="perk-badge">+500 PTS</span>
                            <div class="w-full h-full bg-gradient-to-br from-green-400 to-emerald-500 flex items-center justify-center">
                                <i class="bi bi-check2-circle text-white text-5xl"></i>
                            </div>
                            <div class="perk-overlay">
                                <h5 class="text-white font-bold mb-0">Daily Check-in</h5>
                                <p class="text-white/50 text-sm mb-0">Earn points for every workout</p>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-8 opacity-10">

                {{-- Trainers & Facilities --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {{-- Trainers --}}
                    <div>
                        <div class="flex justify-between items-end mb-4">
                            <div>
                                <h4 class="text-xl font-extrabold mb-1">Meet The Experts</h4>
                                <p class="text-muted-foreground text-sm">Our trainers with exceptional expertise</p>
                            </div>
                        </div>

                        @forelse($club->instructors as $instructor)
                        @php $trainerUser = $instructor->user; @endphp
                        <a href="{{ $trainerUser ? route('trainer.show', $instructor->id) : '#' }}" class="mini-trainer mb-3 block no-underline text-foreground">
                            @if($trainerUser && $trainerUser->profile_picture)
                            <img src="{{ asset('storage/' . $trainerUser->profile_picture) }}" class="mini-pfp" alt="{{ $trainerUser->full_name ?? $trainerUser->name }}">
                            @else
                            <div class="mini-pfp-placeholder">{{ strtoupper(substr($trainerUser->name ?? 'T', 0, 1)) }}</div>
                            @endif
                            <div>
                                <h6 class="font-bold mb-1">{{ $trainerUser->full_name ?? $trainerUser->name ?? 'Trainer' }}</h6>
                                <div class="flex items-center mb-1" style="font-size:11px;">
                                    <span class="mr-1 text-yellow-400">
                                        @php $rating = $instructor->reviews->avg('rating') ?? 0; @endphp
                                        @for($i = 1; $i <= 5; $i++)
                                            @if($i <= floor($rating))
                                                <i class="bi bi-star-fill"></i>
                                            @elseif($i - $rating < 1 && $i - $rating > 0)
                                                <i class="bi bi-star-half"></i>
                                            @else
                                                <i class="bi bi-star"></i>
                                            @endif
                                        @endfor
                                    </span>
                                    <span class="text-muted-foreground">{{ number_format($rating, 1) }} &middot; {{ $instructor->reviews->count() }} reviews</span>
                                </div>
                                @if($instructor->role && $instructor->role !== 'Instructor')
                                <p class="text-muted-foreground text-sm mb-0">{{ $instructor->role }}</p>
                                @elseif($instructor->bio)
                                <p class="text-muted-foreground text-sm mb-0">{{ Str::limit($instructor->bio, 60) }}</p>
                                @endif
                            </div>
                        </a>
                        @empty
                        <p class="text-muted-foreground text-center py-8">No trainers listed yet</p>
                        @endforelse
                    </div>

                    {{-- Facilities --}}
                    <div>
                        <div class="flex justify-between items-end mb-4">
                            <div>
                                <h4 class="text-xl font-extrabold mb-1">Elite Facilities</h4>
                                <p class="text-muted-foreground text-sm">State-of-the-art training environments</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            @forelse($club->facilities as $facility)
                            <div>
                                @if($facility->photo)
                                <img src="{{ asset('storage/' . $facility->photo) }}" class="fac-preview" alt="{{ $facility->name }}">
                                @else
                                <div class="fac-placeholder">
                                    <i class="bi bi-building text-white text-3xl"></i>
                                </div>
                                @endif
                                <h6 class="font-bold mb-1 mt-2">{{ $facility->name }}</h6>
                                @if($facility->description)
                                <p class="text-muted-foreground text-xs">{{ Str::limit($facility->description, 50) }}</p>
                                @endif
                            </div>
                            @empty
                            <div class="col-span-2 text-center py-8">
                                <p class="text-muted-foreground">No facilities listed yet</p>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <hr class="my-8 opacity-10">

                {{-- Achievements --}}
                <div>
                    <div class="flex justify-between items-end mb-4">
                        <div>
                            <h4 class="text-xl font-extrabold mb-1">Latest Achievements</h4>
                            <p class="text-muted-foreground text-sm">Celebrating our champions and club milestones.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="activity-item">
                            <h6 class="text-sm font-bold mb-1">Club of the Year</h6>
                            <p class="text-muted-foreground text-sm mb-2">Awarded for overall performance and growth.</p>
                            <span class="badge-pill bg-secondary-light">
                                <i class="bi bi-trophy mr-1"></i>Club Award
                            </span>
                        </div>
                        <div class="activity-item">
                            <h6 class="text-sm font-bold mb-1">Championship Medals</h6>
                            <p class="text-muted-foreground text-sm mb-2">Team podium finishes across divisions.</p>
                            <span class="badge-pill bg-secondary-light">
                                <i class="bi bi-award mr-1"></i>Tournament Medals
                            </span>
                        </div>
                        <div class="activity-item">
                            <h6 class="text-sm font-bold mb-1">Student Promotions</h6>
                            <p class="text-muted-foreground text-sm mb-2">Successful gradings this season.</p>
                            <span class="badge-pill bg-secondary-light">
                                <i class="bi bi-star mr-1"></i>Student Success
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== PACKAGES TAB ==================== --}}
            <div class="tab-pane fade" id="tab-packages">
                @if($club->packages->count() > 0)
                <div class="grid-packages">
                    @foreach($club->packages as $package)
                    <div class="package-card">
                        <div class="package-img-wrapper">
                            @if($package->cover_image)
                            <img src="{{ asset('storage/' . $package->cover_image) }}" class="package-img" alt="{{ $package->name }}">
                            @else
                            <div class="w-full h-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                                <i class="bi bi-box text-white text-5xl"></i>
                            </div>
                            @endif
                        </div>
                        <div class="p-4 flex-grow">
                            <h3 class="text-lg font-bold mb-2">{{ $package->name }}</h3>
                            <div class="flex flex-wrap gap-1 mb-3">
                                <span class="badge-pill bg-secondary-light">{{ $package->type ?? 'Package' }}</span>
                                @if($package->gender)
                                <span class="badge-pill"><i class="bi bi-people mr-1"></i>{{ ucfirst($package->gender) }}</span>
                                @endif
                                @if($package->age_min || $package->age_max)
                                <span class="badge-pill">{{ $package->age_min ?? '?' }}-{{ $package->age_max ?? '?' }}y</span>
                                @endif
                            </div>
                            <div class="mb-3">
                                @if($package->price)
                                <span class="text-2xl font-bold text-primary">{{ $club->currency ?? 'USD' }} {{ number_format($package->price, 0) }}</span>
                                @endif
                                @if($package->duration_months)
                                <span class="text-muted-foreground text-sm ml-2">
                                    <i class="bi bi-calendar mr-1"></i>{{ $package->duration_months }}mo
                                </span>
                                @endif
                            </div>

                            @if($package->packageActivities && $package->packageActivities->count() > 0)
                            <div class="pt-3 border-t border-gray-200">
                                <h6 class="text-sm font-bold mb-3">
                                    <i class="bi bi-box2 mr-2"></i>Included Activities ({{ $package->packageActivities->count() }})
                                </h6>
                                @foreach($package->packageActivities as $pa)
                                <div class="activity-item">
                                    <div class="flex justify-between items-start mb-2">
                                        <h6 class="text-sm font-bold mb-0">{{ $pa->activity->name ?? 'Activity' }}</h6>
                                        @if($pa->instructor)
                                        <div class="instructor-tag">
                                            @if($pa->instructor->photo)
                                            <img src="{{ asset('storage/' . $pa->instructor->photo) }}" class="instructor-img" alt="{{ $pa->instructor->name }}">
                                            @endif
                                            <span style="font-size: 9px; font-weight: 700; color: var(--color-primary);">{{ Str::before($pa->instructor->name, ' ') }}</span>
                                        </div>
                                        @endif
                                    </div>
                                    <div class="flex gap-3 text-muted-foreground" style="font-size: 10px;">
                                        @if($pa->activity && $pa->activity->duration_minutes)
                                        <span><i class="bi bi-clock mr-1"></i>{{ $pa->activity->duration_minutes }} min</span>
                                        @endif
                                        @if($pa->schedule)
                                        <span><i class="bi bi-calendar mr-1"></i>
                                            @if(is_array($pa->schedule))
                                                @foreach($pa->schedule as $sched)
                                                    {{ $sched['days'] ?? '' }}: {{ $sched['time_from'] ?? '' }} - {{ $sched['time_to'] ?? '' }}@if(!$loop->last), @endif
                                                @endforeach
                                            @endif
                                        </span>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        <div class="p-4 pt-0">
                            <button class="w-full bg-primary text-white font-bold py-2 shadow-sm rounded-xl hover:bg-primary/90 transition-colors">
                                Select Package
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-16">
                    <i class="bi bi-box text-muted-foreground text-5xl"></i>
                    <p class="text-lg font-medium mt-4">No packages available</p>
                    <p class="text-sm text-muted-foreground mt-2">Check back later for available packages</p>
                </div>
                @endif
            </div>

            {{-- ==================== SCHEDULE TAB ==================== --}}
            <div class="tab-pane fade" id="tab-scheduled">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h4 class="text-xl font-extrabold mb-1">Today's Classes</h4>
                        <p class="text-muted-foreground text-sm">Live status, flexible sessions, and booking availability.</p>
                    </div>
                </div>

                @if($club->activities->count() > 0)
                <div class="max-w-3xl mx-auto">
                    @foreach($club->activities as $index => $activity)
                    @php
                        $statuses = ['ongoing', 'bookable', 'finished', 'full'];
                        $status = $statuses[$index % 4];
                        $statusLabels = ['ongoing' => 'Live now', 'bookable' => 'Open', 'finished' => 'Finished', 'full' => 'Full'];
                    @endphp
                    <div class="class-card" @if($status === 'finished') style="opacity:0.5;" @endif>
                        <div class="class-thumb">
                            @if($activity->picture_url)
                            <img src="{{ asset('storage/' . $activity->picture_url) }}" alt="{{ $activity->name }}">
                            @else
                            <div class="w-full h-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center min-h-[80px]">
                                <i class="bi bi-activity text-white text-xl"></i>
                            </div>
                            @endif
                        </div>
                        <div class="flex-grow flex flex-col">
                            <div class="flex justify-between items-start mb-1">
                                <div>
                                    <h6 class="text-sm font-bold mb-0">{{ $activity->name }}</h6>
                                    <div class="class-meta text-muted-foreground">
                                        @if($activity->duration_minutes)
                                        <span><i class="bi bi-clock mr-1"></i>{{ $activity->duration_minutes }} min</span>
                                        @endif
                                        @if($activity->facility)
                                        <span class="ml-2"><i class="bi bi-geo-alt mr-1"></i>{{ $activity->facility->name }}</span>
                                        @endif
                                    </div>
                                </div>
                                <span class="status-chip status-{{ $status }}">
                                    @if($status === 'ongoing')
                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-green-600"></span>
                                    @elseif($status === 'full')
                                    <i class="bi bi-x-circle"></i>
                                    @elseif($status === 'finished')
                                    <i class="bi bi-clock"></i>
                                    @else
                                    <i class="bi bi-check-circle"></i>
                                    @endif
                                    {{ $statusLabels[$status] }}
                                </span>
                            </div>

                            <div class="flex justify-between items-center mt-2">
                                <div class="flex flex-wrap gap-1">
                                    @if($activity->packages->count() > 0)
                                    <span class="pill-tag">{{ $activity->packages->first()->name }}</span>
                                    @endif
                                    @if($activity->frequency_per_week)
                                    <span class="pill-tag">{{ $activity->frequency_per_week }}x/week</span>
                                    @endif
                                </div>
                                @if($status === 'ongoing' || $status === 'bookable')
                                <button class="bg-green-500 text-white text-xs font-semibold px-3 py-1 rounded-md">Book Spot</button>
                                @elseif($status === 'full')
                                <button class="border border-gray-300 text-gray-500 text-xs font-semibold px-3 py-1 rounded-md" disabled>Join Waitlist</button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-16">
                    <i class="bi bi-calendar-x text-muted-foreground text-5xl"></i>
                    <p class="text-lg font-medium mt-4">No classes scheduled</p>
                    <p class="text-sm text-muted-foreground mt-2">Check back later for available sessions</p>
                </div>
                @endif
            </div>

            {{-- ==================== EVENTS TAB ==================== --}}
            <div class="tab-pane fade" id="tab-events">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h4 class="text-xl font-extrabold mb-1">Upcoming Events</h4>
                        <p class="text-muted-foreground text-sm">Open to everyone. Reserve your spot before it sells out.</p>
                    </div>
                    <button class="border border-gray-800 text-sm font-semibold rounded-full px-3 py-1">
                        <i class="bi bi-calendar-plus mr-1"></i> Event Calendar
                    </button>
                </div>

                <div class="events-lane">
                    {{-- Event 1 --}}
                    <div class="event-node" style="top: 10px;"></div>
                    <article class="event-card mb-4">
                        <div class="event-ribbon limited">Limited Seats</div>
                        <div class="event-header">
                            <div class="event-date-pill">
                                <div class="day">Thu</div>
                                <div class="date">19</div>
                                <div class="month">Feb</div>
                            </div>
                            <div class="event-body-main">
                                <div class="event-title">Open Sparring Night - All Clubs Welcome</div>
                                <div class="event-meta mb-1">
                                    <span class="mr-3"><i class="bi bi-clock"></i> 7:30 PM - 9:30 PM</span>
                                    <span class="mr-3"><i class="bi bi-geo-alt"></i> Main Arena</span>
                                    <span><i class="bi bi-bar-chart"></i> Intermediate & Above</span>
                                </div>
                                <p class="text-xs text-gray-500 mb-0 mt-1">
                                    High-energy sparring rounds with referees, music and live scoring. Bring your gear, we bring the atmosphere.
                                </p>
                            </div>
                        </div>
                        <div class="event-chip-row">
                            <span class="event-chip"><i class="bi bi-people"></i> Public event</span>
                            <span class="event-chip"><i class="bi bi-shield"></i> WT rules</span>
                            <span class="event-chip"><i class="bi bi-camera"></i> Highlight reels</span>
                        </div>
                        <div class="event-footer">
                            <div class="event-capacity">
                                <i class="bi bi-people"></i>
                                <span>24 / 30 spots taken</span>
                                <div class="capacity-bar"><div class="capacity-fill" style="width:80%;"></div></div>
                            </div>
                            <button class="event-cta-open"><i class="bi bi-ticket"></i> Join Event</button>
                        </div>
                    </article>

                    {{-- Event 2 --}}
                    <div class="event-node" style="top: 250px;"></div>
                    <article class="event-card mb-4">
                        <div class="event-ribbon">Family Friendly</div>
                        <div class="event-header">
                            <div class="event-date-pill" style="background:#16a34a;">
                                <div class="day">Sat</div>
                                <div class="date">21</div>
                                <div class="month">Feb</div>
                            </div>
                            <div class="event-body-main">
                                <div class="event-title">Free Beginner Try-Out Day</div>
                                <div class="event-meta mb-1">
                                    <span class="mr-3"><i class="bi bi-clock"></i> 10:00 AM - 1:00 PM</span>
                                    <span class="mr-3"><i class="bi bi-geo-alt"></i> Dojo & Lobby</span>
                                    <span><i class="bi bi-person"></i> Ages 5+</span>
                                </div>
                                <p class="text-xs text-gray-500 mb-0 mt-1">
                                    Open doors for anyone curious. Meet the coaches, try a safe intro class, and tour the facility.
                                </p>
                            </div>
                        </div>
                        <div class="event-chip-row">
                            <span class="event-chip"><i class="bi bi-check-circle"></i> No experience needed</span>
                            <span class="event-chip"><i class="bi bi-cup-hot"></i> Coffee & snacks</span>
                            <span class="event-chip"><i class="bi bi-house"></i> Parents welcome</span>
                        </div>
                        <div class="event-footer">
                            <div class="event-capacity">
                                <i class="bi bi-infinity"></i>
                                <span>Unlimited guests</span>
                            </div>
                            <button class="event-cta-open" style="background:#0f172a;"><i class="bi bi-person-plus"></i> I'm Interested</button>
                        </div>
                    </article>

                    {{-- Event 3 --}}
                    <div class="event-node" style="top: 490px;"></div>
                    <article class="event-card mb-4">
                        <div class="event-ribbon limited">Grading</div>
                        <div class="event-header">
                            <div class="event-date-pill" style="background:#f97316;">
                                <div class="day">Fri</div>
                                <div class="date">28</div>
                                <div class="month">Feb</div>
                            </div>
                            <div class="event-body-main">
                                <div class="event-title">Monthly Belt Grading - Kids & Juniors</div>
                                <div class="event-meta mb-1">
                                    <span class="mr-3"><i class="bi bi-clock"></i> 5:00 PM - 8:00 PM</span>
                                    <span class="mr-3"><i class="bi bi-geo-alt"></i> Main Dojang</span>
                                    <span><i class="bi bi-person-badge"></i> Invite & open spectators</span>
                                </div>
                                <p class="text-xs text-gray-500 mb-0 mt-1">
                                    Formal grading with photo booth and awards stage. Families and friends are invited to cheer.
                                </p>
                            </div>
                        </div>
                        <div class="event-chip-row">
                            <span class="event-chip"><i class="bi bi-award"></i> White to Red Stripe</span>
                            <span class="event-chip"><i class="bi bi-camera-reels"></i> Professional photography</span>
                            <span class="event-chip"><i class="bi bi-star"></i> Medal ceremony</span>
                        </div>
                        <div class="event-footer">
                            <div class="event-capacity">
                                <i class="bi bi-people"></i>
                                <span>48 / 50 candidates</span>
                                <div class="capacity-bar"><div class="capacity-fill" style="width:96%;"></div></div>
                            </div>
                            <button class="event-cta-open" style="background:#f97316;"><i class="bi bi-clipboard-check"></i> Reserve Slot</button>
                        </div>
                    </article>

                    {{-- Event 4 --}}
                    <div class="event-node" style="top: 730px;"></div>
                    <article class="event-card mb-2">
                        <div class="event-ribbon limited">Almost Full</div>
                        <div class="event-header">
                            <div class="event-date-pill" style="background:#0369a1;">
                                <div class="day">Mon</div>
                                <div class="date">02</div>
                                <div class="month">Mar</div>
                            </div>
                            <div class="event-body-main">
                                <div class="event-title">Summer Camp 2026 - Preview Session</div>
                                <div class="event-meta mb-1">
                                    <span class="mr-3"><i class="bi bi-clock"></i> 6:30 PM - 8:00 PM</span>
                                    <span class="mr-3"><i class="bi bi-geo-alt"></i> Mixed Zones</span>
                                    <span><i class="bi bi-bicycle"></i> Camp activities demo</span>
                                </div>
                                <p class="text-xs text-gray-500 mb-0 mt-1">
                                    One-night preview of our signature summer camp: games, team-building, mini-workouts and Q&A.
                                </p>
                            </div>
                        </div>
                        <div class="event-chip-row">
                            <span class="event-chip"><i class="bi bi-fire"></i> Limited preview</span>
                            <span class="event-chip"><i class="bi bi-mortarboard"></i> Ideal for 8-14 yrs</span>
                        </div>
                        <div class="event-footer">
                            <div class="event-capacity">
                                <i class="bi bi-people"></i>
                                <span>Full &middot; Waitlist only</span>
                                <div class="capacity-bar"><div class="capacity-fill" style="width:100%;"></div></div>
                            </div>
                            <button class="event-cta-wait"><i class="bi bi-clock-history"></i> Join Waitlist</button>
                        </div>
                    </article>
                </div>
            </div>

            {{-- ==================== STATISTICS TAB ==================== --}}
            <div class="tab-pane fade" id="tab-stats">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h4 class="text-xl font-extrabold mb-1">Club Statistics</h4>
                        <p class="text-muted-foreground text-sm">Who trains with us, how they train, and how the club is growing.</p>
                    </div>
                    <span class="bg-gray-900 text-white text-xs font-semibold rounded-full px-3 py-2 flex items-center gap-2">
                        <i class="bi bi-bar-chart text-yellow-400"></i>
                        Live snapshot &middot; {{ now()->format('M Y') }}
                    </span>
                </div>

                {{-- Row 1: Nationality, Age, Gender --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    {{-- Nationality --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Active Members by Nationality
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutNationalities" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            @php $natColors = ['#ef4444', '#0ea5e9', '#22c55e', '#8b5cf6']; $ni = 0; @endphp
                            @foreach($nationalityStats as $country => $count)
                            <li>
                                <span><span class="legend-dot" style="background:{{ $natColors[$ni % 4] }};"></span> {{ $country ?: 'Unknown' }}</span>
                                <span>{{ $totalMembers > 0 ? round($count / $totalMembers * 100) : 0 }}%</span>
                            </li>
                            @php $ni++; @endphp
                            @endforeach
                        </ul>
                    </div>

                    {{-- Age Groups --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Members by Age Group
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutAgeGroups" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            @php $ageColors = ['#f97316', '#22c55e', '#3b82f6', '#94a3b8']; $ai = 0; @endphp
                            @foreach($ageGroups as $group => $count)
                            <li>
                                <span><span class="legend-dot" style="background:{{ $ageColors[$ai % 4] }};"></span> {{ $group }}</span>
                                <span>{{ $totalMembers > 0 ? round($count / $totalMembers * 100) : 0 }}%</span>
                            </li>
                            @php $ai++; @endphp
                            @endforeach
                        </ul>
                    </div>

                    {{-- Gender --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Gender Ratio
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutGender" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            @php $genderColors = ['#3b82f6', '#ec4899', '#94a3b8']; $gi = 0; @endphp
                            @foreach($genderStats as $gender => $count)
                            <li>
                                <span><span class="legend-dot" style="background:{{ $genderColors[$gi % 3] }};"></span> {{ ucfirst($gender ?: 'Unknown') }}</span>
                                <span>{{ $totalMembers > 0 ? round($count / $totalMembers * 100) : 0 }}%</span>
                            </li>
                            @php $gi++; @endphp
                            @endforeach
                        </ul>
                    </div>
                </div>

                {{-- Row 2: Horoscope, Blood Type, Championships --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    {{-- Horoscope --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Active Members - Horoscope
                            <span class="badge-pill bg-secondary-light">For fun</span>
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutHoroscope" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            @php $hColors = ['#6366f1', '#22c55e', '#0ea5e9', '#ec4899']; $hi = 0; @endphp
                            @foreach($horoscopeGroups as $element => $count)
                            <li>
                                <span><span class="legend-dot" style="background:{{ $hColors[$hi % 4] }};"></span> {{ $element }} signs</span>
                                <span>{{ $totalMembers > 0 ? round($count / $totalMembers * 100) : 0 }}%</span>
                            </li>
                            @php $hi++; @endphp
                            @endforeach
                        </ul>
                    </div>

                    {{-- Blood Type --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Members by Blood Type
                            <span class="badge-pill bg-secondary-light">Self-reported</span>
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutBloodType" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            @php $btColors = ['#ef4444', '#f97316', '#22c55e', '#3b82f6']; $bi_idx = 0; @endphp
                            @foreach($bloodTypeStats as $type => $count)
                            <li>
                                <span><span class="legend-dot" style="background:{{ $btColors[$bi_idx % 4] }};"></span> {{ $type }}</span>
                                <span>{{ $totalMembers > 0 ? round($count / $totalMembers * 100) : 0 }}%</span>
                            </li>
                            @php $bi_idx++; @endphp
                            @endforeach
                        </ul>
                    </div>

                    {{-- Championships (placeholder) --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Members with Championships
                            <span class="badge-pill bg-secondary-light">Achievements</span>
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutChampions" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            <li><span><span class="legend-dot" style="background:#22c55e;"></span> Medalists</span><span>18%</span></li>
                            <li><span><span class="legend-dot" style="background:#eab308;"></span> Podium finishes</span><span>24%</span></li>
                            <li><span><span class="legend-dot" style="background:#94a3b8;"></span> Competitors</span><span>28%</span></li>
                            <li><span><span class="legend-dot" style="background:#e5e7eb;"></span> Yet to compete</span><span>30%</span></li>
                        </ul>
                    </div>
                </div>

                {{-- Monthly Trend --}}
                <div class="grid grid-cols-1 gap-3">
                    <div class="stat-card">
                        <div class="flex justify-between items-center mb-2">
                            <h6 class="mb-0 text-sm font-bold">Active Members - Last 12 Months</h6>
                            <span class="bg-gray-900 text-white text-xs font-semibold rounded-full px-2 py-1 flex items-center gap-1">
                                <i class="bi bi-people"></i> 12-month trend
                            </span>
                        </div>
                        <p class="text-muted-foreground text-sm mb-2">Membership trends across seasons and holidays.</p>
                        <div class="bar-wrapper-fixed">
                            <canvas id="barMonthlyMembers"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Rating Breakdown --}}
                <div class="rating-breakdown-card">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <div class="md:col-span-3">
                            <div class="rating-main-score">{{ number_format($averageRating, 1) }}</div>
                            <div class="rating-stars">
                                @for($i = 1; $i <= 5; $i++)
                                    @if($i <= floor($averageRating))
                                        <i class="bi bi-star-fill"></i>
                                    @elseif($i - $averageRating < 1 && $i - $averageRating > 0)
                                        <i class="bi bi-star-half"></i>
                                    @else
                                        <i class="bi bi-star"></i>
                                    @endif
                                @endfor
                            </div>
                            <div class="rating-subtext mt-1">Based on {{ $reviews->count() }} verified member reviews</div>
                        </div>
                        <div class="md:col-span-6">
                            <div class="aspect-row">
                                <div class="aspect-label"><i class="bi bi-person-check text-green-500"></i> Trainers</div>
                                <div class="aspect-bar"><div class="aspect-fill" style="width:{{ min($averageRating * 20, 100) }}%;"></div></div>
                                <div class="aspect-score">{{ number_format($averageRating, 1) }}</div>
                            </div>
                            <div class="aspect-row">
                                <div class="aspect-label"><i class="bi bi-droplet text-sky-500"></i> Cleanliness</div>
                                <div class="aspect-bar"><div class="aspect-fill" style="width:{{ min(($averageRating - 0.1) * 20, 100) }}%;"></div></div>
                                <div class="aspect-score">{{ number_format(max($averageRating - 0.1, 0), 1) }}</div>
                            </div>
                            <div class="aspect-row">
                                <div class="aspect-label"><i class="bi bi-house text-indigo-400"></i> Comfort</div>
                                <div class="aspect-bar"><div class="aspect-fill" style="width:{{ min(($averageRating - 0.2) * 20, 100) }}%;"></div></div>
                                <div class="aspect-score">{{ number_format(max($averageRating - 0.2, 0), 1) }}</div>
                            </div>
                            <div class="aspect-row">
                                <div class="aspect-label"><i class="bi bi-bullseye text-amber-400"></i> Keeps on track</div>
                                <div class="aspect-bar"><div class="aspect-fill" style="width:{{ min(($averageRating - 0.1) * 20, 100) }}%;"></div></div>
                                <div class="aspect-score">{{ number_format(max($averageRating - 0.1, 0), 1) }}</div>
                            </div>
                            <div class="aspect-row mb-0">
                                <div class="aspect-label"><i class="bi bi-heart text-rose-400"></i> Community vibe</div>
                                <div class="aspect-bar"><div class="aspect-fill" style="width:{{ min($averageRating * 20, 100) }}%;"></div></div>
                                <div class="aspect-score">{{ number_format($averageRating, 1) }}</div>
                            </div>
                        </div>
                        <div class="md:col-span-3">
                            <div class="rating-badge-row">
                                <span class="rating-badge"><i class="bi bi-emoji-smile text-yellow-400"></i> 9.7 / 10 enjoyment</span>
                                <span class="rating-badge"><i class="bi bi-shield-check text-green-500"></i> Members feel safe</span>
                                <span class="rating-badge"><i class="bi bi-stopwatch text-sky-500"></i> 92% better discipline</span>
                            </div>
                            <div class="rating-trend">
                                <i class="bi bi-graph-up-arrow"></i>
                                Rating up +0.2 vs last year
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== TIMELINE TAB ==================== --}}
            <div class="tab-pane fade" id="tab-timeline">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h4 class="text-xl font-extrabold mb-1">Club Timeline</h4>
                        <p class="text-muted-foreground text-sm">Daily moments, announcements, and highlights.</p>
                    </div>
                </div>

                <div class="news-timeline">
                    <div class="timeline-date"><span>{{ now()->format('d M Y') }}</span></div>

                    {{-- Post 1 --}}
                    <article class="news-card">
                        <span class="news-dot"></span>
                        <div class="news-header flex items-center gap-3">
                            @if($club->logo)
                            <img class="news-avatar" src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}">
                            @else
                            <div class="w-[42px] h-[42px] rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                {{ strtoupper(substr($club->club_name, 0, 1)) }}
                            </div>
                            @endif
                            <div>
                                <h6 class="mb-0 font-bold text-sm">{{ $club->club_name }}</h6>
                                <small class="text-muted-foreground">Just now &middot; {{ $club->address ?? 'Announcement' }}</small>
                            </div>
                        </div>
                        <div class="news-content">
                            @if($club->galleryImages->count() > 0)
                            <img class="news-img" src="{{ asset('storage/' . $club->galleryImages->first()->image_path) }}" alt="Club photo">
                            @endif
                            <p class="mb-2 text-sm">
                                What a season! Our team brought home incredible results. Proud of every athlete who stepped up and represented {{ $club->club_name }}.
                            </p>
                            <div class="news-actions">
                                <a href="#" class="news-action"><i class="bi bi-heart"></i> 142</a>
                                <a href="#" class="news-action"><i class="bi bi-chat"></i> 18</a>
                                <a href="#" class="news-action"><i class="bi bi-share"></i> Share</a>
                            </div>
                        </div>
                    </article>

                    {{-- Post 2 --}}
                    <article class="news-card">
                        <span class="news-dot"></span>
                        <div class="news-header flex items-center gap-3">
                            @if($club->logo)
                            <img class="news-avatar" src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}">
                            @else
                            <div class="w-[42px] h-[42px] rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                {{ strtoupper(substr($club->club_name, 0, 1)) }}
                            </div>
                            @endif
                            <div>
                                <h6 class="mb-0 font-bold text-sm">{{ $club->club_name }}</h6>
                                <small class="text-muted-foreground">1 hour ago &middot; Announcement</small>
                            </div>
                        </div>
                        <div class="news-content">
                            @if($club->galleryImages->count() > 1)
                            <img class="news-img" src="{{ asset('storage/' . $club->galleryImages->skip(1)->first()->image_path) }}" alt="Club photo">
                            @endif
                            <p class="mb-2 text-sm">
                                New beginners class launching next week. Build confidence, discipline, and quality time. Perfect for newcomers of all ages!
                            </p>
                            <div class="news-actions">
                                <a href="#" class="news-action"><i class="bi bi-heart"></i> 96</a>
                                <a href="#" class="news-action"><i class="bi bi-chat"></i> 12</a>
                                <a href="#" class="news-action"><i class="bi bi-share"></i> Share</a>
                            </div>
                        </div>
                    </article>

                    <div class="timeline-date"><span>{{ now()->subDay()->format('d M Y') }}</span></div>

                    {{-- Post 3 --}}
                    <article class="news-card">
                        <span class="news-dot"></span>
                        <div class="news-header flex items-center gap-3">
                            @if($club->logo)
                            <img class="news-avatar" src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}">
                            @else
                            <div class="w-[42px] h-[42px] rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                {{ strtoupper(substr($club->club_name, 0, 1)) }}
                            </div>
                            @endif
                            <div>
                                <h6 class="mb-0 font-bold text-sm">{{ $club->club_name }}</h6>
                                <small class="text-muted-foreground">Yesterday &middot; Highlight</small>
                            </div>
                        </div>
                        <div class="news-content">
                            @if($club->galleryImages->count() > 2)
                            <img class="news-img" src="{{ asset('storage/' . $club->galleryImages->skip(2)->first()->image_path) }}" alt="Club photo">
                            @endif
                            <p class="mb-2 text-sm">
                                Congratulations to our newest members who passed their assessments with distinction. Years of hard work paying off!
                            </p>
                            <div class="news-actions">
                                <a href="#" class="news-action"><i class="bi bi-heart"></i> 210</a>
                                <a href="#" class="news-action"><i class="bi bi-chat"></i> 34</a>
                                <a href="#" class="news-action"><i class="bi bi-share"></i> Share</a>
                            </div>
                        </div>
                    </article>

                    <div class="timeline-date"><span>{{ now()->subDays(2)->format('d M Y') }}</span></div>

                    {{-- Post 4 --}}
                    <article class="news-card">
                        <span class="news-dot"></span>
                        <div class="news-header flex items-center gap-3">
                            @if($club->logo)
                            <img class="news-avatar" src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}">
                            @else
                            <div class="w-[42px] h-[42px] rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                {{ strtoupper(substr($club->club_name, 0, 1)) }}
                            </div>
                            @endif
                            <div>
                                <h6 class="mb-0 font-bold text-sm">{{ $club->club_name }}</h6>
                                <small class="text-muted-foreground">2 days ago &middot; Community</small>
                            </div>
                        </div>
                        <div class="news-content">
                            <p class="mb-2 text-sm">
                                Reminder: Monthly grading is coming up. Please confirm your attendance with reception and ensure you've completed your attendance requirements.
                            </p>
                            <div class="news-actions">
                                <a href="#" class="news-action"><i class="bi bi-heart"></i> 52</a>
                                <a href="#" class="news-action"><i class="bi bi-chat"></i> 5</a>
                                <a href="#" class="news-action"><i class="bi bi-share"></i> Share</a>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // --- Chart Data from Controller ---
    const nationalityLabels = @json($nationalityStats->keys()->toArray());
    const nationalityData = @json($nationalityStats->values()->toArray());
    const ageLabels = @json(array_keys($ageGroups));
    const ageData = @json(array_values($ageGroups));
    const genderLabels = @json($genderStats->keys()->map(fn($g) => ucfirst($g ?: 'Unknown'))->toArray());
    const genderData = @json($genderStats->values()->toArray());
    const horoscopeLabels = @json(array_keys($horoscopeGroups));
    const horoscopeData = @json(array_values($horoscopeGroups));
    const bloodLabels = @json($bloodTypeStats->keys()->toArray());
    const bloodData = @json($bloodTypeStats->values()->toArray());
    const monthlyLabels = @json(array_keys($monthlyTrend));
    const monthlyData = @json(array_values($monthlyTrend));

    const donutOptions = {
        cutout: '65%',
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.parsed}` } }
        }
    };

    // Nationality
    const natCtx = document.getElementById('donutNationalities');
    if (natCtx) {
        new Chart(natCtx, {
            type: 'doughnut',
            data: {
                labels: nationalityLabels,
                datasets: [{ data: nationalityData, backgroundColor: ['#ef4444','#0ea5e9','#22c55e','#8b5cf6'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Age Groups
    const ageCtx = document.getElementById('donutAgeGroups');
    if (ageCtx) {
        new Chart(ageCtx, {
            type: 'doughnut',
            data: {
                labels: ageLabels,
                datasets: [{ data: ageData, backgroundColor: ['#f97316','#22c55e','#3b82f6','#94a3b8'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Gender
    const genderCtx = document.getElementById('donutGender');
    if (genderCtx) {
        new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: genderLabels,
                datasets: [{ data: genderData, backgroundColor: ['#3b82f6','#ec4899','#94a3b8'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Horoscope
    const horoCtx = document.getElementById('donutHoroscope');
    if (horoCtx) {
        new Chart(horoCtx, {
            type: 'doughnut',
            data: {
                labels: horoscopeLabels,
                datasets: [{ data: horoscopeData, backgroundColor: ['#6366f1','#22c55e','#0ea5e9','#ec4899'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Blood Type
    const bloodCtx = document.getElementById('donutBloodType');
    if (bloodCtx) {
        new Chart(bloodCtx, {
            type: 'doughnut',
            data: {
                labels: bloodLabels,
                datasets: [{ data: bloodData, backgroundColor: ['#ef4444','#f97316','#22c55e','#3b82f6'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Championships (placeholder)
    const champCtx = document.getElementById('donutChampions');
    if (champCtx) {
        new Chart(champCtx, {
            type: 'doughnut',
            data: {
                labels: ['Medalists', 'Podium finishes', 'Competitors', 'Yet to compete'],
                datasets: [{ data: [18, 24, 28, 30], backgroundColor: ['#22c55e','#eab308','#94a3b8','#e5e7eb'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Monthly Bar Chart
    const barCtx = document.getElementById('barMonthlyMembers');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Members',
                    data: monthlyData,
                    backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim(),
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#6b7280', font: { size: 11 } } },
                    y: { grid: { color: '#e5e7eb' }, ticks: { color: '#6b7280', font: { size: 11 }, stepSize: 10 } }
                },
                plugins: {
                    legend: { labels: { font: { size: 11 }, color: '#374151' } }
                }
            }
        });
    }
});
</script>
@endpush
