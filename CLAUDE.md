# postd.uk — Master Build Specification

## Product Overview

**postd.uk** is a UK-focused, automated social media management SaaS for small businesses.
The entire value proposition: a business owner provides **three things only** — their Google Reviews URL, their website URL, and some basic info about their business. postd.uk does everything else. AI reads their reviews and website, generates brilliant platform-native content, and posts it automatically across all their connected social channels.

**Tagline:** "All your posts. One hive."
**Domain:** postd.uk
**Built by:** Dijitul digital agency, Mansfield

---

## Brand Identity

| Token | Value | Usage |
|-------|-------|-------|
| `brand-amber` | `#E07B30` | Primary CTA, logo accent, active states |
| `brand-navy` | `#1E2D4A` | Primary text, headers, nav |
| `brand-honey` | `#F5C842` | Highlights, badges, premium indicators |
| `brand-cream` | `#F9F5EE` | Page backgrounds, card surfaces |
| `brand-white` | `#FFFFFF` | Input backgrounds, modals |
| `brand-amber-dark` | `#B85E1A` | Hover states on amber |
| `brand-navy-light` | `#2E4470` | Secondary navy for gradients |

**Typography:**
- Display: `Syne` (Google Fonts) — bold, modern, geometric
- Body: `Inter` (Google Fonts) — clean, highly readable
- Mono: `JetBrains Mono` — code/API keys only

**Logo concept:** A stylised hexagon (honeycomb cell) containing a speech bubble with a lightning bolt — representing automated, buzzing social activity. The word "postd" in Syne Bold with a amber dot replacing the full stop after "postd" to represent ".uk".

**Visual language:** Clean, confident, slightly playful. Not corporate. UK-native tone. Think Notion meets Hootsuite, but warmer and more human. Generous white space. Amber accents sparingly.

---

## Customer Onboarding — The Three-Step Setup

The customer ONLY ever has to provide:
1. **Business name** and **industry/category** (dropdown: Restaurant, Retail, Trades, Professional Services, Health & Beauty, etc.)
2. **Google Reviews page URL** (e.g. `https://g.page/r/xxx/review`)
3. **Website URL** (e.g. `https://mybusiness.co.uk`)
4. **Tone preference**: Professional / Friendly / Casual (single toggle)
5. **Which platforms** to connect (OAuth flows, guided step by step)

That is all. Everything else — content themes, post ideas, scheduling, image generation — is fully automated.

**Post-setup automation:**
- System scrapes website for: services, USPs, team info, location, opening hours
- System reads Google Reviews for: sentiment, common praise, customer language
- System monitors local UK news (via RSS/NewsAPI) for relevant trending topics
- AI generates 6 platform-native variants of every content piece
- Posts are scheduled at optimal times per platform per day
- Customer can optionally review posts in a simple mobile-first inbox before they go live (or set to fully auto)

---

## Tech Stack

### Backend (API)
- **Framework:** Laravel 11 (PHP 8.3+)
- **Architecture:** Modular monolith — bounded contexts as Laravel modules/packages
- **Database:** PostgreSQL 16 (primary data store)
- **Cache/Queue:** Redis 7 (cache, sessions, rate limiting, job queues)
- **Queue system:** Laravel Horizon with named queues:
  - `critical` — auth, billing, webhook receipt
  - `posting` — social media post dispatch (time-sensitive)
  - `generation` — AI content generation
  - `scraping` — website/review scraping (low priority)
- **Authentication:** Laravel Sanctum (SPA tokens) + Socialite (OAuth for social platforms)
- **Billing:** Laravel Cashier (Stripe) + Stripe Tax (UK VAT)
- **File storage:** DigitalOcean Spaces (S3-compatible) via Laravel's `s3` disk
- **Search/Scout:** Laravel Scout with Meilisearch (for admin search)

### Frontend (Web App + PWA)
- **Framework:** React 18 + Vite 5
- **Styling:** Tailwind CSS 4 (use CSS custom properties for brand tokens)
- **State:** Zustand (lightweight, no boilerplate)
- **Data fetching:** TanStack Query (React Query v5)
- **Router:** React Router v6
- **Forms:** React Hook Form + Zod validation
- **PWA:** Vite PWA plugin (service worker, offline support, installable)
- **Mobile-first:** Every screen designed for 375px width first, then desktop

### AI & Content
- **Text generation:** OpenAI GPT-4o (primary), GPT-4o-mini (bulk/cheap tasks)
- **Image generation:** DALL-E 3 (for posts needing custom imagery)
- **TikTok video:** Creatomate API (template-based video generation)
- **Scraping:** Laravel HTTP client + DOMDocument/Symfony DomCrawler for website scraping
- **Review fetching:** Google Places API (for review data from Google Reviews URL)

