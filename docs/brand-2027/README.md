# Relay: the postd brand (2027)

One brand for **postd** (postd.uk) and **Postd Office** (office.postd.uk), their marketing sites and their apps. Chosen 11 November 2026. This file is the source of truth; the old "honey/amber/navy" system in `docs/design-system.md` is retired.

**Feel:** bright, friendly, optimistic and quick to read on a phone. Made for busy small-business owners, not marketers. Clean white space, one confident blue for actions, a sunshine-yellow highlight used sparingly, soft rounded shapes.

## Logo

Files in this folder (copy into each app's `public/brand/`):

| File | Use |
|---|---|
| `icon.svg` | postd mark: cobalt circle, white "p", sun dot. Favicon, app icon, avatar. |
| `logo.svg` / `logo-reversed.svg` | Mark + "postd" wordmark, for light / dark backgrounds |
| `office-icon.svg` | Postd Office mark: same "p" on a **navy** circle |
| `office-logo.svg` / `office-logo-reversed.svg` | Mark + "postd office" (office in medium weight, cobalt-400) |

In HTML, render the wordmark as live text next to the SVG mark (Sora 800, letter-spacing -0.035em), not as the SVG `<text>`, so it uses the loaded font. Always lowercase "postd". The product name is written "Postd Office" in sentences; the logo lockup is lowercase "postd office". Minimum mark size 20px. Clear space round the mark: half its width.

## Colour

| Token | Hex | Use |
|---|---|---|
| `cobalt-600` (primary) | `#2340F5` | Buttons, links, active states, the mark. White text on it passes AA (6.9:1). |
| `cobalt-700` | `#1A30C4` | Primary hover / pressed |
| `cobalt-400` | `#4A63FF` | Links and accents on dark backgrounds |
| `cobalt-100` | `#E0E6FF` | Selected rows, soft chips |
| `cobalt-50` | `#EEF1FF` | Tinted panels |
| `sun-400` (highlight) | `#FFD23F` | Highlight marker behind one word in a headline, badges ("Most popular", "New"), the dot in the logo. **Never** text colour on white, never a large fill. Navy text on it. |
| `sun-100` | `#FFF4CC` | Soft highlight panels |
| `navy-900` (ink) | `#0E1A3F` | Headings, body text, dark sections, footer |
| `navy-700` | `#26335C` | Secondary dark surfaces |
| `slate-500` | `#56607A` | Secondary text (5.9:1 on white) |
| `slate-400` | `#8A93AB` | Placeholders and disabled only (not body text) |
| `line` | `#E3E7F1` | Borders, dividers |
| `cloud` | `#F3F5FB` | Page background in apps, alternate marketing bands |
| `white` | `#FFFFFF` | Cards, marketing background |
| `ai-600` | `#6B4EFF` | **AI-generated content only** (drafts, captions, "written by postd" chips). Soft bg `ai-50` `#EFEBFF`. Keeps human actions (cobalt) and machine output visually separate. |
| `success` | `#12A150` / soft `#E6F6EC` | Posted, connected, healthy |
| `warn` | `#B76E00` / soft `#FFF3DC` | Needs attention, expiring |
| `danger` | `#D92D20` / soft `#FDECEA` | Failed, abusive, errors |

Dark sections (footer, closing call-to-action): `navy-900` background, white text, `cobalt-400` links, sun accents.

## Type

- **Headings, buttons, numbers that matter:** **Sora** (Google Fonts), weights 600, 700, 800. Headlines 800 with letter-spacing -0.035em, line-height 1.0 to 1.1.
- **Body, UI text, forms:** **Plus Jakarta Sans**, weights 400, 500, 600, 700.
- Fallback stack: `system-ui, -apple-system, "Segoe UI", sans-serif`.
- Marketing scale (desktop / phone): display 64/40, h2 44/32, h3 24/20, body-lg 19/17, body 16, small 14, label 12 (600, letter-spacing .04em, uppercase only for tiny labels).
- App scale: page title 24, section 18, body 15, small 13.
- Highlight one word per hero headline with the sun marker: `background: #FFD23F; padding: 0 .12em; border-radius: .18em; box-decoration-break: clone`.

## Shape and depth

- Buttons: **pill** (`border-radius: 999px`), min height 44px, padding 12px 22px, Sora 600 15px. Primary = cobalt-600 bg, white text. Secondary = white bg, navy text, 1.5px `line` inset ring. Ghost = transparent, cobalt text.
- Inputs: 12px radius, 1.5px `line` border, 48px tall, focus ring 3px `cobalt-100` plus border `cobalt-600`.
- Cards: 18px radius, white, shadow `0 10px 30px rgba(14,26,63,.08)` on marketing; in apps prefer a 1px `line` border and no shadow, shadow only on popovers and the hovered card.
- Chips and badges: pill, 12px Plus Jakarta 600.
- Focus visible: 3px `cobalt-100` ring + 2px cobalt outline offset. Respect `prefers-reduced-motion`.

## Layout

- Marketing: max width 1200px, 16px side gutter minimum, generous vertical rhythm (96px desktop / 64px phone between sections). Alternate white and `cloud` bands; one `navy-900` band for the closing call-to-action and the footer.
- Mobile-first everywhere. Footers: link columns become tap-to-expand accordions on phones (`aria-expanded`), always open on desktop; contact email stays visible as a big tap target; brand block on top.
- Apps: `cloud` page background, white sidebar with 1px `line` divider, active nav item = `cobalt-50` background with `cobalt-600` text and a 3px cobalt bar on the left edge.

## Voice

UK English. No em-dashes anywhere. Short, warm, plain sentences.

## How the two products relate

- Same tokens, fonts, shapes and components. postd leads with the cobalt mark; Postd Office uses the navy mark and the "postd office" lockup.
- Each site's header links to the other ("Postd Office" on postd.uk, "postd" on office.postd.uk) and both footers say "Built by dijitul" linking to https://dijitul.uk.
