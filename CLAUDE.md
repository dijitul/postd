# postd.uk — CLAUDE.md

Master reference for AI assistants and developers working on this project.
Always read this file at the start of every session.

---

## What is postd.uk?

An automated social media scheduling SaaS for UK small businesses. Users connect their Google Business Profile (and other platforms), and the system generates, schedules, and publishes posts automatically using Claude AI. Built and maintained by Dijitul (dijitul.uk), Mansfield.

**Live URLs:**
- Frontend: https://postd.uk
- API: https://api.postd.uk

**Git repo:** `D:\Git\postd.uk` (local), auto-deploys to server on push to main.

---

## Tech Stack

### Backend
- **Laravel 11** (PHP 8.3) — API only, no Blade views except emails
- **PostgreSQL 16** — primary database
- **Redis 7** — cache, sessions, queues (password protected — see env vars)
- **Laravel Horizon** — queue worker management
- **Laravel Sanctum** — Bearer token auth for SPA
- **Laravel Socialite** — OAuth for social platforms
- **Laravel Cashier** — Stripe billing
- **Anthropic Claude API** — content generation (claude-haiku-4-5-20251001)
- **OpenAI API** — image generation (DALL-E)
- **DigitalOcean Spaces** — S3-compatible image storage
- **Guzzle** — HTTP client for platform APIs

### Frontend
- **React 18** + **Vite 5**
- **React Router 6** — SPA routing
- **Zustand** — auth state
- **TanStack Query** — server state / data fetching
- **react-hook-form** + **Zod** — forms and validation
- **Tailwind CSS 3** — styling
- **Lucide React** — icons

---

## Repository Structure

```
D:\Git\postd.uk\
├── api/                    Laravel 11 backend
│   ├── app/
│   │   ├── Models/         Eloquent models
│   │   ├── Modules/        Feature modules (see below)
│   │   ├── Http/Middleware/
│   │   └── Console/Commands/
│   ├── config/
│   ├── database/migrations/
│   ├── routes/api.php
│   └── storage/logs/       laravel.log lives here
├── web/                    React SPA
│   ├── src/
│   │   ├── pages/          Route-level components
│   │   ├── components/ui/  Shared UI components
│   │   ├── stores/         authStore.js (Zustand)
│   │   └── lib/api.js      Axios client — all API calls go through here
│   └── public/
├── scripts/                Nginx, Supervisor, deploy configs
└── CLAUDE.md               This file
```

---

## Backend Modules

All business logic lives under `api/app/Modules/`:

| Module | What it does |
|---|---|
| **Auth** | Login, register, Google OAuth sign-in/sign-up |
| **Onboarding** | Business setup wizard, GBP location picker |
| **Social** | Platform OAuth connections, token management |
| **Content** | Post generation (Claude AI), approval workflow, dispatch |
| **Schedule** | Post timing logic, `DispatchScheduledPostsJob` |
| **Scraping** | Website scraper, Google Reviews fetcher |
| **Billing** | Stripe subscriptions via Cashier, plan catalogue and entitlements (see Plans and Entitlements) |
| **Analytics** | Post performance data |
| **Admin** | Dijitul team dashboard (impersonate, health checks) |
| **Notifications** | Email notifications (trial ending, post failed, etc.) |
| **Media** | Photo library (`business_images`): website, Google and uploaded photos used on posts before any AI image. Photos page API under `/images` |

### Key Classes

```
ContentGenerationService        calls Anthropic Claude API to write posts
PostDispatchService             publishes a post to the platform API
GoogleBusinessProfilePlatform   GBP API integration
TwitterPlatform                 X/Twitter API v2 integration
SocialConnectionService         OAuth token storage, refresh, account sync
SchedulingService               picks optimal posting time (respects quiet hours)
ScrapeBusinessJob               scrapes website + reviews to build content brief
GeneratePostsJob                daily job: creates posts for all active platforms
ImageLibraryService             photo library: imports website/Google photos, uploads, picks a photo per post
ImportBusinessImagesJob         queued by ScrapeBusinessJob; fetches the photos (own 40s budgets per source)
DispatchScheduledPostsJob       runs every minute via cron, dispatches due posts
GoogleAuthController            handles Google sign-in OAuth callback
PlanCatalogue                   read-only view of config/plans.php: plans, prices, Stripe price lookup
EntitlementService              decides what an account may do (platforms, cadence, images, locations)
Entitlements                    pure value object behind EntitlementService, unit tested with no DB
```

