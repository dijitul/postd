# Writing brief for postd.uk guides

Read this, the calendar (`docs/marketing/guides-calendar-2026-q4.md`) and one existing guide (`web/content/guides/google-business-profile-posts.md`) before writing.

## Format

One Markdown file per article at `web/content/guides/<slug>.md`, frontmatter exactly like the existing guides:

```yaml
---
title: "..."              # the H1, natural and specific
seoTitle: "..."           # under 60 characters, Title Case is fine
description: "..."        # 120 to 155 characters, never more than 155
slug: ...                 # from the calendar, unchanged
date: YYYY-MM-DD          # from the calendar, unchanged
author: "Olly, dijitul"
pillar: ...               # from the calendar
type: ...                 # from the calendar
product: ...              # from the calendar: postd, office or both
primaryKeyword: "..."
faq:                      # 4 to 6 real questions, 1 to 3 sentence answers
  - q: "..."
    a: "..."
---
```

Quote every YAML string with double quotes and never put an unescaped double quote inside one. Do not add a `# H1` in the body (the title is the H1).

Body: open with a 2 to 4 sentence direct answer (AI engines and featured snippets quote this), then H2s phrased as the questions people search, tables where comparing anything, numbered steps for how-tos, short paragraphs (people read on phones). 1,300 to 2,200 words; pillars up to 3,000. Use relative internal links (`/guides/<slug>`).

## Voice and rules (non-negotiable)

- UK English (colour, organise, £, "on the tools", VAT). Dates like 5 December 2026.
- **No em-dashes or en-dashes anywhere.** Use commas, full stops, colons or brackets. Ranges as "£15 to £29".
- The agency is **dijitul**, always lowercase, site dijitul.uk. The product is **postd** or **postd.uk** (lowercase). The sister product is **Postd Office** (capital P and O).
- Friendly, plain, a bit direct. A Mansfield agency talking to a plumber, not a US growth blog. No hype words (game-changer, unlock, supercharge, leverage, elevate, in today's fast-paced world).
- Honest. We make postd and Postd Office: say so wherever we compare ourselves with others. Say what each competitor does well and when it is the better choice. Say what our products do not do.
- **Never invent facts.** Competitor prices, features and platform rules must be checked on the web today (November 2026) and the source linked. Convert dollar prices to pounds approximately and say so ("about £X at current rates"), or quote the £ price if the vendor shows one. If you cannot verify something, leave it out or say it is unverified. No made-up statistics, studies or customer quotes. Platform policies (Google, Meta, TikTok, LinkedIn) link to the official help page.
- Time-saving and cost claims are worked examples with stated assumptions ("if writing and posting one post takes 20 minutes and you post 4 times a week on 3 platforms..."), never a fake survey. Use a realistic UK owner hourly value (say £30 to £50 an hour) and show the sum.
- Use concrete UK small-business examples: plumbers in Mansfield, a salon in Nottingham, a café in Derby, a cleaner in Leicester. Mix the towns.
- End each article with a short "where to start" section, then a single natural CTA line linking to the right product (see links below). The page also shows a CTA box automatically, so do not overdo it in the body: at most one mid-article mention and the closing one.

## Product facts (only claim these)

**postd.uk** (writes and publishes posts)
- Reads the business's website (services, about, FAQ, blog pages) and real Google reviews, then writes posts in UK English, separately for each platform.
- Publishes to Google Business Profile, Facebook Pages, LinkedIn Company Pages and X. Not Instagram or TikTok. Does not reply to reviews or messages.
- Approval queue or autopilot, edit any post. Sensible local posting times and quiet hours. Uses the business's own photos first (from website, Google profile, uploads), AI images only when no photo fits. Hashtags checked against the business's own words.
- "What's new" posts when a new page appears on the site; rotates angles (review quotes, service spotlights, tips, FAQs, local, behind the scenes).
- Plans (+ VAT): Local £15/month (1 location, any 2 of GBP, Facebook, LinkedIn, up to 3 posts a week each, 5 AI images a month). Growth £29/month (all four platforms incl. X, up to 7 a week each, 30 AI images). Agency £59/month (3 locations then £15 each, up to 14 a week, Google up to 7). Annual = 2 months free.
- 14-day free trial on Growth, no card needed. Monthly, cancel any time.
- Built by dijitul in Mansfield.
- Link: `/register?utm_source=postd.uk&utm_medium=guide&utm_campaign=<slug>`; pricing `/#pricing`.

**Postd Office** (handles what comes back: messages and comments), at office.postd.uk
- One inbox for Facebook Page messages and comments, Instagram DMs and comments, and TikTok comments. TikTok DMs are not supported because TikTok offers no API for them; say so.
- AI drafts every reply in the business's voice, only from answers the business has approved; if it does not know, it leaves the message for a person. Every reply is a draft until approved. Autopilot is off by default and switched on one approved answer at a time.
- Automatic translation both ways (message shown in English, reply sent in their language with an English copy).
- Flags abuse, spam, scams and threats; hide comments in a tap; urgent items jump the queue and email the team.
- Unlimited team seats, owner and agent roles, activity log. Nobody needs the Facebook password.
- Sorting Office £49/month (the inbox). Head Office £99/month (adds the Publisher: write once, tailored captions, hashtags and mentions per network, staggered posting, schedule up to 30 days ahead, for Facebook, Instagram and TikTok). + VAT, annual = 2 months free. One brand per plan. Fair-use limit on AI-handled messages.
- No free trial; 30-day money-back guarantee (self-serve refund of the first payment from the Billing page).
- Separate sign-up from postd.uk. UK-run by dijitul, hosted in the UK and EU, AI processing by Anthropic, messages kept 12 months, GDPR: the business is controller, we are processor.
- Link: `https://office.postd.uk/?utm_source=postd.uk&utm_medium=guide&utm_campaign=<slug>`; pricing `https://office.postd.uk/#pricing`.

**How they fit together:** postd puts posts out (Google, Facebook, LinkedIn, X), Postd Office deals with what comes back (DMs and comments on Facebook, Instagram, TikTok) and, on Head Office, publishes to Facebook, Instagram and TikTok. Articles with `product: both` should explain which one suits whom.
