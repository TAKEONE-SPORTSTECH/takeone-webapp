<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name', 'Club SaaS') }}</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            padding: 40px 30px;
            text-align: center;
            background: linear-gradient(135deg, {{ $user->gender == 'm' ? '#0d6efd 0%, #0a58ca 100%' : '#d63384 0%, #a61e4d 100%' }});
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 10px 0 0 0;
            font-size: 16px;
        }
        .content {
            padding: 40px 30px;
        }
        .welcome-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .welcome-section h2 {
            color: {{ $user->gender == 'm' ? '#0d6efd' : '#d63384' }};
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 10px;
        }
        .welcome-section p {
            color: #666666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .info-box {
            background-color: {{ $user->gender == 'm' ? 'rgba(13, 110, 253, 0.05)' : 'rgba(214, 51, 132, 0.05)' }};
            border-left: 4px solid {{ $user->gender == 'm' ? '#0d6efd' : '#d63384' }};
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .info-box h3 {
            color: {{ $user->gender == 'm' ? '#0d6efd' : '#d63384' }};
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .info-box p {
            color: #666666;
            line-height: 1.6;
            margin: 0 0 10px 0;
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
            background-color: {{ $user->gender == 'm' ? '#0d6efd' : '#d63384' }};
            color: #ffffff;
            text-decoration: none;
            padding: 14px 40px;
            border-radius: 5px;
            font-weight: 500;
            font-size: 16px;
            border: none;
            cursor: pointer;
        }
        .button:hover {
            background-color: {{ $user->gender == 'm' ? '#0b5ed7' : '#b02a5c' }};
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        .footer p {
            color: #999999;
            font-size: 12px;
            margin: 0;
        }
        .footer a {
            color: {{ $user->gender == 'm' ? '#0d6efd' : '#d63384' }};
            text-decoration: none;
        }
        .divider {
            height: 1px;
            background-color: #e9ecef;
            margin: 30px 0;
        }
        .greeting {
            font-size: 18px;
            color: #333333;
            margin-bottom: 20px;
        }
        .greeting strong {
            color: {{ $user->gender == 'm' ? '#0d6efd' : '#d63384' }};
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name', 'Club SaaS') }}</h1>
            <p>Welcome to the Family</p>
        </div>
        <div class="content">
            <div class="welcome-section">
                <p class="greeting">Dear <strong>{{ $user->full_name }}</strong>,</p>
                <h2>Welcome to the Family!</h2>
                <p>We are thrilled to have you join our community. Your account has been successfully created and you are now part of our family.</p>
            </div>

            @if($guardian && $relationship)
            <div class="info-box">
                <h3>Your Family Information</h3>
                <p><strong>Guardian:</strong> {{ $guardian->full_name }}</p>
                <p><strong>Relationship:</strong> {{ ucfirst($relationship->relationship_type) }}</p>
                @if($user->birthdate)
                <p><strong>Birthdate:</strong> {{ \Carbon\Carbon::parse($user->birthdate)->format('F j, Y') }}</p>
                @endif
            </div>
            @endif

            <div class="divider"></div>

            <p>We're excited to have you with us. If you have any questions or need assistance, please don't hesitate to reach out to your guardian or our support team.</p>

            <div class="button-container">
                <a href="{{ url('/login') }}" class="button">Access Your Account</a>
            </div>

            <p style="text-align: center; color: #999999; font-size: 14px; margin-top: 30px;">
                If you have any questions, feel free to contact us at any time.
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Club SaaS') }}. All rights reserved.</p>
            <p style="margin-top: 10px;">
                <a href="{{ url('/') }}">Visit Website</a> |
                <a href="mailto:{{ config('mail.from.address') }}">Contact Support</a>
            </p>
        </div>
    </div>
</body>
</html>
