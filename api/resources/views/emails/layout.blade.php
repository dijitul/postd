<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>postd.uk</title>
    <style>
        body { font-family: 'Inter', Arial, sans-serif; background: #F9F5EE; color: #1E2D4A; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .header { background: #1E2D4A; padding: 24px 32px; }
        .header-logo { color: #E07B30; font-size: 24px; font-weight: 700; letter-spacing: -0.5px; }
        .body { padding: 32px; }
        .footer { background: #F9F5EE; padding: 16px 32px; text-align: center; font-size: 13px; color: #6b7280; border-top: 1px solid #e5e7eb; }
        .btn { display: inline-block; background: #E07B30; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 16px 0; }
        p { line-height: 1.6; margin: 0 0 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-logo">postd.uk</div>
        </div>
        <div class="body">
            @yield('content')
        </div>
        <div class="footer">
            <p>postd.uk — built by <a href="https://dijitul.co.uk" style="color: #E07B30;">Dijitul</a>, Mansfield, UK</p>
            <p>© {{ date('Y') }} postd.uk. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
