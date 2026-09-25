# postd.uk: AI engine optimisation (AEO/GEO) plan

Date: 25 September 2026
Owner: Olly (dijitul)
Companion doc: `seo-content-plan.md` (Google rankings). This doc covers being **cited and recommended by AI assistants**: ChatGPT, Claude, Gemini, Perplexity, Google AI Overviews and Copilot. The two overlap but are not the same game. A page can rank on Google and still never be quoted by ChatGPT.

Shipped with this plan:

- `web/public/llms.txt` (ready to deploy)
- `web/public/robots.txt` (ready to deploy)

---

## 0. The short version

| Question | Answer |
|---|---|
| Are we cited by any AI engine today? | No. A web search for `"postd.uk"` returns nothing about us, only Royal Mail and postcode pages. We have no third-party mentions for an engine to find. |
| Can AI crawlers read postd.uk? | Barely. They get `index.html`: a title, a meta description and one JSON-LD block. The page body is an empty `<div id="root">`. GPTBot, ClaudeBot and PerplexityBot do not run JavaScript. |
| Is what they *can* read correct? | No. It says we post to Instagram (being dropped), omits Google Business Profile from the description, links our maker to `dijitul.io` (a domain we do not own), and quotes prices that are being revised. |
| Who wins our prompts today? | SocialBee, Buffer, SocialPilot, Circleboom, LocalHQ, Localo, OneUp, Publer, Metricool, Zoho Social, Apaya, ClickGrow. None is UK-built. |
| What sources do the engines lean on? | Reddit (the most cited domain across every engine in 2026 studies), YouTube, "best X tools" listicles, G2/Capterra, vendor comparison pages. |
| Biggest single fix | Serve real HTML to crawlers (pre-render the marketing routes). Nothing else matters much until that is done. |
| Biggest opening | "Done for you" plus "UK" plus "Google Business Profile" is a gap. The listicles are all US schedulers where you still write the posts. |

**What this plan can and cannot promise.** AI answers are non-deterministic and change with model updates. These fixes improve the likelihood of being cited. They do not guarantee it. Every number below is a snapshot.

---

## 1. Baseline: who AI engines recommend today

### 1.1 Method and its limits

I could not log into ChatGPT, Gemini or Perplexity from this session, so this baseline is built from the **web sources those engines retrieve and cite** (live search results for each prompt, plus 2026 citation studies). That is a good proxy for Perplexity, ChatGPT search and AI Overviews, which ground answers in the same pages, and a weaker proxy for model-memory answers. Section 7 sets out the manual baseline Olly should run before shipping anything, so we have a real "before" number.

### 1.2 Proxy audit

| Prompt (as a buyer would type it) | Tools surfaced | Sources doing the surfacing | postd.uk present? |
|---|---|---|---|
| "best AI tool to post to Google Business Profile automatically" | Social Champ, Gain, LocalHQ, Circleboom, Localo, Lead Oracle, SocialBee, Post Planner | Vendor blogs ranking themselves (socialchamp.com, blog.gainapp.com, leadoracle.ai, socialbee.com), almcorp.com guide | No |
| "automated social media for UK small business" | Buffer, Later, Pallyy, Metricool, Hootsuite, SocialBee, Sprout, Agorapulse | Hootsuite, SocialPilot, Sprout, Zapier, bloggingwizard.com, aether-agency.co.uk (the one UK page: no GBP mention, no UK tools) | No |
| "cheap alternative to Buffer for a local business" | Publer, Zoho Social, Metricool, PromoRepublic, Blotato, SocialBu | sproutsocial.com, socialpilot.co, socialbu.com, statusbrew.com, **g2.com alternatives page** | No |
| "AI that writes social posts from my website and Google reviews" | Apaya, ClickGrow, SocialBee Copilot, SocialPilot AI Pilot, Reply Champion | apaya.com, clickgrow.ai (both self-ranking listicles), socialpilot.co, g2.com | No, and this is literally our product |
| "what should a plumber post on Facebook" | No tools; generic advice (before/after photos, tips, seasonal reminders, reviews, 3 to 4 posts a week) | webfx.com, scorpion.co, fieldpulse.com, getjobber.com, hookagency.com, wavegen.ai (a tool vendor that got in with a free templates page) | No |
| "social media scheduler with Google Business Profile, UK" | SocialBee, Zoho Social, Metricool, Circleboom, Eclincher, OneUp, Buffer, Planly | socialbee.com, eclincher.com, technologyadvice.com, g2.com discussion pages | No |

### 1.3 What the pattern tells us

