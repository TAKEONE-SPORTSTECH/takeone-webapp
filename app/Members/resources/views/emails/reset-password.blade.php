<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - {{ config('app.name', 'TAKEONE') }}</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f4f2fb;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(143, 111, 218, 0.12);
            overflow: hidden;
        }
        .header {
            padding: 40px 30px;
            text-align: center;
            background: linear-gradient(135deg, #8f6fda 0%, #6b46c1 100%);
        }
        .header h1 {
            color: #ffffff;
            margin: 0 0 6px 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 2px;
        }
        .header p {
            color: rgba(255, 255, 255, 0.85);
            margin: 0;
            font-size: 15px;
        }
        .icon-wrap {
            text-align: center;
            margin: 30px 0 10px 0;
        }
        .icon-circle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background-color: rgba(143, 111, 218, 0.1);
        }
        .content {
            padding: 10px 30px 40px 30px;
        }
        .content h2 {
            color: #333333;
            font-size: 20px;
            text-align: center;
            margin-top: 16px;
            margin-bottom: 8px;
        }
        .content p {
            color: #666666;
            line-height: 1.6;
            margin-bottom: 16px;
            text-align: center;
        }
        .divider {
            height: 1px;
            background-color: #ede9f8;
            margin: 24px 0;
        }
        .button-container {
            text-align: center;
            margin: 24px 0;
        }
        .button {
            display: inline-block;
            background-color: #8f6fda;
            color: #ffffff;
            text-decoration: none;
            padding: 14px 40px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.5px;
        }
        .note {
            text-align: center;
            color: #aaaaaa;
            font-size: 13px;
            margin-top: 20px;
        }
        .footer {
            background-color: #f4f2fb;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #ede9f8;
        }
        .footer p {
            color: #999999;
            font-size: 12px;
            margin: 0;
        }
        .footer a {
            color: #8f6fda;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ strtoupper(config('app.name', 'TAKEONE')) }}</h1>
            <p>Account Security</p>
        </div>

        <div class="icon-wrap">
            <div class="icon-circle">
                <svg width="30" height="30" fill="none" stroke="#8f6fda" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                </svg>
            </div>
        </div>

        <div class="content">
            <h2>Reset Your Password</h2>
            <p>We received a request to reset your password.<br>Click the button below to choose a new one.</p>
            <p style="color: #aaaaaa; font-size: 13px;">This link will expire in <strong style="color: #8f6fda;">60 minutes</strong>.</p>

            <div class="divider"></div>

            <div class="button-container">
                <a href="{{ $url }}" class="button">Reset My Password</a>
            </div>

            <p class="note">If you did not request a password reset, no further action is required.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'TAKEONE') }}. All rights reserved.</p>
            <p style="margin-top: 8px;">
                <a href="{{ url('/') }}">Visit Website</a> &nbsp;|&nbsp;
                <a href="mailto:{{ config('mail.from.address') }}">Contact Support</a>
            </p>
        </div>
    </div>
</body>
</html>