---

## Frontend Pages

```
/                   Marketing landing page
/login              Login (Google primary, email/password secondary)
/register           Register (Google primary, email/password hidden by default)
/auth/callback      Handles Google OAuth redirect (stores token, redirects)
/onboarding         5-step business setup wizard (?new=1 adds another location)
/dashboard          Main dashboard
/posts              Content library
/inbox              Pending approval queue
/platforms          Connected social accounts
/photos             Photo library (new app routes also go in .htaccess, vite.config.js APP_ROUTES, robots.txt and the nginx conf)
/settings           Business settings
/billing            Plans, monthly/annual, usage against limits, Stripe Checkout and portal
/admin              Dijitul team only
/guides             Guides index (prerendered, grouped by pillar)
/guides/<slug>      One guide, from web/content/guides/<file>.md
```

### Public site: prerendering and guides

The public routes (`/`, `/terms`, `/privacy`, `/login`, `/register`, `/guides`, `/guides/<slug>`, plus `dist/404.html`) are rendered to static HTML at build time so crawlers and AI bots that do not run JavaScript see the full page. The browser then hydrates it. App routes are still a pure SPA served from `dist/app.html`.

- `npm run build` = client build, SSR build of `src/entry-server.jsx` into `web/.ssr/`, then `scripts/prerender.mjs` (writes the pages, `sitemap.xml`, and adds guides to `llms.txt`).
- Per-page `<title>`, description, canonical, Open Graph and JSON-LD come from `<Seo />` in `src/lib/head.jsx`. Never hard-code them in `index.html`.
- Public pages must render the same on the server as on first client render: no `window`/`localStorage` in render, and use `useHydrated()` for anything auth-dependent.
- Guides: Markdown with frontmatter in `web/content/guides/`, processed by `web/plugins/guides.js`. An article is only built once its `date` (Europe/London) has arrived. `.github/workflows/publish-guides.yml` rebuilds and copies `web/dist` every day at 06:00 UK, so dated articles go live without a push. `npm run dev` shows future articles with a "scheduled" banner.
- Nothing public may start with `/posts` (robots.txt disallows it).
- **Production runs Apache, not nginx.** Routing lives in `web/public/.htaccess`, which ships in `dist` on every deploy: prerendered files first, app routes get `app.html` plus `X-Robots-Tag: noindex`, anything else is a real 404, no trailing slashes. The vhost allows overrides (`AllowOverride All`) and `mod_headers` is enabled. `scripts/nginx-postd.uk.conf` holds the equivalent nginx rules for reference only: nginx is installed on the server but has not run since a config error on 16 September 2026, and Apache owns ports 80 and 443. Do not start nginx.
- `npm run preview` serves `dist/` with the same rules as nginx. `npm run generate:images` rebuilds `og-image.png` and the icons from `public/brand/icon.svg`.

---

## Server Details

