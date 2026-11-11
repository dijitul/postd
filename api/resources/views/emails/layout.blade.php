<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>postd.uk</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; background: #F3F5FB; color: #0E1A3F; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 18px; overflow: hidden; border: 1px solid #E3E7F1; }
        .header { background: #0E1A3F; padding: 24px 32px; }
        .header-logo { color: #FFFFFF; font-family: 'Sora', 'Segoe UI', Arial, sans-serif; font-size: 26px; font-weight: 800; letter-spacing: -0.035em; }
        .body { padding: 32px; }
        .footer { background: #0E1A3F; padding: 20px 32px; text-align: center; font-size: 13px; color: rgba(255,255,255,0.75); }
        .footer a { color: #95A5FF; }
        .btn { display: inline-block; background: #2340F5; color: #ffffff; padding: 13px 24px; border-radius: 999px; text-decoration: none; font-family: 'Sora', 'Segoe UI', Arial, sans-serif; font-weight: 600; font-size: 15px; margin: 16px 0; }
        p { line-height: 1.6; margin: 0 0 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-logo"><img src="https://postd.uk/app-icons/icon-96.png" width="36" height="36" alt="" style="display:inline-block;vertical-align:middle;margin-right:10px;border:0;">postd</div>
        </div>
        <div class="body">
            @yield('content')
        </div>
        <div class="footer">
            <p>postd.uk, built by <a href="https://dijitul.uk">dijitul</a>, Mansfield, UK</p>
            <p>© {{ date('Y') }} postd.uk. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