1. **Self-ranking listicles work.** Apaya, ClickGrow, SocialBee, Gain and LeadOracle each publish "best X tools" pages with themselves at number one, and the engines cite them. It is the dominant pattern in this category. We can do the same, honestly (see 4.6).
2. **Trade "what to post" prompts are won by US vertical SaaS** (Jobber, FieldPulse, Scorpion) and by one AI tool (wavegen.ai) that published free templates. Nobody UK-specific owns them. That is the easiest content win on the list.
3. **G2 appears repeatedly** as the source for "alternatives" answers. We are not on it.
4. **The only UK listicle found (aether-agency.co.uk) mentions no UK tool and no GBP support.** It uses an answer-first "Quick answer" block and an FAQ, which is why it gets pulled into answers despite thin content.
5. **2026 citation studies**: Reddit is the most-cited domain across ChatGPT, Gemini, Perplexity, AI Mode and AI Overviews; YouTube, LinkedIn, Wikipedia and Forbes follow. The top 15 domains take roughly two thirds of all citations. Perplexity has swung towards YouTube in mid-2026. Listicles, articles and product pages are the formats cited out of proportion to their share of the web.

### 1.4 Competitor positioning map

| Competitor | Why engines pick them | Our angle against them |
|---|---|---|
| SocialBee | Huge blog, GBP-specific pages, "Copilot" AI | They still expect you to run a content calendar. We write and post it all. |
| Buffer | Brand recognition, free plan, simple | Buffer is a queue you have to fill. |
| LocalHQ / Localo | GBP autopilot posting | GBP only. We cover GBP plus Facebook, LinkedIn and X from one setup. |
| Apaya / ClickGrow | "Reads your reviews, posts automatically" | Closest rivals. US-first, US English. We are UK-built, UK English, UK time. |
| Publer / Zoho Social | Cheapest "Buffer alternative" | Cheap schedulers still need your time. Position on hours saved, not price. |

---

## 2. The prompts we want to be cited for

Twenty conversational prompts, grouped by intent. "Target asset" is the page that should earn the citation (see section 5 for the build list). Intent key: R = recommendation, C = comparison, H = how-to/advice, D = definitional.

| # | Prompt | Intent | Target asset |
|---|---|---|---|
| 1 | What's the best AI tool to post to Google Business Profile automatically? | R | `/google-business-profile-posting` + GBP listicle |
| 2 | Is there an app that writes and posts my social media for me? UK small business | R | Home page (pre-rendered) + FAQ |
| 3 | Automated social media posting for UK small businesses, what are my options? | R | UK listicle `/guides/best-social-media-automation-tools-uk` |
| 4 | Cheap alternative to Buffer for a local business | C | `/compare/buffer-alternative` |
| 5 | Buffer vs Hootsuite vs postd for a one-person business | C | `/compare/postd-vs-buffer`, `/compare/postd-vs-hootsuite` |
| 6 | AI that writes social posts from my website and Google reviews | R | Home + "How it works" section + FAQ |
| 7 | What should a plumber post on Facebook? | H | `/guides/what-to-post/plumbers` |
| 8 | What should an electrician post on Google Business Profile? | H | `/guides/what-to-post/electricians` |
| 9 | Social media post ideas for a hair salon in the UK | H | `/guides/what-to-post/hair-salons` |
| 10 | What should a café post on social media each week? | H | `/guides/what-to-post/cafes` |
| 11 | How often should a small business post on Google Business Profile? | H | `/guides/google-business-profile-posting-frequency` |
| 12 | Do Google Business Profile posts help local SEO? | D | Same guide, with a dated evidence table |
| 13 | How can I automate Google Business Profile posts? | H | `/google-business-profile-posting` |
| 14 | Can I use ChatGPT to write my business's social media posts, and is there something that does it automatically? | C | `/guides/chatgpt-vs-automated-posting` |
| 15 | How do I post to a LinkedIn company page automatically? | H | `/guides/linkedin-company-page-automation` |
| 16 | Social media management for tradespeople who don't have time | R | `/for/trades` |
| 17 | How much does a social media manager cost for a small business in the UK? (and cheaper options) | D | `/guides/social-media-manager-cost-uk` |
| 18 | How do I turn my Google reviews into social media posts? | H | `/guides/google-reviews-to-social-posts` |
| 19 | Best social media tools made in the UK | R | UK listicle + UK directory listings |
| 20 | Is postd.uk any good? / What is postd.uk? | D | Home, llms.txt, G2/Capterra/Trustpilot reviews, Product Hunt |
| 21 | Done-for-you social media for small business without hiring an agency | R | Home + `/for/*` pages |
| 22 | How to keep a Facebook business page active with no time | H | `/guides/keep-facebook-page-active` |

Prompt 20 is the one to watch first. Until an engine can answer "what is postd.uk?" correctly, it will not recommend us for anything else.