**Provider:** DigitalOcean droplet
**IP:** 144.126.207.135
**OS:** Ubuntu 24.04
**SSH user:** root, via the `postd` host alias in `~/.ssh/config` (key `~/.ssh/postd_claude`), so `ssh postd`
**Web server:** Apache 2 (vhosts `/etc/apache2/sites-available/postd.uk-le-ssl.conf` and `api.postd.uk-le-ssl.conf`, Let's Encrypt certificates). Not nginx.
**App path:** `/var/www/postd/api` (note: no `.uk` in the server path)
**Log file:** `/var/www/postd/api/storage/logs/laravel.log`
**Worker log:** `/var/log/postd-worker.log`

**Auto-deploy:** GitHub push to `main` triggers deploy via `scripts/deploy.sh`
- Maintenance mode on
- `git pull`
- `composer install --no-dev`
- `php artisan migrate --force`
- `php artisan config:cache && route:cache && view:cache`
- Supervisor restart
- Maintenance mode off

---

## Environment Variables (server .env)

Located at `/var/www/postd/api/.env`

```env
APP_ENV=production
APP_KEY=
APP_URL=https://api.postd.uk
FRONTEND_URL=https://postd.uk

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=postd
DB_USERNAME=
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=D7J7TU7%D0Tc0m        # Redis requires auth — must be set or everything breaks
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis

# Anthropic (content generation)
ANTHROPIC_API_KEY=

# OpenAI (image generation)
OPENAI_API_KEY=

# Google OAuth (sign-in + GBP)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://api.postd.uk/api/auth/social/google_business_profile/callback
GOOGLE_AUTH_REDIRECT_URI=https://api.postd.uk/api/auth/google/callback

# Twitter/X OAuth 2.0
TWITTER_CLIENT_ID=
TWITTER_CLIENT_SECRET=
TWITTER_REDIRECT_URI=https://api.postd.uk/api/auth/social/twitter/callback

# DigitalOcean Spaces (image storage)
DO_SPACES_KEY=
DO_SPACES_SECRET=
DO_SPACES_REGION=
DO_SPACES_BUCKET=
DO_SPACES_ENDPOINT=

# Stripe
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

# Stripe price IDs (see Plans and Entitlements). Blank = hidden on the Billing page.
STRIPE_PLAN_LOCAL=              # falls back to STRIPE_PLAN_STARTER (same £19 price)
STRIPE_PLAN_STARTER=
STRIPE_PLAN_GROWTH=
STRIPE_PLAN_AGENCY=
STRIPE_PLAN_LOCAL_ANNUAL=
STRIPE_PLAN_GROWTH_ANNUAL=
STRIPE_PLAN_AGENCY_ANNUAL=
STRIPE_PRICE_EXTRA_LOCATION=    # Agency add-on, £19/month per location beyond 3
STRIPE_PLAN_PRO=                # legacy £69, existing subscribers only
STRIPE_TAX_RATE_VAT=            # 20% UK VAT, exclusive. Added to every new subscription

# Google Places (website scraping — leave blank if not set up, see Known Issues)
GOOGLE_PLACES_API_KEY=
```

After any `.env` change always run:
```bash
cd /var/www/postd/api
php artisan config:clear
supervisorctl restart all
```

---

## Supervisor Setup

Config at `/etc/supervisor/conf.d/postd-worker.conf`:

```ini
[program:postd-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/postd/api/artisan queue:work redis --queue=posting,generation,default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/postd-worker.log
stopwaitsecs=3600
```

**Important:** Keep `numprocs=1`. GBP API has a very low rate limit and multiple workers will cause 429 errors immediately.

Horizon also runs as a separate process. Both should show RUNNING:
```bash
supervisorctl status
# postd-horizon                    RUNNING
# postd-worker:postd-worker_00     RUNNING
```

---

## Cron

`crontab -e` on the server:
```
* * * * * cd /var/www/postd/api && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs:
- `DispatchScheduledPostsJob` — every minute — dispatches approved posts at their scheduled time
- `GeneratePostsJob` — daily — creates new posts for all active businesses
- `RefreshSocialTokensCommand` — daily — refreshes expiring OAuth tokens
- `NotifyTrialEndingCommand` — daily at 9am — sends trial expiry warnings

---

## Queue Behaviour — Important Quirk

**Jobs dispatched from `php artisan tinker` are NOT picked up by the Horizon worker.** They go to a different Redis database than the one the worker monitors. This only affects tinker — the web app dispatches correctly.

**To run jobs in tinker, call them directly:**
```php
// Generate posts
$business = \App\Models\User::where('email', 'you@example.com')->first()->business;
$job = new \App\Modules\Content\Jobs\GeneratePostsJob($business);
$job->handle(app(\App\Modules\Content\Services\ContentGenerationService::class));

// Publish a post
$post = \App\Models\Post::find('uuid-here');
$service = app(\App\Modules\Content\Services\PostDispatchService::class);
$service->dispatch($post);
```

**To force-regenerate posts today:**
```php
$business = \App\Models\User::where('email', 'you@example.com')->first()->business;
$business->last_generated_at = null;
$business->save();
// Then run GeneratePostsJob directly as above
```

---

## Post Lifecycle

```
pending   created by GeneratePostsJob — awaits user approval in Inbox
approved  user approves in Inbox UI (or auto-approved by AutoApprovePostsCommand)
          DispatchScheduledPostsJob picks up at scheduled_at time
published post sent successfully to platform
failed    platform API returned an error — failure_reason column is set
```

Posts have `requires_approval = true` by default. `DispatchScheduledPostsJob` only dispatches `approved` posts whose `scheduled_at` is in the past.

---

## Social Platform Integrations

### Google Business Profile (GBP)

**Current status:** Working (confirmed live 25 September 2026).

**OAuth scopes required:** `https://www.googleapis.com/auth/business.manage`

**Key implementation note:** `GoogleBusinessProfilePlatform::getAccountsWithToken(string $accessToken)` accepts a plain token string (not an encrypted model) for use during the auth callback before a SocialConnection exists.

**Rate limiting:** GBP API enforces per-minute quotas. The cache key `gbp_locations_{userId}` stores fetched locations for 30 minutes to avoid repeat calls.

### Twitter/X

**Current status:** Working.

**Why Twitter uses manual PKCE (not Socialite):**
Socialite's `twitter-oauth-2` driver calls `$request->session()->put('code_verifier', ...)` inside `->redirect()`. This throws `Session store not set on request` because this is a stateless API with no session middleware.

Fix in `SocialConnectionController::redirect()`: for Twitter, we generate PKCE manually (code_verifier + code_challenge), store the verifier in our Redis state cache (`oauth_state_{$state}`), and build the Twitter auth URL directly. The callback retrieves the verifier from that cache and exchanges the code using Guzzle. All other platforms still use Socialite.

**Token expiry:** Twitter tokens expire after ~2 hours. Refresh token is stored and should auto-refresh.

**Cost:** X API requires paid credits. No free tier.

**Callback URL registered in console.x.com:** `https://api.postd.uk/api/auth/social/twitter/callback`

### LinkedIn

**Current status:** Working (confirmed live 25 September 2026). Company Pages only.

**Company Pages only, and not by choice.** LinkedIn requires the Community Management API to be the ONLY product on a developer app. That rules out "Sign In with LinkedIn using OpenID Connect" and "Share on LinkedIn" on the same app, so we hold no profile scope, `/v2/userinfo` is closed to us, and posting to a personal profile is impossible. Every author is an organisation URN.

There are therefore **two LinkedIn apps**. The original one (with Share on LinkedIn / OIDC) is kept only as the route to `w_member_social` should we ever add personal-profile posting — that scope cannot be added to the Community Management app. The new, dedicated app is the one whose credentials are in `.env`.

**Why LinkedIn does not use Socialite:**
Both Socialite LinkedIn drivers (`linkedin` and `linkedin-openid`) fetch a profile endpoint — `/v2/me` or `/v2/userinfo` — to build their user object. Neither scope exists on a Community-Management-only app, so both throw on callback. `SocialConnectionController` builds the authorisation URL and exchanges the code by hand instead, the same pattern Twitter uses and for a similar reason.

**OAuth scopes required:** `r_organization_admin`, `r_organization_social`, `w_organization_social` — kept in `LinkedInPlatform::SCOPES`. Requesting a scope the app was not provisioned makes LinkedIn reject the whole dialog rather than ignore the one bad entry, so keep that constant in step with the app's Auth tab.

**API surface:** versioned REST only (`/rest/posts`, `/rest/organizationAcls`, `/rest/images?action=initializeUpload`). Every call needs a `LinkedIn-Version: YYYYMM` header from `LINKEDIN_API_VERSION`; the legacy `/v2/ugcPosts` and Vector Asset endpoints answer the organisation APIs with 426 Upgrade Required. Versions retire about a year after release, so this needs bumping periodically — it is env-driven to avoid a deploy.

**Post IDs:** `/rest/posts` returns 201 with an empty body. The new post's URN arrives in the `x-restli-id` response header, not the payload.

**Token expiry:** 60 days, and **there is no automatic renewal**. Refresh tokens are a LinkedIn partner privilege, and unlike Facebook there is no exchange-a-still-valid-token trick. Users must reconnect every 60 days; `RefreshSocialTokensCommand` warns them beforehand via the existing no-refresh-token path.

**A user who administers no Company Page** gets a connection with zero accounts. The callback catches this and redirects to `/platforms?error=linkedin_no_pages` rather than letting the first scheduled post fail days later.

**Callback URL registered in the LinkedIn app:** `https://api.postd.uk/api/auth/social/linkedin/callback`

### Google Sign-in (primary auth method)

Handled by `GoogleAuthController`. The callback flow:
1. Create or find user by Google email
2. Cache raw tokens: `google_tokens_{userId}` (30 min TTL)
3. Attempt to fetch GBP locations and cache them: `gbp_locations_{userId}` (30 min TTL)
4. If `$user->business()->where('onboarding_complete', true)->exists()` → redirect to `/dashboard`
5. Otherwise → redirect to `/onboarding`

**OAuth app is in Testing mode.** To add test users:
Google Cloud Console → APIs & Services → OAuth consent screen → Test users

---

## Onboarding Flow (5 steps)

1. **Business info** — name, industry, tone. Shows GBP location picker if `gbp_locations_{userId}` cache exists, otherwise shows text search fallback.
2. **Website URL**
3. **Google Reviews URL** — auto-populated from cached GBP location `review_url` if available
4. **Connect platforms** — GBP shown as already connected for Google sign-in users
5. **Done** — calls `POST /api/onboarding/complete`, sets `onboarding_complete = true`, redirects to dashboard

**GBP location picker not showing?** The cache (`gbp_locations_{userId}`) is set during Google OAuth callback. If the GBP API 429s during the callback, the cache is empty and the text fallback shows instead. The `GET /api/onboarding/gbp-locations` endpoint also tries to populate from the cached token as a second attempt.

---

## Content Generation

Uses Anthropic Claude API via direct HTTP (not the OpenAI PHP SDK).

**Model in use:** `claude-haiku-4-5-20251001`

**Available models on our API key** (confirmed March 2026):
- `claude-haiku-4-5-20251001` — use this (cheapest, fastest)
- `claude-sonnet-4-6`
- `claude-opus-4-6`

**Do NOT use** (not on our key):
- `claude-3-5-sonnet-20241022`
- `claude-3-5-haiku-latest`
- Any model with `-20241022` or earlier date suffix

**How posts stay relevant and new** (`ContentGenerationService`):
- Every post takes an *angle* from `ANGLE_ROTATION`. Quote angles lift a real review or website line word for word; every other angle is written from one specific page of the business's site (`ANGLE_PAGE_KINDS`), taking the page gone longest without use. `ai_metadata.page_id` records which.
- `ScrapeBusinessJob` reads the sitemap and homepage links (up to 10 extra pages: services, about, FAQ, newest articles) and keeps `structured_data.page_first_seen`. A page first seen in the last 21 days gets a `whats_new` post once per platform, ahead of the rotation.
- Each draft is compared (word-trigram overlap) with the last 20 posts; above 0.3 it is rewritten once and the less similar draft kept.
- `HashtagGuard` strips any hashtag whose words are not in the business's own context (name, industry, location, website, reviews) or a short generic list, plus repeats and anything over the platform's `max_hashtags`. Removed tags are logged and kept in `ai_metadata.removed_hashtags`. Publishers send `content` only; the `hashtags` column is informational.

**Post images: real photos first, AI second.** Facebook, LinkedIn and GBP posts get a picture when the business setting `generate_images` is on (X never does).
- `ImageLibraryService::pickForPost()` runs first, at post creation. It prefers an enabled photo from the same website page the post was written from, then Google and uploaded photos, then any; skips anything used in the last 21 days if something else is available; least recently used first. The rules live in the pure `ImagePicker`. The chosen URL goes straight into `media_urls` and `ai_metadata.image = {source, business_image_id}`.
- Only when no library photo fits does `GeneratePostImageJob` make an AI image (GPT Image model, gated by `POST_IMAGES_ENABLED` and the plan's AI image allowance). The AI image is also recorded in `business_images` with `source = ai` so the owner sees it, but the picker never reuses AI images.
- `POST_IMAGES_ENABLED` switches off AI images only. Library photos are free, ignore that switch and do not count towards the allowance (which counts `AiCostLog` rows).
- Where photos come from: `WebsiteScraperService` returns `image_candidates` (found by the pure `ImageCandidateExtractor`: og:image, img/srcset/lazy attributes, gallery links, inline CSS backgrounds; skips logos, icons, badges, header/nav/footer images and third-party hosts). `ImportBusinessImagesJob` downloads at most 15 new website photos a run and 60 in total, then reads the GBP v4 `accounts.locations.media` list at most once a week (`businesses.images_google_synced_at`), skipping LOGO/PROFILE. Photos must be at least 600x400 with an aspect ratio between 0.5 and 2.2; everything is stored as JPEG, long side at most 2048px, under `library/{business_id}/` on Spaces, deduplicated by SHA-1 per business.
- Owners manage the library on `/photos`: switch photos off (stock images they only licensed for their website), delete (a photo still on an unpublished post is switched off instead), upload (JPEG/PNG/WebP up to 15MB; HEIC is refused with instructions), and trigger a check (once an hour). A single post's image can be removed on the Posts page (`PUT /posts/{id}` with `media_urls: []`).

---

## Plans and Entitlements

**VAT:** dijitul is VAT registered. Prices are quoted plus VAT (`VAT_SUFFIX` in `web/src/lib/plans.js`), and `User::taxRates()` makes Cashier attach the `STRIPE_TAX_RATE_VAT` rate to every new subscription and Checkout session. Checkout collects a billing address and the customer's VAT number. Subscriptions created before the rate existed are not changed automatically, because adding VAT raises their bill.

**Stripe setup:** `php artisan billing:setup-stripe --dry-run`, then without `--dry-run`. It creates the VAT rate and any missing prices (Agency monthly and annual, Local and Growth annual, extra location) with amounts read from `config/plans.php`, reuses the existing Starter and Growth products, tags everything with lookup keys so a rerun never duplicates, and prints the `.env` lines. Run it against a test key first.

Approved structure (full reasoning in `docs/marketing/pricing-and-platforms.md`):

| | Local £19/mo | Growth £39/mo (trial tier) | Agency £79/mo |
|---|---|---|---|
| Locations | 1 | 1 | 3, then +£19/mo each |
| Platforms | any 2 of GBP, Facebook, LinkedIn (no X) | all four | all four, per location |
| Posts a week, per platform | 3 | 7 | 14 (GBP 7) |
| AI images a month | 5 | 30 | 100, pooled across locations |
| Analytics history | 30 days | 12 months | 12 months |

Every plan: autopilot or approval, review-driven posts, regenerating. Annual = 2 months free (£190 / £390 / £790). Prices show with no VAT wording for now; the suffix is `VAT_SUFFIX` in `web/src/lib/plans.js`.

**Trial:** 14 days on Growth, no card. X capped at 10 posts and AI images at 10 for the whole trial. When it ends, posting pauses (see below); nothing is deleted.

**Legacy plans:** `starter` is an alias of `local` (same Stripe price, `STRIPE_PLAN_LOCAL` falls back to `STRIPE_PLAN_STARTER`). `pro` is not sold any more; existing Pro subscribers keep £69 with Agency entitlements. Migration `2026_09_25_150000` renamed stored `comped_plan` values and dropped the unused `plan_features` table.

### One source of truth

- `api/config/plans.php` holds every plan, price, Stripe price env var and limit. Change a limit there and every enforcement point follows. `config/cashier.php` no longer has plans.
- `PlanCatalogue` reads it (plan lookup, `starter` alias, Stripe price to plan and interval, MRR value).
- `EntitlementService::forUser()` builds an `Entitlements` object for the user's current plan and trial state. `Entitlements` is pure arithmetic and covered by `tests/Unit/EntitlementsTest.php` (no database).
- `GET /billing/plans` serves plans, availability and the account's usage (`account`) to the Billing page. The landing page pricing is hardcoded in `web/src/lib/plans.js` because it is prerendered; keep it in step with `config/plans.php`.
- A Stripe price env var left blank marks that option unavailable; the Billing page hides it rather than erroring.

### Where each limit is enforced

| Limit | Where |
|---|---|
| Platform count, X on Local | `SocialConnectionController::redirect()` (403 with a worded reason) and again in `callback()`; `GoogleAuthController` will not auto-add GBP past the limit. Reconnecting an existing platform is always allowed. |
| Which platforms get posts | `ContentGenerationService::generateFromBrief()` uses `Entitlements::usablePlatforms()`: allowed platforms, oldest connection first, up to the limit. Extra connections are shown as "paused on your plan", never deleted. |
| Posts a week | Clamped in `ContentGenerationService::postsPerWeekTarget()`; refused above the plan in `OnboardingController::updateSettings()`. The saved setting is kept, so upgrading again restores it. |
| Scheduler spacing | `SchedulingService::minGapMinutesFor()`, shared by the scheduler and `ContentGenerationService`: 48h up to 4 a week (unchanged), 20h up to 7 (one a day), 7h up to 14 (two a day). Above every other day, a candidate past the day's last slot goes to tomorrow rather than the next preferred day. The old fixed 48h gap capped every platform at 4 a week. Tested without a DB in `SchedulingSpacingTest`. |
| AI images | Checked when queued and again in `GeneratePostImageJob::handle()` (one run queues several). Past the allowance the post goes out text-only. There is no stock-photo fallback yet. |
| X on trial | `ContentGenerationService` holds X generation to what is left of the 10. |
| Locations | `OnboardingController::createBusiness()` with `new_location`. An extra Agency location returns 402 with the price until the user confirms, then `SubscriptionService::addExtraLocation()` adds the add-on price to the subscription. |
| Analytics history | `AnalyticsController::period()` holds `from` to the plan's history. |

Limits are only checked when something new is created. Nothing at publish time consults them, so a post already scheduled always goes out.

**No plan (trial over, subscription lapsed):** `DispatchScheduledPostsJob` skips those accounts, so scheduled posts stay scheduled (paused). Generation stops. When a plan is chosen (`subscribe()` or the `customer.subscription.created` webhook), `SubscriptionService::resumePausedPosts()` gives overdue posts fresh slots so they do not all go out at once.

### Multiple locations

A user can own several businesses. `users.current_business_id` records which one they are working on, and `User::business()` orders that one first, so every `$user->business` call site follows the switch. `GET /businesses` and `POST /businesses/{id}/switch` back the header switcher (`LocationSwitcher.jsx`); "Add a location" runs `/onboarding?new=1`.

### Checkout

`POST /billing/checkout {plan, interval}`: a new subscriber gets a Stripe Checkout URL (trial days are kept via `trialUntil`); an existing subscriber is swapped straight away (`SubscriptionService::changePlan()`), carrying Agency extra locations across. A downgrade that would leave more locations than the new plan covers is refused with an explanation. Payment details and cancelling go through the Stripe portal.

---

## Database Notes

**PostgreSQL-specific gotchas:**

- `raw_token_data` on `social_connections` is `text` type (not `json`). Laravel's `encrypted:array` cast produces a cipher string. Migration `2026_03_22_210000_fix_social_connections_raw_token_data_column.php` changed it from `json` to `text`.
- `failed_jobs` has no `created_at` — use `orderByRaw('id DESC')` not `->latest()`.
- Posts column is `scheduled_at` (not `scheduled_for`).

**Useful tinker snippets:**

```php
// Full user state check
$user = \App\Models\User::where('email', 'you@example.com')->first();
$business = $user->business;
$connections = $business->socialConnections()->get();
foreach ($connections as $c) {
    echo "{$c->platform} | active: {$c->is_active} | expires: {$c->expires_at}\n";
    foreach ($c->platformAccounts as $a) {
        echo "  account: {$a->account_name} | selected: {$a->is_selected}\n";
    }
}

// List posts
$posts = $business->posts()->orderBy('created_at','desc')->limit(10)
    ->get(['id','platform','status','scheduled_at','created_at']);
foreach ($posts as $p) {
    echo "{$p->platform} | {$p->status} | {$p->scheduled_at}\n";
}

// Reset a user's onboarding completely
$user->businesses()->each(function ($b) {
    $b->socialConnections()->each(fn($c) => $c->forceDelete());
    $b->posts()->delete();
    $b->delete();
});
\Illuminate\Support\Facades\Cache::forget("google_tokens_{$user->id}");
\Illuminate\Support\Facades\Cache::forget("gbp_locations_{$user->id}");

// Check failed jobs
DB::table('failed_jobs')->orderByRaw('id DESC')->first();

// Clear all jobs from a queue (use when 429 storm occurs)
// Run on server: php artisan queue:clear redis --queue=generation
```

---

## Known Issues & Pending Work

| Issue | Priority | Notes |
|---|---|---|
| `ScrapeBusinessJob` crashes | Fixed | `GoogleReviewsService::$apiKey` is `?string` and the service returns early when the key is unset, so a null `GOOGLE_PLACES_API_KEY` no longer throws. |
| `NotifyTrialEndingCommand` bug | Fixed | `scopeOnTrial` collided with Cashier's `Billable::onTrial()`, so the static call returned a bool. Scope renamed to `scopeTrialing` — use `User::trialing()`. |
| Jobs from tinker not queued | Known quirk | Run via `->handle()` in tinker instead of `::dispatch()` |
| Settings page testing | Needs verification | Previously not saving correctly |
| Retry button for failed posts | Built | `POST /posts/{id}/retry` plus a retry action on the failed filter in `PostsPage.jsx`. Note there is no separate Inbox page — it is the Posts page filtered by status. |
| Twitter token refresh | Untested | First live test will be when the current token expires (~2 hours post-connect) |
| `LINKEDIN_API_VERSION` unverified | Verify before launch | Set to `202506` as a placeholder. Confirm against LinkedIn's current version list; an unsupported value fails every `/rest/*` call. |
| Stripe prices for new plans | Needs setting up | Agency, the three annual prices and the extra-location add-on need creating in Stripe and their IDs adding to `.env`. Until then those options are hidden on the Billing page. |
| Google sign-ups had no trial | Fixed for new users | `GoogleAuthController` never set `trial_ends_at`. New Google sign-ups now get the 14-day trial; existing Google users without a subscription or comp have no plan and are paused until comped or subscribed. |
| Stripe webhooks failed to load | Fixed | `StripeWebhookController` redeclared Cashier's protected `getUserByStripeId()` as private, a fatal error on class load. |
| Starter cadence grace period | Not built | The pricing doc says Starter customers above 3 posts a week keep that cadence for 6 months. Not implemented: Local is held to 3 now. |
| Removing a location | Not built | There is no endpoint to remove a business or reduce the extra-location quantity. Handle by hand in Stripe and the database. |
| LinkedIn 60-day reconnect | By design, needs UX | No refresh token is possible. Users must manually reconnect every 60 days — worth a more prominent prompt than the standard expiry email. |

---

## Development Workflow

**Always edit local files at `D:\Git\postd.uk`, never on the server directly.** The server auto-deploys on push to main. Direct server edits are overwritten on the next deploy.

```powershell
# Standard workflow
cd D:\Git\postd.uk
git status
git add path/to/file.php path/to/another.jsx
git commit -m "Brief description of what changed and why"
git push
```

After pushing:
- Server pulls, migrates, recaches, restarts workers automatically
- Check deploy succeeded: `tail -f /var/www/postd/api/storage/logs/laravel.log`

---

## Google Cloud Console

- **OAuth app** in Testing mode — add test users before they can sign in
- **My Business Account Management API** — allowlisted and working
- **Registered callback URLs:**
  - `https://api.postd.uk/api/auth/google/callback` (sign-in)
  - `https://api.postd.uk/api/auth/social/google_business_profile/callback` (GBP connect)

---

## X Developer Console

- **URL:** https://console.x.com
- **App name:** postd
- **Auth:** OAuth 2.0 PKCE
- **Callback:** `https://api.postd.uk/api/auth/social/twitter/callback`
- **Scopes:** `tweet.read tweet.write users.read offline.access`
- Requires paid credits to use the API

---

## Preferences & Conventions

- UK English throughout (colour, flavour, organise, etc.)
- No em-dashes anywhere — use commas or restructure the sentence
- Footer links to `https://dijitul.uk` only — we do not own `dijitul.io`
- All API responses are JSON
- Bearer token auth via Sanctum — stored in `localStorage` as `postd_token`
- Axios base URL in `web/src/lib/api.js` already includes `/api` — never add it again in endpoint paths
- Post content is written in a friendly, approachable tone by default
- Always test locally and push — never hotfix production files
