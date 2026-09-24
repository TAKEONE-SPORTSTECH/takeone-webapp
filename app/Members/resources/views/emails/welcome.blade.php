<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Verify your email · {{ config('app.name', 'TAKEONE') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f1fb; font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased;">
    <!-- Preheader (hidden) -->
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">Confirm your email to activate your {{ config('app.name', 'TAKEONE') }} account.</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f1fb; padding:28px 16px;">
        <tr>
            <td align="center">
                <!-- Card -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:540px; background-color:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 10px 40px rgba(124,92,214,0.14);">

                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#8f6fda 0%,#6b46c1 100%); padding:40px 32px 34px; text-align:center;">
                            <div style="width:74px; height:74px; margin:0 auto 18px; background:rgba(255,255,255,0.16); border-radius:22px; line-height:74px; text-align:center;">
                                <span style="font-size:34px;">✉️</span>
                            </div>
                            <h1 style="margin:0; color:#ffffff; font-size:24px; font-weight:800; letter-spacing:0.04em;">{{ strtoupper(config('app.name', 'TAKEONE')) }}</h1>
                            <p style="margin:6px 0 0; color:rgba(255,255,255,0.82); font-size:14px;">One last step to get started</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:36px 32px 8px;">
                            <p style="margin:0 0 6px; font-size:16px; color:#6b7280;">Hi {{ $user->full_name }},</p>
                            <h2 style="margin:0 0 14px; font-size:22px; font-weight:800; color:#1f2937;">Welcome to {{ config('app.name', 'TAKEONE') }} 👋</h2>
                            <p style="margin:0; font-size:15px; line-height:1.7; color:#4b5563;">
                                Your account is ready. Just confirm your email address using the button below and you'll be signed in — no password needed.
                            </p>

                            @if($guardian && $relationship)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0 4px; background-color:#f7f5fe; border:1px solid #ece8fb; border-radius:16px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0 0 10px; font-size:12px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; color:#8f6fda;">Family information</p>
                                        <p style="margin:0 0 4px; font-size:14px; color:#374151;"><strong style="color:#1f2937;">Guardian:</strong> {{ $guardian->full_name }}</p>
                                        <p style="margin:0 0 4px; font-size:14px; color:#374151;"><strong style="color:#1f2937;">Relationship:</strong> {{ $relationship->relationship_type === 'spouse' ? 'Wife' : ucfirst($relationship->relationship_type) }}</p>
                                        @if($user->birthdate)
                                        <p style="margin:0; font-size:14px; color:#374151;"><strong style="color:#1f2937;">Birthdate:</strong> {{ \Carbon\Carbon::parse($user->birthdate)->format('F j, Y') }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            @endif
                        </td>
                    </tr>

                    <!-- Button -->
                    <tr>
                        <td style="padding:24px 32px 8px; text-align:center;">
                            <table role="presentation" cellpadding="0" cellspacing="0" align="center">
                                <tr>
                                    <td style="border-radius:16px; background:linear-gradient(135deg,#8f6fda 0%,#6b46c1 100%); box-shadow:0 8px 22px -6px rgba(107,70,193,0.6);">
                                        <a href="{{ $verificationUrl }}" target="_blank"
                                           style="display:inline-block; padding:16px 44px; font-size:16px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:16px;">
                                            Verify my email
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:18px 0 0; font-size:12px; color:#9ca3af;">This link expires in 60 minutes.</p>
                        </td>
                    </tr>

                    <!-- Fallback link -->
                    <tr>
                        <td style="padding:22px 32px 6px;">
                            <div style="background-color:#f9fafb; border-radius:14px; padding:14px 16px;">
                                <p style="margin:0 0 6px; font-size:12px; color:#9ca3af;">Button not working? Copy and paste this link:</p>
                                <a href="{{ $verificationUrl }}" style="font-size:12px; color:#8f6fda; word-break:break-all; text-decoration:none;">{{ $verificationUrl }}</a>
                            </div>
                            <p style="margin:18px 0 0; text-align:center; font-size:12px; color:#9ca3af; line-height:1.6;">
                                If you didn't create this account, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:26px 32px 34px; text-align:center;">
                            <div style="height:1px; background-color:#f0eefb; margin-bottom:20px;"></div>
                            <p style="margin:0; font-size:12px; color:#b3acc9;">&copy; {{ date('Y') }} {{ config('app.name', 'TAKEONE') }}. All rights reserved.</p>
                            <p style="margin:8px 0 0; font-size:12px;">
                                <a href="{{ url('/') }}" style="color:#8f6fda; text-decoration:none;">Visit website</a>
                                <span style="color:#d6d1ea;">&nbsp;·&nbsp;</span>
                                <a href="mailto:{{ config('mail.from.address') }}" style="color:#8f6fda; text-decoration:none;">Contact support</a>
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="max-width:540px; margin:18px auto 0; font-size:11px; color:#b3acc9; text-align:center; line-height:1.6;">
                    You're receiving this because an account was created with this email at {{ config('app.name', 'TAKEONE') }}.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