### External APIs
- Facebook Graph API (Pages posting)
- Instagram Graph API (Business account posting, image + carousel + reels)
- X (Twitter) API v2 (tweet posting)
- LinkedIn API v2 (organisation posts)
- TikTok Content Posting API (video upload + publish)
- Google Business Profile API (post to GBP — FREE for all tiers, major differentiator)
- NewsAPI.org or UK RSS feeds (local news for content inspiration)
- Google Places API (review ingestion)
- Stripe API (subscriptions, webhooks)
- OpenAI API (content generation)
- Creatomate API (TikTok video)
- DigitalOcean Spaces API (media storage)

---

## Pricing Tiers

All prices ex-VAT. Stripe Tax handles UK VAT (20%) automatically at checkout.

| Tier | Monthly | Platforms Included | Key Feature |
|------|---------|-------------------|-------------|
| **Starter** | £19/mo | 2 platforms + GBP | Perfect for just getting started |
| **Growth** | £39/mo | 4 platforms + GBP | Most popular — default trial tier |
| **Pro** | £69/mo | All platforms + GBP | Unlimited everything |

**TikTok add-on:** +£15/mo (Starter/Growth), included in Pro
**14-day free trial** (Growth tier, no card required)
**Google Business Profile** is FREE on every tier (major differentiator vs competitors)

---

## Module Architecture

```
api/
├── app/
│   ├── Modules/
│   │   ├── Auth/           — Registration, login, email verify, 2FA
│   │   ├── Onboarding/     — Business setup, platform connection wizard
│   │   ├── Billing/        — Stripe/Cashier, plans, invoices, usage
│   │   ├── Content/        — Post generation, queue, approval inbox
│   │   ├── Scraping/       — Website scraper, Google Reviews fetcher
│   │   ├── Social/         — Social platform connections, OAuth, posting
│   │   ├── Schedule/       — Post scheduling, optimal timing engine
│   │   ├── Analytics/      — Post performance, engagement tracking
│   │   ├── Admin/          — Dijitul team dashboard (super admin)
│   │   └── Notifications/  — Email, in-app, webhook notifications
│   ├── Models/
│   ├── Http/Controllers/
│   └── ...
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── api.php
│   ├── admin.php
│   └── webhooks.php
```

---

## Database Schema (Core Tables)

```sql
-- Users & tenancy
users                  — id, email, name, password, trial_ends_at, ...
businesses             — id, user_id, name, industry, website_url, google_reviews_url, tone, ...
business_settings      — id, business_id, auto_approve_posts, post_time_windows, ...

-- Social connections
social_connections     — id, business_id, platform, access_token (encrypted), expires_at, ...
platform_accounts      — id, connection_id, platform_account_id, account_name, account_type, ...

-- Content pipeline
content_sources        — id, business_id, type (review/website/news), raw_data, scraped_at
content_briefs         — id, business_id, source_id, theme, key_messages, tone_notes, ...
posts                  — id, business_id, brief_id, platform, content, media_urls[], status, scheduled_at, ...
post_attempts          — id, post_id, attempted_at, response_code, response_body, ...

-- Billing
subscriptions          — (Cashier managed)
plan_features          — plan_name, platform_limit, tiktok_included, ...

-- Admin
system_health_logs     — id, business_id, connection_id, check_type, status, checked_at
```

---

## Content Generation Engine

### The Cascade Model
One source event → six platform-native posts. Each genuinely different, not just resized.

**Source types:**
1. Google Review (positive review comes in → trigger content generation)
2. Website scrape finding (new service detected, opening hours, USP)
3. Local news hook (relevant UK news item found via RSS)
4. Weekly content (evergreen posts generated on schedule)

**Platform rules (encoded in system prompts):**

| Platform | Format | Tone | Length | Special |
|----------|--------|------|--------|---------|
| Facebook | Story + CTA | Warm, conversational | 150-300 words | Emojis OK, hashtags minimal |
| Instagram | Visual-first caption | Aspirational, lifestyle | 100-150 words | 5-10 hashtags, strong hook line |
| X (Twitter) | Punchy, opinionated | Direct, witty | Max 260 chars | 1-2 hashtags, question or statement |
| LinkedIn | Professional insight | Expert, thoughtful | 200-400 words | No excessive hashtags, value-led |
| TikTok | Script for video | Energetic, authentic | 30-60 second script | Hook in first 3 seconds |
| Google BP | Factual update | Clear, helpful | 100-200 words | Include CTA, mention location |

**Image generation prompt rules:**
- Always UK-appropriate imagery
- Never generic stock photo aesthetics
- Brand-consistent colour palette in prompts
- Business category-aware (trades vs restaurant vs professional)

---

## Admin Dashboard (Dijitul internal)

Route: `/admin` (separate auth, Dijitul team only)

Key metrics:
- MRR (monthly recurring revenue)
- Active subscribers, trial users, churned this month
- Platform connection health (% with valid tokens)
- Posts generated this week, success rate
- Top churning businesses (at-risk list)
- API cost per business/month (OpenAI spend tracking)

