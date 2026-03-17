<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name', 'TAKEONE') }}</title>
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
        .content {
            padding: 40px 30px;
        }
        .welcome-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .welcome-section h2 {
            color: #8f6fda;
            font-size: 22px;
            margin-top: 0;
            margin-bottom: 10px;
        }
        .welcome-section p {
            color: #666666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .info-box {
            background-color: rgba(143, 111, 218, 0.05);
            border-left: 4px solid #8f6fda;
            padding: 20px;
            margin: 20px 0;
            border-radius: 6px;
        }
        .info-box h3 {
            color: #8f6fda;
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .info-box p {
            color: #666666;
            line-height: 1.6;
            margin: 0 0 8px 0;
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
        .button:hover {
            background-color: #7c5bc7;
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
        .divider {
            height: 1px;
            background-color: #ede9f8;
            margin: 30px 0;
        }
        .greeting {
            font-size: 17px;
            color: #333333;
            margin-bottom: 20px;
        }
        .greeting strong {
            color: #8f6fda;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ strtoupper(config('app.name', 'TAKEONE')) }}</h1>
            <p>Welcome to the Family</p>
        </div>
        <div class="content">
            <div class="welcome-section">
                <p class="greeting">Dear <strong>{{ $user->full_name }}</strong>,</p>
                <h2>Welcome to {{ config('app.name', 'TAKEONE') }}!</h2>
                <p>We are thrilled to have you join our community. Your account has been successfully created and you are now part of our family.</p>
            </div>

            @if($guardian && $relationship)
            <div class="info-box">
                <h3>Your Family Information</h3>
                <p><strong>Guardian:</strong> {{ $guardian->full_name }}</p>
                <p><strong>Relationship:</strong> {{ $relationship->relationship_type === 'spouse' ? 'Wife' : ucfirst($relationship->relationship_type) }}</p>
                @if($user->birthdate)
                <p><strong>Birthdate:</strong> {{ \Carbon\Carbon::parse($user->birthdate)->format('F j, Y') }}</p>
                @endif
            </div>
            @endif

            <div class="divider"></div>

            <p style="color: #555; line-height: 1.6;">Before you can access your account, please verify your email address by clicking the button below.</p>

            <div class="button-container">
                <a href="{{ $verificationUrl }}" class="button">Verify My Email</a>
            </div>

            <p style="text-align: center; color: #aaaaaa; font-size: 13px; margin-top: 20px;">
                If you did not create an account, no further action is required.
            </p>
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
