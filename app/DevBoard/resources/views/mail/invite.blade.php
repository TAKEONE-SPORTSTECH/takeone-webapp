{{-- TakeNote's invitation (App\DevBoard\Mail\TakeNoteInvite): the night desk and its violet, on paper. Tables + inline styles, as email needs. --}}
@php $rtl = app()->getLocale() === 'ar'; $align = $rtl ? 'right' : 'left'; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>{{ __('devboard::messages.mi_subject', ['name' => $inviter]) }}</title>
</head>
<body style="margin:0; padding:0; background-color:#160f24; font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,'Noto Sans','IBM Plex Sans Arabic','Hiragino Sans','PingFang SC',sans-serif; -webkit-font-smoothing:antialiased;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ __('devboard::messages.mi_preheader') }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#160f24; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">
                    {{-- the band: TakeNote, and who is asking --}}
                    <tr>
                        <td style="background-color:#7c6bf5; background-image:linear-gradient(150deg,#7c6bf5,#5b4bd6); border-radius:24px 24px 0 0; padding:34px 32px 30px; text-align:{{ $align }};">
                            <table role="presentation" cellpadding="0" cellspacing="0" align="{{ $align }}">
                                <tr>
                                    <td style="vertical-align:middle;"><img src="{{ asset('images/takenote/icon-192.png') }}" width="40" height="40" alt="" style="display:block; border-radius:11px;"></td>
                                    <td style="vertical-align:middle; padding:0 12px; color:#ffffff; font-size:15px; font-weight:800; letter-spacing:.01em;">TakeNote</td>
                                </tr>
                            </table>
                            <div style="clear:both; height:22px; line-height:22px;">&nbsp;</div>
                            <h1 style="margin:0; color:#ffffff; font-size:26px; line-height:1.2; font-weight:800; letter-spacing:-.01em;">{{ __('devboard::messages.mi_title', ['name' => $inviter]) }}</h1>
                        </td>
                    </tr>

                    {{-- the paper: what it is, and how in --}}
                    <tr>
                        <td style="background-color:#fbf7ef; padding:30px 32px 10px; text-align:{{ $align }};">
                            <p style="margin:0; font-size:15.5px; line-height:1.7; color:#1d1530;">{{ __('devboard::messages.mi_body', ['name' => $inviter]) }}</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0 4px; background-color:#f3ecdf; border-radius:16px;">
                                <tr>
                                    <td style="padding:16px 18px; text-align:{{ $align }};">
                                        <p style="margin:0 0 6px; font-size:12px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#5b4bd6;">{{ __('devboard::messages.mi_how') }}</p>
                                        <p style="margin:0 0 4px; font-size:14px; line-height:1.6; color:#1d1530;">{{ __('devboard::messages.mi_step1') }}</p>
                                        <p dir="ltr" style="margin:0 0 8px; font-size:15px; font-weight:700; color:#1d1530; word-break:break-all; text-align:{{ $align }};">{{ $email }}</p>
                                        <p style="margin:0; font-size:13px; line-height:1.6; color:#6b6280;">{{ __('devboard::messages.mi_step2') }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#fbf7ef; padding:18px 32px 30px; text-align:center;">
                            <a href="{{ $link }}" style="display:inline-block; background-color:#7c6bf5; color:#ffffff; text-decoration:none; font-size:16px; font-weight:800; padding:15px 34px; border-radius:14px;">{{ __('devboard::messages.mi_button') }}</a>
                            <p dir="ltr" style="margin:14px 0 0; font-size:12px; color:#8a8199; word-break:break-all;">{{ $link }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#fbf7ef; border-radius:0 0 24px 24px; border-top:1px solid #ece6d9; padding:18px 32px 24px; text-align:{{ $align }};">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#8a8199;">{{ __('devboard::messages.mi_foot', ['name' => $inviter]) }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
