# postd.uk: platform roadmap and pricing structure

Prepared September 2026. Competitor prices were checked live on the date of writing. USD and EUR figures are converted at roughly $1 = £0.75 and €1 = £0.85.

---

## Part 1: Text-first platforms to integrate

Instagram and TikTok are being dropped. The product posts written, local and review-led content, so the useful question for each platform is whether a UK trade, salon or cafe can reach local customers there through an API we are allowed to use.

### Ranked recommendation

| Rank | Platform | API and cost (2026) | Approval hurdle | UK SMB relevance | Decision |
|---|---|---|---|---|---|
| 1 | **GBP offers and events** (new post types) | Local Posts API still supports STANDARD, EVENT and OFFER posts, plus the new recurring posts. Free. | None. Uses the GBP access we already have. | Highest. Offers and events show on Maps and Search, where local customers look. | **Build now.** It is a post type, not a new integration. |
| 2 | **Threads** | Graph-style API, free, capped at 250 publishes per user per day. Text-first. | Meta App Review for `threads_basic` and `threads_content_publish` takes 2 to 6 weeks. We already have Meta business verification from Facebook Pages. | Good. The UK is one of Threads' top markets, it has 500m MAU worldwide, and the audience overlaps with Facebook. | **Build next.** Apply for review now so approval arrives with the code. |
| 3 | **Monthly email digest** (Mailchimp first, own sending later) | Mailchimp Marketing API is free on the customer's own account. Own sending through SES or Postmark costs pennies per thousand. | None. The business is the data controller, so onboarding needs a clear PECR/GDPR consent statement. | High. It is an owned audience, it suits repeat-visit businesses (salons, cafes), and it reuses the posts we already generate. | **Build in Q1 as a Growth feature.** Start with a Mailchimp connection so we avoid deliverability and list-hygiene liability. |
| 4 | **Bluesky** (AT Protocol) | Open, free, no rate card. OAuth or app password. Roughly a day of work. | None. | Low to moderate. About 7% of its 46m registered users are in the UK. It skews to media and professionals, not local trades. | **Add cheaply** as an extra channel. It costs almost nothing to run. |
| 5 | **Nextdoor** | A Publish API exists and posts come from the business's own account, but it is partner-gated (current partners include Orlo and ZenCity). | Application-only, with no published criteria and no clear UK availability. | Excellent fit: neighbourhood recommendations for trades. | **Apply now, do not build** until access is granted. It could be the biggest differentiator if we get in. |
| 6 | **Mastodon** | Open, free, per-instance OAuth. | None. | Negligible for local SMBs. | Park. Revisit only if customers ask. |
| 7 | **Telegram channels** | Bot API, free and trivial. | None. | Very low in the UK SMB segment. | Park. |
| 8 | **WhatsApp Channels** | The official Cloud API does not expose Channels. Only unofficial wrappers can post, and they breach Meta's terms. | Not possible legitimately. | High in theory. | **Do not build.** It risks bans and puts our Meta app in jeopardy. |
| 9 | **Reddit** | Commercial use needs written approval and a paid agreement (about $0.24 per 1,000 calls). New Data API access is being restricted in favour of Devvit (announced 5 Aug 2026). | Heavy approval process, and the API is shrinking. | Poor. Communities are hostile to automated business self-promotion. | **Do not build.** |
| 10 | **Facebook Groups** | Removed by Meta on 22 April 2024. It has not been reinstated as of Graph v26. | Impossible. | n/a | **Not possible.** |
| n/a | **GBP Q&A** | Q&A API discontinued 3 Nov 2025 and replaced by Ask Maps. | n/a | n/a | Not possible. |
| n/a | **Pinterest** | Visual platform. | n/a | n/a | Out of scope. |

**Planned channel set at launch of the new plans:** GBP (standard, offers, events), Facebook Pages, LinkedIn Company Pages and X. Threads and Bluesky follow, then the email digest. Nextdoor is added if approved.

---

## Part 2: Pricing structure

### Cost to serve (what the price must cover)

| Cost item | Unit cost | Notes |
|---|---|---|
| Claude Haiku 4.5 generation | about £0.002 per post | Negligible even at 14 posts a week across 4 platforms. |
| AI image (OpenAI) | about £0.03 to £0.06 per image | The only material generation cost. |
| **X post, no link** | $0.015 (about £0.011) | Pay-per-use. There has been no free tier since the 2026 migration. |
| **X post with a URL** | **$0.20 (about £0.15)** | 13 times the cost of a plain post. X posts should be link-free by default, with the URL moved to the bio. |
| Stripe (UK cards) | 1.5% + 20p | Charged on the VAT-inclusive amount. |
| Hosting, scraping, email, support allocation | about £0.50 to £1.00 per business per month | |

**Fully loaded variable cost per business:** Local about £1.30, Growth about £5.20 (worst case with X links included), Agency about £16 for 3 locations with heavy X use. Every tier clears 80% gross margin. The only cost lever that matters is X with links, and the tier design below keeps it bounded.

### Market context

