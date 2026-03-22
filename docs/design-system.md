# postd.uk Design System

**Version:** 1.0
**Status:** Active — foundation release
**Maintained by:** Brand Guardian

---

## Overview

postd.uk is a UK-focused social media SaaS for small businesses. The design system reflects its personality: warm, modern, confident, and slightly playful. It is never corporate or cold. Think Notion meets Hootsuite, but warmer and more human.

Every design decision comes back to one question: does this feel like a trusted tool built by real people, for real UK business owners?

---

## Brand Principles

### 1. Warm confidence

We are not shy about being good at what we do. CTAs are prominent. Actions are clear. But confidence never tips into arrogance — tone is always warm and encouraging.

### 2. Generous whitespace

Space is not wasted space. Breathing room around elements reduces cognitive load and makes the interface feel premium without being expensive.

### 3. Amber as signal, not decoration

The amber accent colour is used for exactly one thing: drawing the eye to the most important action on the screen. When amber appears, it means "do this now". Overuse destroys its signal value.

### 4. Mobile-first, always

Every screen starts at 375px and is built outward. The app runs as a PWA. Nothing is an afterthought on mobile.

### 5. UK-native

British English throughout. Pricing in GBP. Date formats DD/MM/YYYY. Tone that sounds like a person, not a press release.

---

## Colour Palette

### Primary Colours

| Token | Hex | Tailwind class | Usage |
|-------|-----|---------------|-------|
| Brand Amber | `#E07B30` | `amber-500` / `brand-amber` | Primary CTA, logo accent, active states, focus rings |
| Amber Dark | `#B85E1A` | `amber-700` / `brand-amber-dark` | Hover states on amber buttons |
| Brand Navy | `#1E2D4A` | `navy-800` / `brand-navy` | Primary text, headers, navigation, dark surfaces |
| Navy Light | `#2E4470` | `navy-700` / `brand-navy-light` | Gradient secondary, card borders on dark |
| Brand Honey | `#F5C842` | `honey-400` / `brand-honey` | Premium indicators, "Most Popular" badges, upgrade CTAs |
| Brand Cream | `#F9F5EE` | `cream-200` / `brand-cream` | Page backgrounds, card surfaces |

### Amber Scale

```
amber-50:  #FEF6EE  — tinted backgrounds, hover states on ghost elements
amber-100: #FDE9D5  — amber section backgrounds
amber-200: #FBD0A8  — amber borders, dividers
amber-300: #F8B06C  — illustrated accents
amber-400: #F4892E  — amber-light (logo gradient end)
amber-500: #E07B30  — brand-amber (primary)
amber-600: #C96620  — strong amber, border on filled amber
amber-700: #B85E1A  — brand-amber-dark (hover)
amber-800: #93481A  — very dark amber, use sparingly
amber-900: #763C19  — near-black amber
```

### Navy Scale

```
navy-50:  #EEF1F7  — very light tint
navy-100: #D8DFED
navy-200: #B5C2DC
navy-300: #8AA0C5
navy-400: #617EAD
navy-500: #4A6195
navy-600: #3B4F7D
navy-700: #2E4470  — brand-navy-light
navy-800: #1E2D4A  — brand-navy (primary)
navy-900: #172239
navy-950: #0D1520  — deep navy for hero gradient bottoms
```

### Honey Scale

```
honey-50:  #FEFCE8
honey-100: #FEF8C3
honey-200: #FEEE89
honey-300: #FDE047
honey-400: #F5C842  — brand-honey (primary)
honey-500: #EAB308
honey-600: #CA8A04
```

### Cream Scale

```
cream-100: #FDFBF8  — near white with warmth
cream-200: #F9F5EE  — brand-cream (page backgrounds)
cream-300: #F3EBD9  — borders, subtle dividers on cream
cream-400: #EBE0C4  — stronger cream borders
cream-500: #DDD0AC  — muted cream elements
```

### Neutral (Slate)

Used for secondary text, borders, and input states. Never use pure black (`#000000`) for body text.

