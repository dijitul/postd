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
| **Billing** | Stripe subscriptions via Cashier |
| **Analytics** | Post performance data |
| **Admin** | Dijitul team dashboard (impersonate, health checks) |
| **Notifications** | Email notifications (trial ending, post failed, etc.) |

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
DispatchScheduledPostsJob       runs every minute via cron, dispatches due posts
GoogleAuthController            handles Google sign-in OAuth callback
```

---

## Frontend Pages

```
/                   Marketing landing page
/login              Login (Google primary, email/password secondary)
/register           Register (Google primary, email/password hidden by default)
/auth/callback      Handles Google OAuth redirect (stores token, redirects)
/onboarding         5-step business setup wizard
/dashboard          Main dashboard
/posts              Content library
/inbox              Pending approval queue
/platforms          Connected social accounts
/settings           Business settings
/billing            Subscription management
/admin              Dijitul team only
```

---

## Server Details

**Provider:** DigitalOcean droplet
**IP:** 144.126.207.135
**OS:** Ubuntu 24.04
**SSH user:** root
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

**Current status:** Awaiting Google's allowlisting approval. Apply at:
https://support.google.com/business/contact/api_default — select "Application for Basic API Access"

Until approved, GBP posts fail with 429/403. The `mybusinessaccountmanagement.googleapis.com/v1/` endpoint has effectively zero quota for unlisted apps.

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

**Current status:** Awaiting LinkedIn's Community Management API approval. Code is complete and deployed; connecting will fail until the product is granted.

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

**Image generation:** Uses OpenAI DALL-E via `GeneratePostImageJob`. Requires `OPENAI_API_KEY`.

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
| GBP API quota 0 | Blocked on Google | Applied for Basic API Access. GBP posts fail until approved. |
| GBP location picker blank | Blocked on Google | Requires GBP API to list locations |
| `ScrapeBusinessJob` crashes | Fix needed | `GOOGLE_PLACES_API_KEY` is null, `GoogleReviewsService::$apiKey` is typed `string` — needs to be `?string` |
| `NotifyTrialEndingCommand` bug | Fixed | `scopeOnTrial` collided with Cashier's `Billable::onTrial()`, so the static call returned a bool. Scope renamed to `scopeTrialing` — use `User::trialing()`. |
| Jobs from tinker not queued | Known quirk | Run via `->handle()` in tinker instead of `::dispatch()` |
| Settings page testing | Needs verification | Previously not saving correctly |
| Retry button in Inbox | Not built | Failed posts need a retry action in the UI |
| Twitter token refresh | Untested | First live test will be when the current token expires (~2 hours post-connect) |
| LinkedIn Community Management API | Blocked on LinkedIn | Access form submitted, awaiting review. Code complete but untested end to end — connecting fails until the product is granted. |
| `LINKEDIN_API_VERSION` unverified | Verify before launch | Set to `202506` as a placeholder. Confirm against LinkedIn's current version list; an unsupported value fails every `/rest/*` call. |
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
- **My Business Account Management API** — quota 0, awaiting allowlisting
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
