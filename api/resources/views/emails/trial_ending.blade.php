<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Your postd.uk trial ends in 3 days</title>
    <style>
        /* Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { margin: 0; padding: 0; background-color: #F9F5EE; font-family: 'Inter', Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { border: 0; display: block; max-width: 100%; }
        a { text-decoration: none; }

        .email-body {
            background-color: #F9F5EE;
            padding: 40px 20px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #F9F5EE;
        }

        /* Header */
        .header {
            background-color: #1E2D4A;
            border-radius: 12px 12px 0 0;
            padding: 36px 40px 32px;
            text-align: center;
        }
        .logo-text {
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: -0.5px;
        }
        .logo-dot {
            color: #F5C842;
        }
        .header-tagline {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 14px;
            color: rgba(255,255,255,0.65);
            margin-top: 6px;
        }

        /* Body card */
        .body-card {
            background-color: #FFFFFF;
            padding: 44px 48px 40px;
        }

        /* Stats block */
        .stats-heading {
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 13px;
            font-weight: 700;
            color: #E07B30;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .stats-grid {
            background-color: #F9F5EE;
            border-radius: 10px;
            padding: 24px;
            margin-bottom: 32px;
        }
        .stats-row {
            width: 100%;
        }
        .stat-cell {
            text-align: center;
            padding: 0 12px;
            vertical-align: top;
        }
        .stat-number {
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 36px;
            font-weight: 800;
            color: #E07B30;
            line-height: 1;
            margin-bottom: 6px;
        }
        .stat-label {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 13px;
            color: #4A5568;
            line-height: 1.4;
        }
        .stat-divider {
            width: 1px;
            background-color: #E5E0D8;
            height: 60px;
            vertical-align: middle;
        }

        /* Heading */
        h1 {
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: #1E2D4A;
            line-height: 1.25;
            margin-bottom: 16px;
        }

        /* Body text */
        p {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 16px;
            color: #1E2D4A;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        p.muted {
            color: #4A5568;
            font-size: 15px;
        }

        /* Urgency banner */
        .urgency-banner {
            background-color: #FFF8EC;
            border-left: 4px solid #E07B30;
            border-radius: 0 8px 8px 0;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .urgency-banner p {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 15px;
            color: #1E2D4A;
            margin-bottom: 0;
            line-height: 1.6;
        }
        .urgency-banner strong {
            color: #E07B30;
        }

        /* Pricing block */
        .pricing-block {
            border: 1.5px solid #E5E0D8;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 32px;
        }
        .pricing-header {
            background-color: #F9F5EE;
            padding: 14px 20px;
            border-bottom: 1.5px solid #E5E0D8;
        }
        .pricing-header-text {
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 13px;
            font-weight: 700;
            color: #4A5568;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 0;
        }
        .plan-row {
            padding: 18px 20px;
            border-bottom: 1px solid #F0EDE7;
        }
        .plan-row:last-child {
            border-bottom: none;
        }
        .plan-row-popular {
            background-color: #FFF8EC;
            border-left: 3px solid #E07B30;
        }
        .plan-name {
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: #1E2D4A;
            display: inline-block;
            margin-right: 8px;
        }
        .plan-badge {
            display: inline-block;
            background-color: #E07B30;
            color: #FFFFFF;
            font-family: 'Inter', Arial, sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            vertical-align: middle;
        }
        .plan-price {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 14px;
            color: #4A5568;
            margin-top: 4px;
            margin-bottom: 4px;
        }
        .plan-price strong {
            font-size: 20px;
            color: #1E2D4A;
            font-weight: 700;
        }
        .plan-price span {
            color: #6B7280;
            font-size: 13px;
        }
        .plan-features {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 13px;
            color: #6B7280;
            margin-bottom: 0;
            line-height: 1.5;
        }

        /* Divider */
        .divider {
            border: none;
            border-top: 1.5px solid #F0EDE7;
            margin: 32px 0;
        }

        /* CTA button */
        .cta-block {
            text-align: center;
            margin: 32px 0 28px;
        }
        .cta-button {
            display: inline-block;
            background-color: #E07B30;
            color: #FFFFFF !important;
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 17px;
            font-weight: 700;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 8px;
            letter-spacing: 0.2px;
            line-height: 1;
        }
        .cta-button:hover {
            background-color: #B85E1A;
        }
        .cta-subtext {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 13px;
            color: #6B7280;
            margin-top: 12px;
            margin-bottom: 0;
        }

        /* Reassurance list */
        .reassurance {
            background-color: #F9F5EE;
            border-radius: 8px;
            padding: 20px 24px;
            margin-bottom: 28px;
        }
        .reassurance-title {
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #1E2D4A;
            margin-bottom: 12px;
        }
        .reassurance-item {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 14px;
            color: #1E2D4A;
            padding: 5px 0;
            padding-left: 20px;
            position: relative;
            margin-bottom: 0;
            line-height: 1.5;
        }
        .reassurance-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 10px;
            width: 8px;
            height: 8px;
            background-color: #E07B30;
            border-radius: 50%;
        }

        /* Footer */
        .footer {
            background-color: #1E2D4A;
            border-radius: 0 0 12px 12px;
            padding: 28px 40px;
            text-align: center;
        }
        .footer p {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 13px;
            color: rgba(255,255,255,0.6);
            line-height: 1.7;
            margin-bottom: 8px;
        }
        .footer p:last-child { margin-bottom: 0; }
        .footer a {
            color: rgba(255,255,255,0.75);
            text-decoration: underline;
        }
        .footer-logo {
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: #FFFFFF;
            margin-bottom: 14px;
            display: block;
        }
        .footer-logo span { color: #F5C842; }

        /* Mobile */
        @media only screen and (max-width: 620px) {
            .email-body { padding: 20px 12px; }
            .header { padding: 28px 24px; border-radius: 8px 8px 0 0; }
            .body-card { padding: 32px 24px 28px; }
            h1 { font-size: 22px; }
            .cta-button { padding: 14px 24px; font-size: 16px; }
            .footer { padding: 24px 24px; border-radius: 0 0 8px 8px; }
            .stat-number { font-size: 28px; }
        }
    </style>
</head>
<body>
<div class="email-body">
    <div class="email-container">

        <!-- Header -->
        <div class="header">
            <span class="logo-text">postd<span class="logo-dot">.</span>uk</span>
            <p class="header-tagline">All your posts. One hive.</p>
        </div>

        <!-- Body card -->
        <div class="body-card">

            <!-- Stats section -->
            <p class="stats-heading">Your trial in numbers</p>
            <div class="stats-grid">
                <table class="stats-row" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td class="stat-cell" width="33%">
                            <div class="stat-number">{{ $postsCreated }}</div>
                            <p class="stat-label">posts<br>created</p>
                        </td>
                        <td class="stat-divider"></td>
                        <td class="stat-cell" width="33%">
                            <div class="stat-number">{{ $platformsReached }}</div>
                            <p class="stat-label">platforms<br>reached</p>
                        </td>
                        <td class="stat-divider"></td>
                        <td class="stat-cell" width="33%">
                            <div class="stat-number">{{ $reviewsUsed }}</div>
                            <p class="stat-label">reviews<br>turned into content</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Heading -->
            <h1>Your trial ends in 3 days — here's what happens next</h1>

            <p>
                You've seen what postd.uk can do. {{ $postsCreated }} posts created, {{ $platformsReached }} platforms covered — all without writing a single word yourself. That's what consistent social media looks like when it's handled for you.
            </p>

            <!-- Urgency banner -->
            <div class="urgency-banner">
                <p>Your free trial ends on <strong>{{ $trialEndsAt }}</strong>. After that, posting will pause unless you choose to continue. <strong>No card has been charged. No tricks.</strong> We just want you to know what's coming so you can decide.</p>
            </div>

            <p class="muted">
                If you'd like to keep your posts going — and keep your platforms active and consistent — choose the plan that fits you best.
            </p>

            <hr class="divider">

            <!-- Pricing -->
            <p class="stats-heading">Choose your plan</p>
            <div class="pricing-block">
                <div class="pricing-header">
                    <p class="pricing-header-text">All plans include Google Business Profile — always free</p>
                </div>

                <!-- Starter -->
                <div class="plan-row">
                    <span class="plan-name">Starter</span>
                    <p class="plan-price"><strong>£19</strong><span>/mo + VAT</span></p>
                    <p class="plan-features">2 platforms + Google Business Profile. Perfect for just getting started.</p>
                </div>

                <!-- Growth (highlighted) -->
                <div class="plan-row plan-row-popular">
                    <span class="plan-name">Growth</span>
                    <span class="plan-badge">Your current trial</span>
                    <p class="plan-price"><strong>£39</strong><span>/mo + VAT</span></p>
                    <p class="plan-features">4 platforms + Google Business Profile. Our most popular plan for established businesses.</p>
                </div>

                <!-- Pro -->
                <div class="plan-row">
                    <span class="plan-name">Pro</span>
                    <p class="plan-price"><strong>£69</strong><span>/mo + VAT</span></p>
                    <p class="plan-features">All platforms including TikTok + Google Business Profile. Unlimited posting, unlimited content.</p>
                </div>
            </div>

            <!-- Reassurance -->
            <div class="reassurance">
                <p class="reassurance-title">A few things worth knowing</p>
                <p class="reassurance-item">No card has been charged during your trial — not a penny</p>
                <p class="reassurance-item">You can cancel at any time, no questions asked</p>
                <p class="reassurance-item">Your connected platforms and content history are kept safe</p>
                <p class="reassurance-item">If you have any questions, just reply to this email</p>
            </div>

            <!-- CTA -->
            <div class="cta-block">
                <a href="{{ $upgradeUrl }}" class="cta-button">Continue with postd.uk</a>
                <p class="cta-subtext">Takes less than two minutes. Cancel whenever you like.</p>
            </div>

            <p class="muted">
                If you've decided postd.uk isn't right for you right now, that's completely fine — no hard feelings and no pressure. But if you'd like to chat before deciding, just reply here and we'll come back to you promptly.
            </p>

        </div>

        <!-- Footer -->
        <div class="footer">
            <span class="footer-logo">postd<span>.</span>uk</span>
            <p>
                postd.uk is a product of Dijitul Ltd, Mansfield, Nottinghamshire.<br>
                <a href="{{ $unsubscribeUrl }}">Unsubscribe from emails</a> &middot; <a href="https://postd.uk/privacy">Privacy policy</a>
            </p>
            <p>
                You're receiving this because your postd.uk trial is nearing its end.<br>
                &copy; {{ date('Y') }} postd.uk. All rights reserved.
            </p>
        </div>

    </div>
</div>
</body>
</html>
