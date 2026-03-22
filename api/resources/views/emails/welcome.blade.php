<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Welcome to postd.uk</title>
    <style>
        /* Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { margin: 0; padding: 0; background-color: #F9F5EE; font-family: 'Inter', Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { border: 0; display: block; max-width: 100%; }
        a { text-decoration: none; }

        /* Brand tokens */
        /* brand-amber: #E07B30 */
        /* brand-navy: #1E2D4A */
        /* brand-honey: #F5C842 */
        /* brand-cream: #F9F5EE */

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
            background-color: #E07B30;
            border-radius: 12px 12px 0 0;
            padding: 36px 40px 32px;
            text-align: center;
        }
        .header-logo {
            display: inline-block;
            margin-bottom: 0;
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
            color: rgba(255,255,255,0.82);
            margin-top: 6px;
            letter-spacing: 0.3px;
        }

        /* Body card */
        .body-card {
            background-color: #FFFFFF;
            padding: 44px 48px 40px;
        }

        /* Celebration strip */
        .celebration {
            background-color: #FFF8EC;
            border: 1.5px solid #F5C842;
            border-radius: 8px;
            padding: 16px 20px;
            text-align: center;
            margin-bottom: 32px;
        }
        .celebration-text {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 15px;
            color: #1E2D4A;
            font-weight: 500;
        }
        .celebration-highlight {
            color: #E07B30;
            font-weight: 700;
        }

        /* Heading */
        h1 {
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #1E2D4A;
            line-height: 1.2;
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

        /* Steps section */
        .steps-heading {
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #1E2D4A;
            margin-bottom: 20px;
            margin-top: 8px;
        }
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .step-number {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            background-color: #E07B30;
            border-radius: 50%;
            color: #FFFFFF;
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 15px;
            font-weight: 800;
            text-align: center;
            line-height: 36px;
            margin-right: 16px;
            margin-top: 2px;
        }
        .step-content {}
        .step-title {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #1E2D4A;
            margin-bottom: 4px;
        }
        .step-desc {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 15px;
            color: #4A5568;
            line-height: 1.6;
            margin-bottom: 0;
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
            margin: 36px 0 32px;
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

        /* Trial banner */
        .trial-banner {
            background-color: #F9F5EE;
            border: 1.5px solid #E07B30;
            border-radius: 8px;
            padding: 18px 24px;
            text-align: center;
            margin-bottom: 32px;
        }
        .trial-banner-title {
            font-family: 'Syne', 'Arial Black', Arial, sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #1E2D4A;
            margin-bottom: 6px;
        }
        .trial-banner-text {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 14px;
            color: #4A5568;
            margin-bottom: 0;
            line-height: 1.5;
        }
        .trial-banner-text strong {
            color: #E07B30;
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
        .footer p:last-child {
            margin-bottom: 0;
        }
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
        .footer-logo span {
            color: #F5C842;
        }

        /* Mobile responsiveness */
        @media only screen and (max-width: 620px) {
            .email-body { padding: 20px 12px; }
            .header { padding: 28px 24px; border-radius: 8px 8px 0 0; }
            .body-card { padding: 32px 24px 28px; }
            h1 { font-size: 24px; }
            .cta-button { padding: 14px 28px; font-size: 16px; }
            .footer { padding: 24px 24px; border-radius: 0 0 8px 8px; }
        }
    </style>
</head>
<body>
<div class="email-body">
    <div class="email-container">

        <!-- Header -->
        <div class="header">
            <div class="header-logo">
                <span class="logo-text">postd<span class="logo-dot">.</span>uk</span>
            </div>
            <p class="header-tagline">All your posts. One hive.</p>
        </div>

        <!-- Body card -->
        <div class="body-card">

            <!-- Celebration strip -->
            <div class="celebration">
                <span class="celebration-text">Welcome to the hive, <span class="celebration-highlight">{{ $businessName }}</span>. Your posts are about to take care of themselves.</span>
            </div>

            <!-- Heading -->
            <h1>You're all set. Let's get your first posts flowing.</h1>

            <p>
                It's brilliant to have you on board. We built postd.uk for businesses exactly like yours — ones that know they should be posting consistently but simply don't have the time to do it properly. That's our job now.
            </p>

            <p>
                Your 14-day free trial is live and ready. Here's everything you need to do to get your first posts created and scheduled — it takes about five minutes.
            </p>

            <hr class="divider">

            <!-- Steps -->
            <p class="steps-heading">Three things to get your first posts flowing</p>

            <!-- Step 1 -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td valign="top" width="52">
                        <div class="step-number">1</div>
                    </td>
                    <td valign="top">
                        <div class="step-content">
                            <p class="step-title">Connect your social platforms</p>
                            <p class="step-desc">Head to your dashboard and connect the platforms you want to post on. Each one takes less than a minute with our guided OAuth connection. We support Facebook, Instagram, X, LinkedIn, TikTok, and Google Business Profile — and GBP is included free on every plan.</p>
                        </div>
                    </td>
                </tr>
            </table>

            <div style="height: 20px;"></div>

            <!-- Step 2 -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td valign="top" width="52">
                        <div class="step-number">2</div>
                    </td>
                    <td valign="top">
                        <div class="step-content">
                            <p class="step-title">Let us learn about your business</p>
                            <p class="step-desc">We'll read your website and your Google Reviews automatically — no copying and pasting required. This is how we understand your services, your tone, and what your customers love about you. The more complete your website is, the more tailored your posts will be.</p>
                        </div>
                    </td>
                </tr>
            </table>

            <div style="height: 20px;"></div>

            <!-- Step 3 -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td valign="top" width="52">
                        <div class="step-number">3</div>
                    </td>
                    <td valign="top">
                        <div class="step-content">
                            <p class="step-title">Choose your posting style</p>
                            <p class="step-desc">Decide whether you'd like to review posts before they go live, or let them publish automatically. Most businesses start with the review inbox so they can see the content in action before switching to fully automatic. Either way, we'll take care of the writing, the scheduling, and the timing.</p>
                        </div>
                    </td>
                </tr>
            </table>

            <hr class="divider">

            <!-- Trial banner -->
            <div class="trial-banner">
                <p class="trial-banner-title">Your 14-day free trial has started</p>
                <p class="trial-banner-text">You're on our <strong>Growth plan</strong> — that's 4 platforms plus Google Business Profile, fully included. <strong>No card required. No charges until you decide to continue.</strong></p>
            </div>

            <!-- CTA -->
            <div class="cta-block">
                <a href="{{ $dashboardUrl }}" class="cta-button">Get started in your dashboard</a>
                <p class="cta-subtext">Questions? Just reply to this email and a real person will get back to you.</p>
            </div>

            <p class="muted">
                We're a small team and we genuinely care about getting this right for you. If something isn't working, or if you want to talk through your setup, just reply here. We're based in Mansfield and we're happy to help.
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
                You're receiving this because you signed up at postd.uk.<br>
                &copy; {{ date('Y') }} postd.uk. All rights reserved.
            </p>
        </div>

    </div>
</div>
</body>
</html>