```
slate-100: #F1F5F9  — skeleton loaders
slate-200: #E2E8F0  — borders, dividers
slate-300: #CBD5E1  — placeholder borders
slate-400: #94A3B8  — placeholder text, secondary icons
slate-500: #64748B  — secondary body text
slate-600: #475569  — primary body text (use this, not navy-800, for paragraphs)
slate-700: #334155  — slightly emphasised body text
slate-800: #1E293B  — alternative to navy for body
slate-900: #0F172A  — very dark, near-black
```

### Status Colours

| State | Hex | Usage |
|-------|-----|-------|
| Success | `#16A34A` | Connected, posted, active |
| Warning | `#D97706` | Expiring tokens, partial failures |
| Error | `#DC2626` | Failed posts, billing errors |
| Info | `#2563EB` | Informational notices |
| Pending | `#7C3AED` | Queued, processing, scheduled |

### Platform Colours

Use these only for platform-specific badges and icons. Never use them for general UI.

| Platform | Colour |
|----------|--------|
| Facebook | `#1877F2` |
| Instagram | `#E1306C` |
| X | `#000000` |
| LinkedIn | `#0A66C2` |
| TikTok | `#010101` |
| Google | `#4285F4` |

### Accessibility — WCAG 2.1 AA

Required minimum contrast ratios: 4.5:1 for body text, 3:1 for large text and UI components.

| Foreground | Background | Ratio | Pass |
|------------|------------|-------|------|
| `#1E2D4A` (navy) | `#FFFFFF` | 11.2:1 | AA / AAA |
| `#1E2D4A` (navy) | `#F9F5EE` (cream) | 10.1:1 | AA / AAA |
| `#FFFFFF` | `#E07B30` (amber) | 3.1:1 | AA (large text / UI) |
| `#FFFFFF` | `#1E2D4A` (navy) | 11.2:1 | AA / AAA |
| `#1E2D4A` | `#F5C842` (honey) | 6.2:1 | AA / AAA |
| `#64748B` (slate-600) | `#FFFFFF` | 5.9:1 | AA |

Note: White text on amber (`#E07B30`) passes for large text (18pt+) and UI components but fails for small body text. Use navy text on amber for small labels.

---

## Typography

### Typefaces

| Role | Font | Weights | Usage |
|------|------|---------|-------|
| Display | Syne | 700, 800 | Headings, button labels, wordmark, nav |
| Body | Inter | 400, 500, 600 | Paragraphs, labels, table content, captions |
| Mono | JetBrains Mono | 400, 500 | API keys, code snippets, platform IDs |