| Competitor | Entry price (approx. GBP/month) | What you actually get | Relevance |
|---|---|---|---|
| **Localo** (closest rival) | Single Business $49 (about £37), or $39 (about £29) annually, + VAT | 1 GBP profile, AI posts to Google and Facebook | Direct comparator. Pro plan is $169. |
| Birdeye | $299 to $449 per location | Reviews, listings and social AI | Enterprise ceiling. Proves local businesses value review-led content. |
| SocialBee | $29 (about £22), 5 profiles | AI assist, but the user drives it | Scheduler, not done-for-you. |
| Ocoya | $29 (about £22), 5 profiles, 300 AI credits | AI assist | Same as above. |
| FeedHive | €15 (about £13), 4 accounts, 30 scheduled posts | Scheduler with AI credits | Same as above. |
| Predis.ai | $32 (about £24). No auto-posting until Rise at $79 (about £59) | Visual-first AI | Different product. |
| Buffer | $5 (about £3.75) per channel | Scheduler only. You write the posts. | Price anchor for "just scheduling". |
| Publer | $5 plus $4 per extra account | Scheduler only | Same as above. |
| Hootsuite | $99 per user per month (annual) | Enterprise scheduler | Irrelevant for SMBs, but a useful "expensive" anchor. |
| Semrush Local / Moz Local | $14 to $30 per location | Listings management, limited posting | Adjacent. |

**Positioning:** postd is done-for-you rather than a scheduler, so the benchmark is Localo, not Buffer. The recommended structure undercuts Localo at entry, with more platforms and review-driven content, and matches it at the mid tier while offering four times the channels.

### Recommended structure: three tiers with an annual option

Prices are shown **ex VAT, as B2B is the norm**, with the inc-VAT price alongside because many sole traders are not VAT-registered and see the gross figure. Use Stripe Tax so the checkout shows both. If dijitul is not VAT-registered, the ex-VAT column is simply the price.

| | **Local** | **Growth** (recommended, trial tier) | **Agency** |
|---|---|---|---|
| Monthly, ex VAT | **£19** | **£39** | **£79** |
| Monthly, inc VAT | £22.80 | £46.80 | £94.80 |
| Annual, ex VAT (2 months free) | £190 (about £15.83/mo) | £390 (£32.50/mo) | £790 (£65.83/mo) |
| Businesses / locations | 1 | 1 | 3 included, then +£19 ex VAT per extra location |
| Platforms | Any 2 of GBP, Facebook, LinkedIn (plus Threads and Bluesky when live) | All platforms | All platforms, per location |
| X / Twitter | No | Included, up to 7 posts/week, link-free by default | Included, up to 7 posts/week per location |
| Posts per week, per platform | Up to 3 | Up to 7 | Up to 14 (GBP is capped at 7, since more does not help ranking) |
| GBP offers and events | Yes | Yes | Yes |
| Review-driven posts | Yes | Yes | Yes |
| Approval or autopilot | Both | Both | Both, plus a shareable client approval link |
| AI images | 5 per month, then free stock photos | 30 per month, then stock | 100 per month pooled, then stock |
| Regenerate a post | Unlimited | Unlimited, priority queue | Unlimited, priority queue |
| Monthly email digest (when live) | No | Yes | Yes |
| Analytics | Last 30 days | 12 months, with a monthly summary email | 12 months, per-location PDF report |
| Team seats | 1 | 2 | 5 |
| Support | Email | Email, faster response | Priority, with a named contact at dijitul |

**Design rules behind the table:**

- **Autopilot, approval and review posts are on every tier.** They are the core promise, and paywalling them would make Local feel broken. Local is a complete product: GBP and Facebook at 3 posts a week each is 26 posts a month, which is more than most UK SMBs have ever managed.
- **Limits never stop a post going out.** When AI images run out, posts fall back to licensed stock photography rather than going without an image. A cadence above the tier limit is shown as a locked option with the upgrade price, never as an error.
- **X is on Growth and above** because it is the only channel with a per-post cost. Link-free X posts keep the worst case at about £0.33 a month, or £2.10 at 7 a week even if the customer turns links on.
- **Agency is priced per location.** Three locations for £79 costs less than two Growth plans at £78 plus one more, so the second and third locations push customers naturally towards Agency.

### Sensitivity analysis (Growth, the anchor tier)

Assumptions: 15% trial-to-paid at £39, price elasticity of -1.2 (typical for SMB SaaS, to be validated), variable cost £4.30 per month before Stripe fees.

| Price (ex VAT) | Trial-to-paid | Paying per 100 trials | MRR per 100 trials | Margin per sub | Gross profit per 100 trials |
|---|---|---|---|---|---|
| £31 (-20%) | 18.6% | 18.6 | £577 | £25.94 (83.7%) | £482 |
| £35 (-10%) | 16.8% | 16.8 | £588 | £29.87 (85.3%) | £502 |
| **£39 (rec.)** | **15.0%** | **15.0** | **£585** | **£33.80 (86.7%)** | **£507** |
| £43 (+10%) | 13.2% | 13.2 | £568 | £37.73 (87.7%) | £498 |
| £47 (+20%) | 11.4% | 11.4 | £536 | £41.65 (88.6%) | £475 |

