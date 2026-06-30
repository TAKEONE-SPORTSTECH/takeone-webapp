<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Link - {{ config('app.name', 'TAKEONE') }}</title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background-color: #f4f2fb; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(143, 111, 218, 0.12); overflow: hidden; }
        .header { padding: 40px 30px; text-align: center; background: linear-gradient(135deg, #8f6fda 0%, #6b46c1 100%); }
        .header h1 { color: #ffffff; margin: 0 0 6px 0; font-size: 28px; font-weight: 700; letter-spacing: 2px; }
        .header p { color: rgba(255, 255, 255, 0.85); margin: 0; font-size: 15px; }
        .body { padding: 36px 30px; color: #374151; font-size: 15px; line-height: 1.6; }
        .body h2 { font-size: 19px; color: #111827; margin: 0 0 14px; }
        .btn-wrap { text-align: center; margin: 30px 0; }
        .btn { display: inline-block; background: linear-gradient(135deg, #8f6fda 0%, #6b46c1 100%); color: #ffffff !important; text-decoration: none; padding: 15px 40px; border-radius: 12px; font-size: 16px; font-weight: 600; }
        .note { font-size: 13px; color: #9ca3af; margin-top: 22px; }
        .fallback { word-break: break-all; font-size: 12px; color: #8f6fda; }
        .footer { padding: 20px 30px 32px; text-align: center; color: #9ca3af; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name', 'TAKEONE') }}</h1>
            <p>Your secure login link</p>
        </div>
        <div class="body">
            <h2>Hi {{ $user->full_name ?? 'there' }},</h2>
            <p>Tap the button below to sign in to your account. No password needed — this link signs you in instantly.</p>
            <div class="btn-wrap">
                <a href="{{ $loginUrl }}" class="btn">Log me in</a>
            </div>
            <p class="note">This link expires in 30 minutes and can only be used from this email. If you didn't request it, you can safely ignore this message — your account stays secure.</p>
            <p class="note">Button not working? Paste this link into your browser:</p>
            <p class="fallback">{{ $loginUrl }}</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name', 'TAKEONE') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