### Loading

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
```

### Type Scale

| Token | Size | Line Height | Usage |
|-------|------|-------------|-------|
| `text-2xs` | 10px / 0.625rem | 14px | Micro labels, character counts |
| `text-xs` | 12px / 0.75rem | 16px | Badge labels, captions, helper text |
| `text-sm` | 14px / 0.875rem | 20px | Body text, form labels, table cells |
| `text-base` | 16px / 1rem | 24px | Standard body copy |
| `text-lg` | 18px / 1.125rem | 28px | Lead paragraphs, card titles |
| `text-xl` | 20px / 1.25rem | 28px | Sub-section headings |
| `text-2xl` | 24px / 1.5rem | 32px | Section headings |
| `text-3xl` | 30px / 1.875rem | 36px | Page headings |
| `text-4xl` | 36px / 2.25rem | 40px | Hero sub-headings |
| `text-5xl` | 48px / 3rem | ~52px | Hero headings (Syne Bold) |
| `text-6xl` | 60px / 3.75rem | ~63px | Marketing display (Syne Bold) |

### Typography rules

- All headings: `font-display font-bold`, `letter-spacing: -0.02em`
- Body text: `font-body font-normal`, `line-height: 1.7` for paragraphs
- Button labels: `font-display font-semibold`
- Form labels: `font-display font-semibold text-sm`
- Helper / error text: `font-body text-xs`
- Never set body text in navy-800 — use `slate-600` for paragraphs and `navy-800` for headings only

---

## Spacing Scale

postd.uk uses an **8pt grid**. All spacing values are multiples of 8px.

| Token | px | rem | Tailwind | Usage |
|-------|-----|-----|----------|-------|
| xs | 4px | 0.25rem | `p-1` | Tight internal gaps |
| sm | 8px | 0.5rem | `p-2` | Icon-text gaps, badge padding |
| md | 16px | 1rem | `p-4` | Standard component padding |
| lg | 24px | 1.5rem | `p-6` | Card padding, section gaps |
| xl | 32px | 2rem | `p-8` | Large card padding |
| 2xl | 48px | 3rem | `p-12` | Section gaps between components |
| 3xl | 64px | 4rem | `p-16` | Major section dividers |
| 4xl | 96px | 6rem | `p-24` | Page section gaps (hero, pricing) |

### Gap conventions

- Between icon and text label: `gap-2` (8px)
- Between related form fields: `gap-4` (16px)
- Between unrelated form sections: `gap-6` (24px)
- Between cards in a grid: `gap-4` mobile, `gap-6` desktop
- Section padding: `py-16` mobile, `py-24` desktop

---

## Border Radius

| Token | Value | Usage |
|-------|-------|-------|
| `rounded-sm` | 4px | Badges (xs), tight elements |
| `rounded-md` | 8px | Dropdown items, table rows |
| `rounded-lg` | 12px | Input fields (sm), tags |
| `rounded-xl` | 16px | Cards, buttons (default), input fields |
| `rounded-2xl` | 20px | Elevated cards, pricing cards |
| `rounded-3xl` | 24px | Large hero cards |
| `rounded-full` | 9999px | Pills, avatars, status dots |

---

## Shadows

### Elevation system

| Token | Value | Usage |
|-------|-------|-------|
| `shadow-xs` | `0 1px 2px rgb(0 0 0 / 0.05)` | Subtle hover on flat elements |
| `shadow-sm` | `0 1px 3px ... 0 1px 2px ...` | Buttons, badges |
| `shadow-card` | `0 2px 8px rgb(30 45 74 / 0.06) ...` | Default card |
| `shadow-card-hover` | `0 8px 24px rgb(30 45 74 / 0.1) ...` | Card hover state |
| `shadow-lg` | `0 10px 15px ...` | Dropdowns, tooltips |
| `shadow-xl` | `0 20px 25px ...` | Modals, elevated cards |

### Brand glow shadows

Used sparingly on high-emphasis CTAs and premium elements.

| Token | Usage |
|-------|-------|
| `shadow-amber` | Focus ring on amber CTAs |
| `shadow-amber-glow` | Primary CTA hover state (use max once per screen) |
| `shadow-honey-glow` | Pro plan features, upgrade prompts |
| `shadow-focus` | All focusable elements (accessibility) |

---

## Component Principles

### What makes a postd.uk component feel right?

**1. It has one clear purpose**
Every component does exactly one thing well. A button is a button. It does not contain logic for eight different visual states via a maze of props. Keep the API surface small.

**2. States are always handled**
Every interactive component must have: default, hover, active/pressed, focus (keyboard), disabled, and loading states. Missing any of these is a bug.

**3. Amber is earned**
Only one element per screen should be amber. If two things are amber, neither stands out. The primary button is amber. Everything else defers to it.

**4. Feedback is immediate**
Transitions are 150-200ms. Users should feel the interface respond to them in real time. Animations longer than 300ms feel sluggish on mobile.

**5. Mobile tap targets are always at least 44px**
iOS HIG and WCAG both require 44x44px minimum touch targets. The `sm` button size is 32px height — when used in tight spaces, ensure adequate surrounding whitespace to make the effective tap area sufficient.

**6. Labels are always present**
Icon-only buttons must have `aria-label`. Platform icons must have alt text. Never rely on visual context alone.

**7. Errors speak plainly**
Error messages are in plain English. "Something went wrong" is not acceptable. "We couldn't connect your Instagram account. Please try again or check your account permissions." is.

---

## Button Usage

```jsx
// Primary — the most important action on the screen
<Button variant="primary">Connect platform</Button>

// Secondary — important but not the primary focus
<Button variant="secondary">View settings</Button>

// Ghost — tertiary action
<Button variant="ghost">Skip for now</Button>

// Danger — destructive, irreversible
<Button variant="danger">Delete account</Button>

// Loading state
<Button variant="primary" loading>Saving changes</Button>

