{{--
    The entrant's confirmation — white-labelled to the COMPETITION.

    Deliberately unlike the platform's own emails (emails/magic-login and
    friends), which lead with the TAKEONE mark: the person reading this was sent
    a competition and has no idea what is serving it, so the header is the
    event's own colour, the name is the event's, and the footer credits the host
    club. Nothing here says TAKEONE (entry/layout.blade.php, same rule).

    Inline CSS and a table-free single column, because that is what mail clients
    render reliably. The event colour is a SOLID fill, not the poster's
    gradient: Outlook drops `linear-gradient` entirely and the header would come
    out white-on-white.
--}}
@php
    use App\Support\Palette;

    $ev = Palette::safe($event->color ?? null);
    $club = trim((string) ($event->tenant?->club_name ?? ''));

    $when = $event->date ? \App\Support\Cldr::fullDate($event->date) : null;
    $time = $event->start_time ? \Carbon\Carbon::parse($event->start_time)->format('g:i A') : null;
    $venue = trim((string) ($event->location ?? ''));

    $heading = match ($state) {
        \App\Mail\EventEntryEmail::ENTERED => __('events.entry_mail_head_entered'),
        \App\Mail\EventEntryEmail::DECLINED => __('events.entry_mail_head_declined'),
        default => __('events.entry_mail_head_received'),
    };

    $lead = match ($state) {
        \App\Mail\EventEntryEmail::ENTERED => __('events.entry_mail_lead_entered'),
        \App\Mail\EventEntryEmail::DECLINED => __('events.entry_mail_lead_declined', ['club' => $club]),
        default => __('events.entry_mail_lead_received'),
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event->title }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background-color:#f4f5f9; margin:0; padding:20px;">
    <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 4px 20px rgba(30,44,79,.10);">

        {{-- The event's own header. Solid fill — see the note above. --}}
        <div style="padding:32px 30px; background:{{ $ev }}; color:#ffffff;">
            <div style="width:38px; height:3px; border-radius:2px; background:rgba(255,255,255,.85); margin-bottom:14px;"></div>
            <h1 style="margin:0; font-size:23px; line-height:1.25; font-weight:700; color:#ffffff;">{{ $event->title }}</h1>
            @if($club)
                <p style="margin:8px 0 0; font-size:13px; color:rgba(255,255,255,.85);">{{ $club }}</p>
            @endif
        </div>

        <div style="padding:30px; color:#374151; font-size:15px; line-height:1.6;">
            <h2 style="margin:0 0 12px; font-size:19px; color:#111827;">{{ $heading }}</h2>
            <p style="margin:0 0 18px;">{{ $athlete->full_name }}, {{ $lead }}</p>

            {{-- The facts, one per line. Only the ones that exist: an organiser
                 sets a venue and a division when they are ready, and printing an
                 empty label would read as information. --}}
            <div style="border:1px solid #eef1f6; border-radius:12px; padding:4px 16px; margin:0 0 22px;">
                @foreach ([
                    [__('events.entry_mail_when'), trim(($when ?? '').($time ? ' · '.$time : ''))],
                    [__('events.entry_mail_where'), $venue],
                    [__('events.entry_bouts_division'), $division],
                    [__('events.entry_mail_owed'), $owed],
                ] as [$label, $value])
                    @if(trim((string) $value) !== '')
                        <p style="margin:12px 0; font-size:14px;">
                            <span style="display:block; font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#6b7689;">{{ $label }}</span>
                            <span style="display:block; color:#1e2c4f; font-weight:600;">{{ $value }}</span>
                        </p>
                    @endif
                @endforeach
            </div>

            @if($state === \App\Mail\EventEntryEmail::ENTERED && trim((string) $payHow) !== '')
                <p style="margin:0 0 22px; font-size:13.5px; color:#6b7689;">{{ $payHow }}</p>
            @endif

            @if($entryUrl)
                <div style="text-align:center; margin:26px 0;">
                    <a href="{{ $entryUrl }}"
                       style="display:inline-block; background:{{ $ev }}; color:#ffffff !important; text-decoration:none; padding:14px 34px; border-radius:12px; font-size:15px; font-weight:600;">
                        {{ $state === \App\Mail\EventEntryEmail::ENTERED ? __('events.entry_mail_cta_entry') : __('events.entry_mail_cta_event') }}
                    </a>
                </div>

                {{-- The link in text too. A competitor reads this on a phone in
                     a hall where the button may not survive the client. --}}
                <p style="margin:0; font-size:12px; color:#9ca3af;">{{ __('events.entry_mail_fallback') }}</p>
                <p style="margin:4px 0 0; font-size:12px; word-break:break-all; color:{{ $ev }};">{{ $entryUrl }}</p>
            @endif
        </div>

        @if($club)
            <div style="padding:18px 30px 28px; text-align:center; color:#9ca3af; font-size:12px;">
                {{ __('events.public_organised_by') }} {{ $club }}
            </div>
        @endif
    </div>
</body>
</html>