---

## Hosting & Deployment

- **Server:** DigitalOcean Droplet, Ubuntu 24.04 LTS, LON1 region
- **Web server:** Nginx + PHP-FPM 8.3
- **Process manager:** Supervisor (for Horizon queue workers)
- **SSL:** Let's Encrypt (Certbot)
- **Deployment:** GitHub Actions → SSH deploy on push to `main`
- **Storage:** DigitalOcean Spaces (CDN-enabled, S3-compatible)
- **Redis:** Installed on same droplet initially

**Deployment script:** Zero-downtime with `php artisan down`, `git pull`, `composer install --no-dev`, `php artisan migrate --force`, `php artisan up`, `sudo supervisorctl restart horizon`

---

## Agent Team & Responsibilities

Each agent works in their own area. Read this file before starting work.

| Agent | Owns |
|-------|------|
| Brand Guardian + UI Designer | Design system, logo SVG, Tailwind config, component library |
| UX Architect | Onboarding flow wireframes, screen map, component specs |
| Backend Architect | Laravel 11 full scaffold, all modules, migrations, API routes |
| Frontend Developer | React PWA, all screens, components, API integration |
| AI Engineer | Content generation engine, platform prompts, cascade model |
| DevOps Automator | GitHub Actions CI/CD, server provision script, Nginx/Supervisor configs |
| Database Optimizer | PostgreSQL schema review, indexes, query optimisation |
| Security Engineer | Auth hardening, API key encryption, rate limiting, OWASP checks |
| Social Media Strategist + platform specialists | Platform content rules, optimal post times, hashtag strategy |
| Content Creator | Onboarding copy, marketing website copy, email sequences |
| Growth Hacker | Trial-to-paid conversion, referral mechanics, in-app upsell triggers |
| Technical Writer | API documentation, internal docs, agent handoff docs |

---

## File Structure

```
D:\Git\postd.uk\
├── CLAUDE.md                    ← You are here
├── README.md
├── .gitignore
├── .github/
│   └── workflows/
│       └── deploy.yml           ← CI/CD pipeline
├── api/                         ← Laravel 11 backend
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── tests/
│   ├── artisan
│   ├── composer.json
│   └── .env.example
├── web/                         ← React + Vite PWA frontend
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── hooks/
│   │   ├── stores/
│   │   ├── lib/
│   │   └── main.jsx
│   ├── public/
│   ├── index.html
│   ├── package.json
│   ├── tailwind.config.js
│   └── vite.config.js
├── docs/                        ← Internal documentation
│   ├── architecture.md
│   ├── platform-content-rules.md
│   ├── design-system.md
│   └── api-reference.md
└── scripts/
    ├── provision.sh             ← Server setup script
    └── deploy.sh                ← Manual deploy script
```

---

## Key Business Rules

1. **Google Business Profile is always free** — include on every tier, lead with this in marketing
2. **Trial defaults to Growth** — maximum feature exposure during trial
3. **No card required for trial** — lower friction, higher signup conversion
4. **All posts reviewed before posting by default** — but can be toggled to fully auto
5. **Platform tokens refresh automatically** — background job checks token expiry 7 days ahead
6. **Rate limits respected religiously** — each platform's API limits are enforced in code, never exceeded
7. **UK English everywhere** — all AI prompts instruct UK spelling and idiom
8. **Data sovereignty** — all data stored in EU/UK regions only
9. **GDPR compliant** — data deletion on account close, consent flows on signup
10. **White-label ready** — architecture should support white-labelling for Dijitul reselling in future

---

## Environment Variables (.env.example)

```bash
APP_NAME="postd.uk"
APP_ENV=production
APP_KEY=
APP_URL=https://postd.uk

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=postduk
DB_USERNAME=postduk
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Storage
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ams3
AWS_BUCKET=postduk-media
AWS_ENDPOINT=https://ams3.digitaloceanspaces.com
FILESYSTEM_DISK=s3

# Stripe
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
CASHIER_CURRENCY=gbp
CASHIER_CURRENCY_LOCALE=en_GB

# OpenAI
OPENAI_API_KEY=
OPENAI_ORG_ID=

# Social Platforms
FACEBOOK_APP_ID=
FACEBOOK_APP_SECRET=
INSTAGRAM_APP_ID=
INSTAGRAM_APP_SECRET=
TWITTER_CLIENT_ID=
TWITTER_CLIENT_SECRET=
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
TIKTOK_CLIENT_KEY=
TIKTOK_CLIENT_SECRET=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_PLACES_API_KEY=

# Creatomate (TikTok video)
CREATOMATE_API_KEY=

# NewsAPI (local news hooks)
NEWS_API_KEY=

# Mail
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=hello@postd.uk
MAIL_FROM_NAME="postd.uk"

# Horizon
HORIZON_SECRET=

# Admin
ADMIN_EMAIL=admin@dijitul.co.uk
```