URL note: `robots.txt` disallows `/posts` (the app's content library). Robots rules are prefix matches, so **no public page may start with `/posts`**. Use `/guides/...`, never `/posts-for-plumbers`.

---

## 3. Why we are invisible today

What a crawler receives from `curl https://postd.uk` (checked 25 September 2026):

```html
<title>AI Social Media Automation for UK Small Businesses | postd.uk</title>
<meta name="description" content="... Connect Facebook, Instagram, X (Twitter) and LinkedIn ... From £19/month ...">
<script type="application/ld+json">{ "@type": "SoftwareApplication", ... "creator": { "name": "Dijitul", "url": "https://dijitul.io" } ... }</script>
<body><div id="root"></div><script type="module" src="/assets/...js"></script></body>
```

| # | Finding | Evidence | Impact on AI citation | Severity |
|---|---|---|---|---|
| 1 | Client-rendered SPA, empty body | `<div id="root"></div>` | GPTBot, ClaudeBot, PerplexityBot and Meta's crawler do not execute JavaScript (Vercel/MERJ study: zero JS execution across 500m+ GPTBot fetches). They see no headings, no how-it-works, no FAQ. Only Googlebot (and so Gemini/AI Overviews) renders JS. | Critical |
| 2 | No third-party footprint | Web search for `"postd.uk"` returns only postal pages | Engines recommend what other sites say about you. No listicle, review site, Reddit thread or directory mentions us. | Critical |
| 3 | `robots.txt`, `sitemap.xml`, `llms.txt` all return `200 text/html` (the SPA shell) | `curl -w "%{http_code} %{content_type}"` | Crawlers get HTML where they expect a robots file or XML sitemap. Parsers treat it as empty or broken. No sitemap means no discovery of future pages. | High |
| 4 | Every unknown URL returns `200` with the home page | `/pricing` returns 200; nginx `try_files ... /index.html`; React redirects `*` to `/` | Soft 404s. Crawlers index duplicate copies of the home page at junk URLs, diluting the one real page. | High |
| 5 | Wrong entity data in JSON-LD | `creator.url` is `https://dijitul.io` (not ours); name is title-cased (should be lowercase dijitul) | Engines link entities by URL. We are telling them our maker lives at a domain we do not control. | High |
| 6 | Product description is out of date | Meta, OG, Twitter card, JSON-LD and page copy list Instagram; none mention Google Business Profile | Engines repeat what we say. They will tell people we post to Instagram and will not match us to GBP prompts, our strongest niche. | High |
| 7 | Prices baked into meta tags and schema | "From £19/month", `lowPrice 19` / `highPrice 69`, TikTok add-on £15 in page copy | Pricing is being revised. Models memorise prices and quote them for months after they change. | Medium |
| 8 | Missing assets | `/og-image.png` returns HTML; `/icons/icon-192.png` returns 404 | Broken link previews on Reddit, LinkedIn, Slack and in Perplexity's source cards. | Medium |
| 9 | One URL for the whole marketing site | Routes: `/`, `/terms`, `/privacy` | One page cannot match 20 different prompts. Engines cite the page whose heading matches the question. | High |
| 10 | No FAQ, no Organization entity, no author | No FAQPage, no Organization, no Person | Nothing quotable in Q&A form; no "who is behind this" signal. | Medium |
| 11 | Name ambiguity | "postd" reads like "posted" / "post" | Engines confuse us with postal services. Needs consistent "postd.uk, the social media automation tool" phrasing everywhere. | Low |

Note on items 3 and 5: the new `robots.txt` and `llms.txt` fix item 3 as soon as `web/dist` is rebuilt (Vite copies `public/` into `dist/`, and nginx's `try_files $uri` serves real files before falling back). Verify after deploy:

```bash
curl -sI https://postd.uk/robots.txt | grep -i content-type   # want text/plain
curl -sI https://postd.uk/llms.txt   | grep -i content-type   # want text/plain
curl -sI https://postd.uk/sitemap.xml | grep -i content-type  # want application/xml (needs creating)
```

---

## 4. Fix pack

Ordered by expected citation impact, not ease.

### P0 (this week): make the site readable and correct

**Fix 1. Pre-render the marketing routes to static HTML.**
- Target prompts: all 22.
- Expected effect: moves us from "unreadable" to "readable" for ChatGPT, Claude, Perplexity, Copilot and Meta AI. Nothing else in this plan works without it.
- Implementation options, in order of preference:
  1. `vite-react-ssg` (or `vite-plugin-prerender` / Puppeteer at build) for `/`, `/terms`, `/privacy` and every new `/guides/*`, `/compare/*`, `/for/*`, `/faq` route. The logged-in app stays a pure SPA.
  2. Longer term, move marketing to a static site generator (Astro) on `postd.uk` and the app to `app.postd.uk`. Cleaner, but a bigger job.
- Test: `curl -A "GPTBot" https://postd.uk/ | grep "<h1"` must show the real heading.

**Fix 2. Correct the facts the crawler already sees (`web/index.html`).**
- Replace Instagram with Google Business Profile in title, meta, OG and Twitter card copy. Suggested description (no price): "postd.uk writes and posts social media for UK small businesses. It reads your website and Google reviews, then publishes to Google Business Profile, Facebook, LinkedIn and X automatically. Built in the UK by dijitul."
- Remove all prices from meta and schema until pricing is settled.
- Fix `creator` to dijitul at `https://dijitul.uk`. Fix `<meta name="author">` to "dijitul, Mansfield".
- Update the marketing page copy (`web/src/pages/Marketing/index.jsx`) to drop Instagram and TikTok and lead with GBP.

**Fix 3. Ship `robots.txt`, `llms.txt` and a real `sitemap.xml`.**
- `robots.txt` and `llms.txt` are drafted in `web/public/`. Full text in the appendix.
- `robots.txt` policy: allow AI search bots **and** training bots. Being in training data is how Claude and ChatGPT know us without browsing. There is no commercial downside for a marketing site.
- `sitemap.xml`: generate at build from the pre-rendered route list, with `<lastmod>`. Coordinate with `seo-content-plan.md`.
- Honesty note on `llms.txt`: 2026 log studies show AI crawlers rarely fetch it, Google has said it will not use it, and SE Ranking found no correlation with citations across 300,000 domains. It costs nothing, some agent tools and IDE assistants do read it, and it is a clean fact sheet. Treat it as cheap insurance, not a lever.

**Fix 4. Return real 404s.**
- nginx: keep the SPA fallback for app routes only, and let unknown marketing paths 404. Simplest: pre-render emits real files; add an explicit `location` list for app routes (`/dashboard`, `/posts`, `/platforms`, `/settings`, `/billing`, `/admin`, `/onboarding`, `/auth/`, `/login`, `/register`) that falls back to `index.html`, and `try_files $uri $uri/index.html =404` for everything else, with a pre-rendered `404.html`.

**Fix 5. Restore missing assets.** Add `og-image.png` (1200x630) and the `/icons/*` set referenced by the manifest.

**Fix 6. Keep platform claims matched to what is live.** As of 25 September 2026 publishing works on all four platforms (GBP, Facebook, LinkedIn Company Pages and X), so `llms.txt` can state all four without qualification. Update it the same day any platform is added or dropped.

### P1 (within 14 days): give engines something to quote

**Fix 7. JSON-LD schema set.** One `@graph` per page, IDs shared so entities join up.

Site-wide (every pre-rendered page):

```json
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://dijitul.uk/#organization",
      "name": "dijitul",
      "url": "https://dijitul.uk",
      "logo": "https://dijitul.uk/logo.png",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Mansfield",
        "addressRegion": "Nottinghamshire",
        "addressCountry": "GB"
      },
      "sameAs": [
        "https://www.linkedin.com/company/REPLACE",
        "https://find-and-update.company-information.service.gov.uk/company/REPLACE"
      ]
    },
    {
      "@type": "WebSite",
      "@id": "https://postd.uk/#website",
      "name": "postd.uk",
      "url": "https://postd.uk",
      "inLanguage": "en-GB",
      "publisher": { "@id": "https://dijitul.uk/#organization" }
    },
    {
      "@type": "SoftwareApplication",
      "@id": "https://postd.uk/#software",
      "name": "postd.uk",
      "alternateName": "postd",
      "url": "https://postd.uk",
      "applicationCategory": "BusinessApplication",
      "applicationSubCategory": "Social media automation",
      "operatingSystem": "Web",
      "inLanguage": "en-GB",
      "description": "Automated social media posting for UK small businesses. postd.uk reads a business's website and Google reviews, writes platform-native posts with Claude AI, and publishes them to Google Business Profile, Facebook Pages, LinkedIn Company Pages and X.",
      "featureList": [
        "Writes posts from your own website and Google reviews",
        "Publishes to Google Business Profile, Facebook, LinkedIn Company Pages and X",
        "Separate copy for each platform",
        "Optional approval queue or full autopilot",
        "Scheduled in UK time with overnight quiet hours",
        "UK English spelling"
      ],
      "areaServed": { "@type": "Country", "name": "United Kingdom" },
      "audience": { "@type": "BusinessAudience", "audienceType": "UK small and local businesses" },
      "creator": { "@id": "https://dijitul.uk/#organization" },
      "publisher": { "@id": "https://dijitul.uk/#organization" }
    }
  ]
}
```

Rules:
- **No `offers` block until pricing is final.** Then add a single `Offer` per plan with `priceCurrency: "GBP"` and `priceValidUntil`, and keep it in step with the page.
- **No `aggregateRating` until we have real, on-site reviews.** Self-invented ratings break Google's guidelines and are the fastest way to lose trust.
- Put the same `Organization` block (same `@id`) on dijitul.uk, with a `makesOffer` or `brand` pointing back to postd.uk, so the two sites confirm each other.

FAQ page and home FAQ section: `FAQPage` with the 12 Q&As in Fix 8. Google restricted FAQ rich results to government and health sites in 2023, so do not expect FAQ snippets in Google. The markup still hands every engine clean question and answer pairs, which is the point here.

Guides: `Article` (or `BlogPosting`) with `headline`, `datePublished`, `dateModified`, `author` as a `Person` (Olly, with `sameAs` to LinkedIn), `publisher` as the dijitul `@id`, `about` naming the trade or platform, and `mainEntityOfPage`. Add `BreadcrumbList`. Where a guide is a step list (for example "How to automate GBP posts"), add `HowTo` as well.

Comparison pages: `Article` plus an `ItemList` of the tools compared. Do not mark competitors up as `Product` with ratings.

**Fix 8. The FAQ block.** Twelve answers, each written to stand alone when an engine lifts it out: the first sentence answers the question, names postd.uk, and contains no "we" that needs context. Use on `/faq`, as a shorter set on the home page, and in `FAQPage` schema.

1. **What is postd.uk?**
   postd.uk is a UK-built service that writes and publishes social media posts for small businesses automatically. It reads your website and Google reviews, writes posts in UK English, and publishes them to Google Business Profile, Facebook, LinkedIn Company Pages and X on a regular schedule.

2. **How is postd.uk different from Buffer or Hootsuite?**
   Buffer and Hootsuite schedule posts you have already written; postd.uk writes the posts for you and then schedules them. For a small business owner, the difference is whether social media takes an hour a week or a few seconds to approve.

3. **Can postd.uk post to Google Business Profile automatically?**
   Yes. postd.uk treats Google Business Profile as a main channel, writing short update posts from your services, offers and reviews and publishing them on a schedule, so your Google listing stays active without you logging in.

4. **Where does postd.uk get the content for my posts?**
   postd.uk reads your own website (services, areas covered, what makes you different) and your real Google reviews. It turns that into varied posts, such as service spotlights, tips, and customer quotes, rather than generic stock content.

5. **Will the posts sound like my business?**
   You choose a tone during setup, such as friendly, professional or down to earth, and postd.uk writes every post in that voice using UK spelling. You can edit any post before it goes out, and your edits show the tone you prefer.

6. **Do I have to approve every post?**
   No. By default each post waits in an approval queue so you can edit, approve or reject it with one tap. If you would rather not check, switch on auto-approval and postd.uk publishes on its own.

7. **Which platforms does postd.uk support?**
   postd.uk publishes to Google Business Profile, Facebook Pages, LinkedIn Company Pages and X (Twitter). It does not post to Instagram, TikTok or personal LinkedIn profiles.

8. **Who is postd.uk for?**
   postd.uk is for UK owner-run businesses that know they should post regularly but never find the time: trades such as plumbers, electricians and builders, plus salons, cafés, cleaners, garages and local professional services.

9. **How long does it take to set up postd.uk?**
   Most businesses are set up in about ten minutes. You sign in with Google, enter your business name, website and Google reviews link, connect your accounts, and the first posts are written for you straight away.

10. **When does postd.uk publish posts?**
    postd.uk picks posting times for each platform based on when local audiences are active, in UK time, and never posts overnight. You can see and change every scheduled time from your post list.

11. **Is postd.uk a UK company?**
    Yes. postd.uk is built and run by dijitul, a digital agency based in Mansfield, Nottinghamshire. It is designed for UK businesses, writes in UK English and bills in pounds sterling.

12. **Can I use ChatGPT instead of postd.uk?**
    You can write posts with ChatGPT, but you still have to think of topics, paste in your details, copy each post into four platforms and remember to do it every week. postd.uk does all of that automatically from your own website and reviews, so posting keeps happening when you are busy.

Checks before publishing: confirm "about ten minutes" against a real onboarding time, and confirm the claim in answer 5 that edits inform tone (if edits are not fed back into generation, drop that clause).

**Fix 9. Entity and consistency work.** Engines trust a brand when several independent sources describe it the same way. Use one canonical description everywhere:

> postd.uk is automated social media posting for UK small businesses. It writes posts from your website and Google reviews and publishes them to Google Business Profile, Facebook, LinkedIn and X. Built by dijitul in Mansfield.

| Listing | Why it matters for AI | Action | Priority |
|---|---|---|---|
| G2 | Cited directly for "Buffer alternatives" and GBP tool questions; its category and alternatives pages feed ChatGPT and Perplexity | Claim a free listing in Social Media Management and Social Media Scheduling. Ask the first 10 happy users for reviews (G2's own request flow, no incentives beyond what G2 allows). | P1 |
| Capterra (plus GetApp and Software Advice, same Gartner listing) | Frequent source in comparison answers, strong on "for small business" | Free vendor listing; same review drive | P1 |
| Trustpilot | UK buyers check it; engines quote it for "is X any good?" | Claim the profile; invite every customer after 30 days | P1 |
| Product Hunt | One-off burst of links and a durable page engines cite for "what is X" | Launch once pre-rendering, FAQ and a demo video are live. Tuesday to Thursday, 00:01 PT. | P2 |
| SaaSHub, AlternativeTo, SaaSworthy, Futurepedia, There's An AI For That | Alternatives pages ("alternatives to Buffer") are exactly the prompt shape we want | List postd.uk as an alternative to Buffer, Hootsuite, SocialBee, LocalHQ | P1 |
| Crunchbase | Entity source for company facts | Free profile for postd.uk, parent dijitul | P2 |
| LinkedIn Company Page for postd.uk | LinkedIn is a top-5 cited domain in 2026; also our own channel | Create it, use the canonical description, post through postd.uk itself | P1 |
| Companies House | Anchor for "is this a real UK company" | Make sure the trading name and address match the site footer and schema | P1 |
| Google Business Profile for dijitul | Gemini and AI Overviews lean on Google entities | Keep dijitul's GBP current and mention postd.uk as a product | P2 |
| Wikidata | Strong entity signal, but items need notability and are deleted without independent sources | Only after we have press coverage to cite. Not now. | P3 |
| UK lists and press | UK-specific citations are thin, so each one counts | Pitch: Startups.co.uk and Business Leader tool round-ups, Nottinghamshire and D2N2 Growth Hub supplier lists, FSB member offers, local press (Mansfield Chad, Nottingham Post) on a Mansfield-built AI tool | P2 |
| Listicle inclusion | These are the pages engines cite today | Email independent authors (bloggingwizard.com, technologyadvice.com, almcorp.com, aether-agency.co.uk, zapier.com) with a short factual pitch and free account. Skip vendor listicles; SocialBee will not list us. | P2 |
| YouTube | Perplexity's top cited domain as of mid-2026 | 3 short videos: "What is postd.uk" (90s), "Automate Google Business Profile posts", "What a plumber should post, written by AI in 10 seconds" | P2 |

**Reddit, done honestly.** Reddit is the most-cited domain in AI answers, which also makes it the place where astroturfing is spotted and punished fastest.

- Use Olly's own account, with a visible note in the profile and in any relevant comment: "I build postd.uk, so bear that in mind."
- Target: r/smallbusinessuk, r/UKBusiness, r/smallbusiness, r/sweatystartup, r/Plumbing, r/electricians, r/HairSalon, r/restaurantowners, r/localseo, r/GoogleMyBusiness.
- Ratio of roughly nine helpful answers with no link to one mention. Answer the question fully in the comment (what to post, how often, GBP tips); mention postd.uk only when someone asks for a tool, and name alternatives alongside it.
- Never use extra accounts, bought upvotes, or "a friend recommended this" posts. Read each subreddit's self-promotion rules first.
- Worth doing: an "I built an AI that writes posts for tradespeople, ask me anything / roast it" post in r/SideProject or r/SaaS, which is permitted there and produces a lasting thread.

### P2 (within 30 days): build the pages the prompts need

**Fix 10. Content assets mapped to prompts.** Each is one page, one question, pre-rendered. Coordinate titles and URLs with `seo-content-plan.md` so the two plans do not create duplicates.

| Asset | Prompts | Format that wins citations |
|---|---|---|
| `/google-business-profile-posting` | 1, 13 | Answer-first, 5-step how-to, comparison table of GBP tools including us |
| `/guides/best-social-media-automation-tools-uk` | 3, 19, 21 | Honest listicle, postd.uk included and marked as ours, comparison table, "best for" labels |
| `/compare/buffer-alternative`, `/compare/postd-vs-buffer`, `/compare/postd-vs-hootsuite`, `/compare/postd-vs-socialbee`, `/compare/postd-vs-localhq` | 4, 5 | Feature-by-feature table, "choose Buffer if..., choose postd.uk if..." |
| `/guides/what-to-post/{trade}` for plumbers, electricians, builders, roofers, hair salons, barbers, cafés, cleaners, garages, dentists, estate agents, accountants | 7 to 10, 16 | 20 example posts per trade, per platform, with a UK seasonal calendar (boiler checks in October, frozen pipes in January). Source from `docs/industry-content-themes.md`. |
| `/guides/google-business-profile-posting-frequency` | 11, 12 | Direct answer, evidence table with dates and sources, clear "we do not know" where evidence is thin |
| `/guides/chatgpt-vs-automated-posting` | 14 | Side-by-side table of time per week |
| `/guides/linkedin-company-page-automation` | 15 | Include the honest point that personal-profile automation is not possible via LinkedIn's API for this kind of app |
| `/guides/social-media-manager-cost-uk` | 17 | Table of UK freelancer, agency and software costs with sources |
| `/guides/google-reviews-to-social-posts` | 18 | How-to plus examples |
| `/faq` | 2, 6, 20 | The 12 Q&As, `FAQPage` schema |
| `/for/trades`, `/for/salons`, `/for/hospitality` | 16, 21 | Use-case landing pages, each with 3 sample posts |

Once live, add each URL to `llms.txt` under a `## Guides` or `## Comparisons` heading and to the sitemap.

---

## 5. How articles should be built to be cited

AI engines lift **passages**, not pages. Each H2 section should survive being quoted on its own, out of context, by an engine that has read nothing else on the page.

### 5.1 Rules

1. **Answer first.** The first 40 to 60 words under the H1 answer the headline question directly, in plain text (not in an image, tab, accordion that only opens with JavaScript, or carousel). Label it "Quick answer" or put it in a bold lead paragraph.
2. **One question per H2, phrased as the user would ask it.** "How often should a plumber post on Facebook?" beats "Posting cadence".
3. **Lead each section with the answer sentence, then the detail.** The first sentence under every H2 should make sense alone.
4. **Name the entity in the sentence.** Write "postd.uk writes posts from your reviews", not "it does this for you". Pronouns break when a passage is extracted.
5. **Tables for anything comparable.** Tool comparisons, costs, posting frequencies, platform character limits. Engines copy tables almost verbatim.
6. **Numbers with a source and a date.** "Facebook's own guidance (checked September 2026) is..." A statistic without a source is less likely to be used; a dated one tells the engine it is current. Never invent figures. Where evidence is thin, say so; balanced pages get cited by Claude in particular.
7. **UK specifics.** Pounds, UK seasons, UK bank holidays, HMRC and Companies House references, UK spelling, UK towns in examples. This is our moat against US listicles and the reason an engine would pick us for a UK prompt.
8. **Visible freshness.** "Last updated" date near the top, matching `dateModified` in schema. Review every guide at least quarterly; engines, Perplexity especially, favour recent pages.
9. **Named author with credentials.** Olly as author, one-line bio ("runs dijitul, a Mansfield digital agency that has built websites and local marketing for UK trades since ..."), linked to a `/about` page and LinkedIn.
10. **Honest about competitors.** Comparison pages that admit where Buffer or SocialBee are the better choice are more likely to be cited than pages that claim we win everything, and they build trust with the reader.
11. **Short paragraphs, plain words.** Two to four sentences. No em-dashes. UK English.
12. **Server-rendered.** If `curl` cannot see it, ChatGPT and Claude cannot see it.

### 5.2 Template

```markdown
# What should a plumber post on Facebook? (20 ideas for UK plumbers)

Last updated: 25 September 2026 · By Olly, dijitul

**Quick answer:** UK plumbers get the most from Facebook by posting three or four times a week:
before-and-after jobs, seasonal warnings (boiler servicing in autumn, frozen pipes in winter),
short tips homeowners can use, and real customer reviews. Keep each post under 80 words with a photo.

## What are the best types of Facebook post for a plumber?
[Answer sentence first. Then a table: post type | example | why it works | how often]

## How often should a plumber post on Facebook?
[Answer sentence. Source and date for any figure.]

## 20 ready-to-use Facebook posts for UK plumbers
[Numbered list of actual example posts, UK English, UK seasons]

## What should a plumber post on Google Business Profile instead?
[Answer sentence. Table: Facebook vs GBP differences.]

## Can I automate a plumber's social media posts?
[Answer sentence naming options honestly: do it yourself, ChatGPT, a scheduler such as Buffer,
or a done-for-you tool such as postd.uk. Table of time per week and what each needs from you.]

## FAQ
[3 to 5 short Q&As, marked up as FAQPage]
```

---

## 6. Platform-specific notes

| Engine | How it finds us | What to prioritise |
|---|---|---|
| ChatGPT (search and browsing) | OAI-SearchBot index plus Bing results; ChatGPT-User fetches live; no JS rendering | Pre-rendering, Bing Webmaster Tools submission, G2/Capterra, Reddit |
| Claude | Claude-SearchBot index and Claude-User live fetches; training via ClaudeBot; no JS rendering | Pre-rendering, balanced comparison pages with clear sourcing |
| Gemini and Google AI Overviews | Googlebot index (renders JS), Google entities | Schema, Google Search Console, dijitul's GBP, YouTube |
| Perplexity | Own index plus live fetches; strong recency bias; YouTube-heavy in 2026 | Fresh dated guides, YouTube videos, Reddit threads |
| Copilot | Bing index | Submit sitemap to Bing Webmaster Tools and use IndexNow on publish |

Bing matters more than its search share suggests: ChatGPT search and Copilot both draw on it. Submit the sitemap there on day one.

---

## 7. Measurement

### 7.1 Baseline (do this before shipping any P0 fix)

- Run all 22 prompts in section 2 in ChatGPT (search on), Claude (web search on), Gemini, Perplexity and Google (note any AI Overview). Logged out or in a clean profile, UK location.
- Run each prompt **three times** per engine, because answers vary run to run. 22 prompts x 5 engines x 3 runs = 330 checks; about three hours by hand, or script it with the APIs where available.
- Log per run: tools named (in order), whether postd.uk appears, its position, the sentence used about us, and cited URLs.
- Expected baseline: 0% citation rate everywhere. Record it anyway; it is the "before" we measure against.

Tracker columns: `date | engine | prompt # | run | postd.uk mentioned (Y/N) | position | wording | competitors named | cited URLs`.

### 7.2 Rechecks

- 14 days after P0 ships: rerun prompts 2, 6 and 20 (the "what is postd.uk" family). Success is an engine describing us correctly, with GBP and without Instagram.
- 30 days after P1: full rerun.
- Then monthly, and after any major model release.

### 7.3 Targets (realistic for a new brand, not promises)

| Milestone | 30 days | 90 days |
|---|---|---|
| "What is postd.uk?" answered correctly | 3 of 5 engines | 5 of 5 |
| Cited on at least one of the 22 prompts | 1 engine (likely Perplexity) | 3 of 5 engines |
| Overall citation rate across the 22 prompts | 5% | 15 to 20% |
| Trade "what to post" prompts citing our guides | 0 to 2 | 5 or more |
| Third-party pages mentioning postd.uk | 8 (directories) | 20 or more, including 2 independent listicles |
| G2 plus Capterra reviews | 5 | 20 |

---

## 8. Checklist

P0, this week
- [ ] Run and record the manual baseline (7.1)
- [ ] Pre-render marketing routes; confirm with `curl -A GPTBot`
- [ ] Fix `index.html`: drop Instagram and prices, add GBP, fix creator to dijitul at dijitul.uk
- [ ] Update marketing page copy to match
- [ ] Deploy `robots.txt` and `llms.txt`
- [ ] Create and deploy `sitemap.xml`; submit to Google Search Console and Bing Webmaster Tools
- [ ] Real 404s for unknown paths
- [ ] Add `og-image.png` and `/icons/*`

P1, within 14 days
- [ ] Site-wide JSON-LD `@graph` (Organization, WebSite, SoftwareApplication)
- [ ] `/faq` page with the 12 Q&As and `FAQPage` schema
- [ ] G2, Capterra, Trustpilot, SaaSHub, AlternativeTo listings with the canonical description
- [ ] postd.uk LinkedIn Company Page
- [ ] Start honest Reddit participation
- [ ] 14-day recheck

P2, within 30 days
- [ ] GBP posting page, UK tools listicle, first 3 comparison pages
- [ ] First 4 trade "what to post" guides (plumbers, electricians, hair salons, cafés)
- [ ] 3 YouTube videos
- [ ] Listicle author outreach, UK press pitch
- [ ] Product Hunt launch
- [ ] Add every new URL to `llms.txt` and the sitemap
- [ ] 30-day full recheck

---

## Appendix A: `web/public/llms.txt`

See the file itself; it is the source of truth. Summary of choices:

- No pricing, and an explicit instruction not to quote one.
- A "what postd.uk does not do" section, so engines stop guessing (Instagram, TikTok, personal LinkedIn, social inbox).
- A "differs from Buffer, Hootsuite or Later" paragraph written to be quoted in comparison answers.
- Lists only live URLs. Add guides and comparisons as they ship.
- Disambiguates from Royal Mail and the Post Office.

## Appendix B: `web/public/robots.txt`

See the file itself. Summary of choices:

- Everything public is open to all crawlers, including AI training bots (GPTBot, ClaudeBot, Google-Extended, Applebot-Extended, CCBot). The trade-off: we give our marketing copy to model training in return for models knowing who we are. For a new brand that wants to be recommended, that is the right trade.
- AI bots are named explicitly so intent is clear. Each named group repeats the app disallows, because a crawler follows only the most specific group that matches it.
- App routes are disallowed for everyone. Because rules are prefix matches, no public URL may start with `/posts`, `/settings`, `/billing`, `/admin`, `/platforms`, `/dashboard`, `/onboarding` or `/inbox`.
- Points to `https://postd.uk/sitemap.xml`, which does not exist yet (see Fix 3).

## Appendix C: sources consulted (25 September 2026)

- Citation share studies: Search Engine Land, "AI search engines cite Reddit, YouTube, and LinkedIn most"; 5WPR "State of AI citations 2026"; Profound "AI platform citation patterns"; ZipTie.dev on Reddit's share.
- JS rendering: Vercel and MERJ crawler study; SearchOptimo and Passionfruit 2026 write-ups.
- AI crawler user agents: Anagram, No Hacks and Presenc AI 2026 references.
- llms.txt evidence: SE Ranking 300,000-domain study; 1ClickReport and Digital Applied 2026 analyses; Google's public statements (Gary Illyes, John Mueller).
- Category SERPs: socialchamp.com, blog.gainapp.com, localhq.io, circleboom.com, localo.com, leadoracle.ai, almcorp.com, socialbee.com, sproutsocial.com, socialpilot.co, g2.com, bloggingwizard.com, zapier.com, aether-agency.co.uk, apaya.com, clickgrow.ai, webfx.com, scorpion.co, fieldpulse.com, getjobber.com, wavegen.ai, technologyadvice.com.
