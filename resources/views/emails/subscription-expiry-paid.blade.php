<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Expiry Reminder</title>
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
        .alert-box {
            background-color: #fff8e6;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 24px;
        }
        .alert-box p {
            margin: 0;
            color: #92400e;
            font-size: 15px;
            font-weight: 600;
        }
        .info-box {
            background-color: rgba(143, 111, 218, 0.05);
            border-left: 4px solid #8f6fda;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 24px;
        }
        .info-box p {
            margin: 0 0 8px 0;
            color: #555555;
            font-size: 14px;
            line-height: 1.6;
        }
        .info-box p:last-child {
            margin-bottom: 0;
        }
        .info-box strong {
            color: #333333;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
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
            <h1>{{ strtoupper($subscription->tenant->club_name ?? config('app.name')) }}</h1>
            <p>Membership Renewal Reminder</p>
        </div>
        <div class="content">
            <p class="greeting">Dear <strong>{{ $recipient->full_name }}</strong>,</p>

            <div class="alert-box">
                <p>⏳ Your membership package expires in 3 days!</p>
            </div>

            <div class="info-box">
                <p><strong>Package:</strong> {{ $subscription->package?->name ?? 'N/A' }}</p>
                <p><strong>Club:</strong> {{ $subscription->tenant->club_name }}</p>
                <p><strong>Expiry Date:</strong> {{ $subscription->end_date->format('F j, Y') }}</p>
            </div>

            <p style="color: #555555; line-height: 1.7; font-size: 14px;">
                To continue enjoying uninterrupted access to all club benefits and activities,
                please renew your membership before it expires.
            </p>

            <div class="button-container">
                <a href="{{ $subscription->tenant->url() }}" class="button">Renew My Package</a>
            </div>

            <div class="divider"></div>

            <p style="color: #aaaaaa; font-size: 13px; text-align: center; margin: 0;">
                If you have already renewed, please disregard this message.
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
