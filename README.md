# postd.uk

**Automated social media management for UK small businesses.**

All your posts. One hive.

---

## What it does

A business owner provides three things: their Google Reviews URL, their website URL, and a bit of info about their business. postd.uk does everything else — AI reads their reviews and website, generates platform-native content, and posts automatically across all their social channels.

**Supported platforms:** Facebook, Instagram, X, LinkedIn, TikTok (premium), Google Business Profile (free on all plans)

## Tech Stack

- **Backend:** Laravel 11 (PHP 8.3) — `api/`
- **Frontend:** React 18 + Vite + Tailwind CSS PWA — `web/`
- **Database:** PostgreSQL 16
- **Cache/Queue:** Redis + Laravel Horizon
- **AI:** OpenAI GPT-4o + DALL-E 3
- **Billing:** Stripe + Laravel Cashier
- **Hosting:** DigitalOcean, LON1

## Local Development

### Backend (API)

```bash
cd api
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

### Frontend (Web)

```bash
cd web
npm install
npm run dev
```

## Deployment

Push to `main` branch — GitHub Actions deploys automatically to `api.postd.uk` and `postd.uk`.

See `.github/workflows/deploy.yml` and `scripts/provision.sh` for full deploy and server setup details.

## Pricing

| Plan | Price | Platforms |
|------|-------|-----------|
| Starter | £19/mo + VAT | 2 + GBP |
| Growth | £39/mo + VAT | 4 + GBP |
| Pro | £69/mo + VAT | All + GBP |

TikTok add-on: +£15/mo (Starter/Growth). 14-day free trial, no card required.

---

Built by [Dijitul](https://dijitul.co.uk) — Mansfield's digital agency.
