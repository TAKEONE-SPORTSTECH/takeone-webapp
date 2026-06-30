<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Your verification code · {{ config('app.name', 'TAKEONE') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f1fb; font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">Your {{ config('app.name', 'TAKEONE') }} verification code is {{ $code }}.</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f1fb; padding:28px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:540px; background-color:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 10px 40px rgba(124,92,214,0.14);">

                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#8f6fda 0%,#6b46c1 100%); padding:40px 32px 34px; text-align:center;">
                            <div style="width:74px; height:74px; margin:0 auto 18px; background:rgba(255,255,255,0.16); border-radius:22px; line-height:74px; text-align:center;">
                                <span style="font-size:34px;">🔐</span>
                            </div>
                            <h1 style="margin:0; color:#ffffff; font-size:24px; font-weight:800; letter-spacing:0.04em;">{{ strtoupper(config('app.name', 'TAKEONE')) }}</h1>
                            <p style="margin:6px 0 0; color:rgba(255,255,255,0.82); font-size:14px;">Verify it's really you</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:34px 32px 12px;">
                            <p style="margin:0 0 14px; color:#1f2937; font-size:16px;">Hi {{ $name }},</p>
                            <p style="margin:0 0 24px; color:#4b5563; font-size:14px; line-height:1.7;">
                                We found an existing account for this email. Enter the code below to continue your registration and add family members linked to your account.
                            </p>

                            <!-- Code -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding:6px 0 22px;">
                                        <div style="display:inline-block; background:#f3f1fb; border:1px solid #e4defa; border-radius:16px; padding:18px 28px;">
                                            <span style="font-size:34px; font-weight:800; letter-spacing:0.42em; color:#6b46c1;">{{ $code }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px; color:#9ca3af; font-size:13px; line-height:1.7;">
                                This code expires in 10 minutes. If you didn't try to register, you can safely ignore this email — no changes will be made to your account.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px 32px;">
                            <hr style="border:none; border-top:1px solid #f0eefb; margin:0 0 16px;">
                            <p style="margin:0; color:#b8b3c9; font-size:12px; text-align:center;">© {{ date('Y') }} {{ config('app.name', 'TAKEONE') }}. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