Profit is flat between £35 and £43, and £39 sits at the top of that range. £39 is also the current Growth price, so existing Growth customers need no migration. If trial conversion comes in above 20% after 90 days, test £45: at that rate the curve moves up in price.

### Upgrade trigger moments (prompt in context, never by nag email)

1. **Connecting a 3rd platform on Local.** Show "Growth adds every platform, including X", with a one-click upgrade.
2. **Moving the posts-per-week slider past 3.** The locked notches show "7 a week on Growth".
3. **Clicking Connect X.** Explain that X charges per post, which is why it sits on Growth.
4. **Using the 5th AI image of the month.** Offer Growth, or tell them stock photos continue for free.
5. **Adding a second business.** Offer Agency ("3 locations for £79").
6. **Opening analytics older than 30 days.** Show a blurred 12-month view with an upgrade link.
7. **Day 45 on a monthly plan.** A single email offering annual (2 months free), sent only after they have seen results.

### Trial design

- **14 days on Growth, no card required.** The buyer is a busy owner, sign-up is already one Google click, and the value only shows once posts go live. That needs platform connections, which take time and currently depend on GBP and LinkedIn approvals. A card wall would cut sign-ups more than it lifts conversion.
- **Guardrails:** X is capped at 10 link-free posts and AI images at 10 during the trial. The cost exposure is under £1 per trial.
- **Day 10:** a "what postd did for you" email with posts published, review quotes used and views, plus plan choice.
- **Day 14 with no plan:** scheduled posts pause and nothing is deleted. Choosing Local lets the user pick which 2 platforms stay active, and the rest pause. There is no free tier: the business model is done-for-you, and a free tier would carry X and image costs for nobody.

### Discount discipline

- Annual billing (2 months free) is the only standing discount.
- There are no ad-hoc coupons. A 20% "dijitul client" rate applies only to businesses on a dijitul retainer, reviewed yearly.
- Agency volume pricing above 10 locations is negotiated, with a floor of £12 ex VAT per location.

### Existing subscribers

| Current plan | Moves to | Handling |
|---|---|---|
| Starter £19 | Local £19 | Same price. They gain GBP offers and events, and review posts are confirmed. Anyone set above 3 posts a week keeps that cadence for 6 months, then drops to 3 unless they upgrade. |
| Growth £39 | Growth £39 | Same price. They gain X and the email digest. |
| Pro £69 | Agency features at **£69 locked for as long as they stay subscribed** | They lose TikTok and Instagram, so they keep their price and gain 3 locations. |
| Anyone with Instagram or TikTok connected | n/a | 60 days' notice before disconnection, and one month's credit if either was one of their paid platforms. |

Implementation: create new Stripe Prices and keep the legacy Price IDs active and hidden so existing subscriptions are not touched. Map the legacy Price IDs to the new entitlements in the Billing module. Give 30 days' notice for any change to what a customer receives.

### Review cadence

- **Day 30:** trial-to-paid rate by tier, and how many Local customers hit the platform or cadence limit.
- **Day 90:** re-run the sensitivity analysis using real conversion data, and check actual X spend per Growth customer.
- **Every 6 months:** re-check X API pricing (it has changed three times in 2026), Localo's price, and the Nextdoor application.

---

### Sources

- Buffer pricing: https://buffer.com/pricing
- Hootsuite plans: https://www.hootsuite.com/plans
- SocialBee pricing: https://socialbee.com/pricing/
- Publer pricing: https://publer.com/help/en/article/what-are-publers-plans-and-pricing-15h4yqh/
- FeedHive pricing: https://feedhive.com/pricing
- Ocoya pricing: https://www.ocoya.com/pricing
- Localo pricing: https://localo.com/pricing
- Predis.ai pricing: https://predis.ai/pricing/
- Birdeye pricing: https://wiserreview.com/blog/birdeye-pricing/
- X API pay-per-use: https://docs.x.com/x-api/getting-started/pricing and https://postproxy.dev/blog/x-api-pricing-2026/
- Threads API: https://www.blotato.com/blog/threads-api-pricing and https://singhamandeep.com/threads-api-app-review-permissions/
- Threads 500m MAU: https://about.fb.com/news/2026/06/meta-launching-new-features-500-million-monthly-threads-users/amp/
- Bluesky statistics: https://www.businessofapps.com/data/bluesky-statistics/
- Nextdoor Publish API: https://developer.nextdoor.com/docs/sharing-overview
- WhatsApp Channels API gap: https://whapi.cloud/blog/whatsapp-channel-api-automation
- GBP Q&A discontinued, Local Posts active: https://www.seroundtable.com/google-business-api-questions-and-answers-deprecating-40113.html and https://slashpost.ai/blogs/google-business-profile/google-business-profile-api-documentation-2026
- Facebook Groups API removal: https://multiplegroupposter.com/blog/facebook-groups-api-discontinued/
- Reddit Data API 2026: https://www.socialcrawl.dev/blog/reddit-data-api-2026 and https://prowlo.com/blog/reddit-data-api