// Sizes
<Button size="sm">Small</Button>
<Button size="md">Medium (default)</Button>
<Button size="lg">Large</Button>
```

### Button Do's and Don'ts

**Do**
- Use `primary` for the single most important CTA per screen
- Use `secondary` for the second most important action (e.g. "Cancel" next to "Save")
- Use `ghost` for everything else
- Always include a loading state when the button triggers an async action
- Use `fullWidth` on mobile forms

**Do not**
- Place two `primary` buttons side by side
- Use `danger` for anything reversible
- Omit the loading state on form submit buttons
- Use `honey` variant outside of upgrade/premium contexts

---

## Card Usage

```jsx
// Default card
<Card>Content</Card>

// With header
<Card title="Platform connections" subtitle="Manage your connected accounts" headerAction={<Button size="sm">Add</Button>}>
  ...
</Card>

// Interactive / clickable
<Card variant="interactive" onClick={handleClick}>
  ...
</Card>

// Elevated (modal-weight)
<Card variant="elevated">
  ...
</Card>

// Premium honey card with ribbon
<Card variant="honey" ribbon ribbonLabel="Most popular">
  ...
</Card>
```

---

## Input Usage

```jsx
// Basic
<Input label="Business name" placeholder="e.g. Mansfield Plumbing Ltd" />

// With validation
<Input
  label="Website URL"
  type="url"
  error="Please enter a valid URL including https://"
  helper="We'll use this to gather your services and USPs"
/>

// With icons
<Input
  label="Search"
  iconLeft={<SearchIcon />}
  iconRight={<ClearIcon />}
/>

// Textarea
<Textarea
  label="Business description"
  rows={4}
  maxLength={500}
  showCount
/>

// Select
<Select
  label="Industry"
  placeholder="Select your industry..."
  options={[
    { value: 'restaurant', label: 'Restaurant & Food' },
    { value: 'retail', label: 'Retail' },
    { value: 'trades', label: 'Trades & Construction' },
  ]}
/>
```

---

## Badge Usage

```jsx
// Plan tiers
<PlanBadge plan="starter" />
<PlanBadge plan="growth" />
<PlanBadge plan="pro" />

// Post status
<StatusBadge status="scheduled" />
<StatusBadge status="posted" />
<StatusBadge status="failed" />

// Platform
<PlatformBadge platform="instagram" />

// Trial countdown
<TrialBadge daysLeft={7} />

// General
<Badge variant="honey">Most popular</Badge>
<Badge variant="success" dot>Connected</Badge>
```

---

## Platform Icon Usage

```jsx
// Bare icon
<PlatformIcon platform="facebook" size="md" />

// With container
<PlatformIcon platform="instagram" size="lg" container="circle" />
<PlatformIcon platform="linkedin" size="lg" container="square" />
<PlatformIcon platform="tiktok" size="md" container="soft" />

// Filled (white icon on platform colour)
<PlatformIcon platform="x" size="lg" container="filled" />

// With label
<PlatformIcon platform="google" size="lg" showLabel />

// Row of platforms
<PlatformIconRow
  platforms={['facebook', 'instagram', 'x', 'linkedin']}
  size="md"
  container="circle"
/>

// Grid with labels
<PlatformIconGrid
  platforms={['facebook', 'instagram', 'x', 'linkedin', 'tiktok', 'google']}
  size="lg"
  container="soft"
