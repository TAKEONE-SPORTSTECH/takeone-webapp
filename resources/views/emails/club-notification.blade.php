<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->subject }}</title>
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
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 2px;
        }
        .header p {
            color: rgba(255, 255, 255, 0.85);
            margin: 0;
            font-size: 14px;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 16px;
            color: #333333;
            margin-bottom: 20px;
        }
        .greeting strong {
            color: #8f6fda;
        }
        .subject-title {
            color: #8f6fda;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 20px 0;
        }
        .message-box {
            background-color: rgba(143, 111, 218, 0.05);
            border-left: 4px solid #8f6fda;
            padding: 20px;
            border-radius: 6px;
            color: #444444;
            line-height: 1.7;
            white-space: pre-line;
        }
        .divider {
            height: 1px;
            background-color: #ede9f8;
            margin: 30px 0;
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
            <h1>{{ strtoupper($notification->tenant->club_name ?? config('app.name')) }}</h1>
            <p>Club Notification</p>
        </div>
        <div class="content">
            <p class="greeting">Dear <strong>{{ $recipient->full_name }}</strong>,</p>
            <h2 class="subject-title">{{ $notification->subject }}</h2>
            <div class="message-box">{{ $notification->message }}</div>

            <div class="divider"></div>

            <p style="color: #aaaaaa; font-size: 13px; text-align: center; margin: 0;">
                This message was sent to you by {{ $notification->tenant->club_name ?? config('app.name') }}.
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p style="margin-top: 8px;">
                <a href="{{ url('/') }}">Visit Website</a> &nbsp;|&nbsp;
                <a href="mailto:{{ config('mail.from.address') }}">Contact Support</a>
            </p>
        </div>
    </div>
</body>
</html>