/>
```

---

## Gradients

### Named gradient utilities

```css
.bg-gradient-amber      /* #E07B30 → #F5C842, 135deg — hero CTAs, active highlights */
.bg-gradient-navy       /* #1E2D4A → #2E4470, 135deg — dark sections, nav */
.bg-gradient-navy-deep  /* #1E2D4A → #0D1520, 180deg — full-page dark hero */
.bg-gradient-cream      /* #FFFFFF → #F9F5EE, 180deg — page transitions */
.bg-gradient-hero       /* #1E2D4A → #3B4F7D, 135deg — marketing hero bg */
.bg-gradient-amber-soft /* #FEF6EE → #FDE9D5, 135deg — amber section bg */
```

### Gradient text

```html
<h1 class="text-gradient-amber">All your posts. One hive.</h1>
```

Only use gradient text on headings size `text-3xl` and above. At smaller sizes it becomes unreadable.

---

## Animation & Motion

### Principles

- Entrances: elements fade in and slide up slightly (`fade-in-up`). Never pop in abruptly.
- Loading: use skeleton loaders, not spinners, for content-heavy areas.
- CTAs: the amber glow pulse is reserved for the single highest-priority CTA on a marketing page only.
- Respect `prefers-reduced-motion`. All animations should be wrapped or suppressed.

### Available animations

| Class | Duration | Usage |
|-------|----------|-------|
| `animate-fade-in` | 200ms | Cards, tooltips appearing |
| `animate-fade-in-up` | 300ms | Page sections loading in |
| `animate-fade-in-scale` | 200ms | Modals, dropdowns |
| `animate-slide-in-right` | 250ms | Toast notifications |
| `animate-skeleton` | 1.8s | Skeleton loading cells |
| `animate-amber-pulse` | 2s | Primary CTA attention pulse |
| `animate-honey-shimmer` | 2.5s | Premium/upgrade surfaces |
| `animate-bounce-subtle` | 2s | Subtle attention on new content |

---

## Layout Tokens

```
--container-max:   80rem    (1280px) — main app container
--container-narrow: 42rem  (672px)  — article / onboarding flow
--sidebar-width:   15rem   (240px)  — main nav sidebar
--header-height:   3.5rem  (56px)   — mobile top bar
```

### Breakpoints

| Name | Width | Target |
|------|-------|--------|
| `xs` | 375px | Small phones |
| `sm` | 640px | Large phones, small tablets |
| `md` | 768px | Tablets |
| `lg` | 1024px | Small laptops |
| `xl` | 1280px | Desktop |
| `2xl` | 1536px | Wide desktop |

---

## Iconography

postd.uk uses **Heroicons** (outline style, 24px) for all UI icons. Use solid variants only for active/selected states.

Platform icons are custom SVG components (see `PlatformIcon.jsx`) — never use third-party icon packs for platform logos as they may be outdated or off-brand.

### Icon sizing conventions

| Context | Size |
|---------|------|
| Button icon | 16-18px |
| Nav icon | 20px |
| Card icon | 24px |
| Platform icon (list) | 20px |
| Platform icon (grid) | 24-32px |
| Empty state illustration | 48-64px |

---

## Do's and Don'ts

### Do

- Use `text-slate-600` for paragraphs, `text-navy-800` for headings
- Always test at 375px width before wider breakpoints
- Keep amber to one element per screen — the primary CTA
- Add `aria-label` to all icon-only buttons and icon elements
- Use `font-display` (Syne) for headings and button labels
- Use `font-body` (Inter) for all other text
- Apply `text-balance` to all multi-line headings
- Show loading states on any button that triggers async work
- Use `shadow-card` for default cards, `shadow-card-hover` on hover
- Write error messages in plain, helpful English

### Do not

- Use `#000000` for text — use `navy-800` or `slate-900`
- Apply amber to decorative elements — it only signals action
- Make hover states change layout (only colour, shadow, scale)
- Use gradients inside cards — save them for hero sections
- Skip focus styles — keyboard users must always see where they are
- Use more than two typefaces — Syne and Inter only
- Create cards without defined padding — always set a padding variant
- Display raw API keys or tokens unmasked in the UI

---

## File locations

```
web/
  public/brand/
    logo.svg            — Full colour horizontal logo
    logo-reversed.svg   — White on navy for dark backgrounds
    icon.svg            — Square hexagon mark only (app icon, favicon)
  src/
    styles/
      globals.css       — CSS custom properties, base reset, utility classes
    components/
      ui/
        Button.jsx      — Primary, secondary, ghost, danger, honey variants
        Card.jsx        — Default, elevated, interactive, cream, navy, amber, honey
        Input.jsx       — Text input, Textarea, Select
        Badge.jsx       — Plan tier, status, platform, general
        PlatformIcon.jsx — All 6 platform SVG icons + container variants
web/
  tailwind.config.js    — Full brand token system
docs/
  design-system.md      — This document
```

---

*Design system maintained by Brand Guardian. Changes to brand tokens must be reflected in both `tailwind.config.js` and `globals.css`. Component API changes require updating this document.*
