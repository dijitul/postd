/**
 * Vite plugin: the guides section.
 *
 * Reads Markdown articles from web/content/guides/*.md at build time and
 * exposes them to the app as virtual modules:
 *
 *   virtual:guides              list of published guides (metadata only) plus
 *                               a loader per guide, so each article's HTML
 *                               lands in its own small chunk
 *   virtual:guide/<slug>        one article: metadata, rendered HTML, table of
 *                               contents and FAQ
 *
 * Only articles dated on or before today (Europe/London) are built. The daily
 * publish workflow rebuilds the site each morning, which is what makes a
 * future-dated article go live without a code push. In `vite dev` future
 * articles are included and flagged as scheduled so they can be previewed.
 *
 * Overrides for testing: GUIDES_BUILD_DATE=YYYY-MM-DD pretends the build runs
 * on that date; GUIDES_INCLUDE_FUTURE=1 builds everything.
 */
import fs from 'node:fs'
import path from 'node:path'
import matter from 'gray-matter'
import { Marked } from 'marked'

const INDEX_ID = 'virtual:guides'
const ARTICLE_PREFIX = 'virtual:guide/'
const RESOLVED_PREFIX = '\0'

export const PILLARS = {
  gbp: 'Google Business Profile posts',
  automation: 'Automated social media posting',
  local: 'Social media for local businesses',
  tools: 'Tools and costs',
}
const TYPES = ['pillar', 'cluster', 'comparison', 'trade', 'template']
const WORDS_PER_MINUTE = 200
// A table of contents is shown once an article is long enough to need one.
const TOC_MIN_H2 = 4
const TOC_MIN_WORDS = 1200

/** Today's date in the UK as YYYY-MM-DD, whatever timezone the build runs in. */
export function londonToday() {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/London',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date())
}

// YAML turns an unquoted 2026-10-01 into a Date. Normalise both forms.
function toIsoDate(value) {
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return value.toISOString().slice(0, 10)
  }
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value.trim())) {
    return value.trim()
  }
  return null
}

function slugify(text) {
  return String(text)
    .toLowerCase()
    .replace(/<[^>]+>/g, '')
    .replace(/&[a-z0-9#]+;/g, '')
    .replace(/['’]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function stripHtml(html) {
  return String(html)
    .replace(/<[^>]+>/g, '')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/\s+/g, ' ')
    .trim()
}

function escapeAttr(value) {
  return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;')
}

/**
 * Markdown to HTML with GFM tables, heading anchors, a table of contents and
 * a few layout tweaks (scrollable tables, lazy images, external links).
 */
function renderMarkdown(markdown) {
  const toc = []
  const usedIds = new Set()
  const marked = new Marked({ gfm: true })

  marked.use({
    renderer: {
      heading({ tokens, depth }) {
        const inner = this.parser.parseInline(tokens)
        // The article H1 comes from frontmatter, so a stray "# " heading in
        // the body is demoted to keep one H1 per page.
        const level = depth === 1 ? 2 : depth
        let id = slugify(stripHtml(inner)) || 'section'
        let n = 2
        while (usedIds.has(id)) id = `${slugify(stripHtml(inner))}-${n++}`
        usedIds.add(id)
        if (level === 2 || level === 3) toc.push({ id, text: stripHtml(inner), level })
        if (level > 3) return `<h${level}>${inner}</h${level}>\n`
        return `<h${level} id="${id}"><a class="heading-anchor" href="#${id}" aria-hidden="true" tabindex="-1">#</a>${inner}</h${level}>\n`
      },
      link({ href, title, tokens }) {
        const text = this.parser.parseInline(tokens)
        const isExternal = /^https?:\/\//i.test(href) && !/^https?:\/\/(www\.)?postd\.uk(\/|$)/i.test(href)
        const titleAttr = title ? ` title="${escapeAttr(title)}"` : ''
        const rel = isExternal ? ' rel="noopener"' : ''
        return `<a href="${escapeAttr(href)}"${titleAttr}${rel}>${text}</a>`
      },
      image({ href, title, text }) {
        const titleAttr = title ? ` title="${escapeAttr(title)}"` : ''
        return `<img src="${escapeAttr(href)}" alt="${escapeAttr(text)}"${titleAttr} loading="lazy" decoding="async">`
      },
    },
  })

  // Wrap tables so wide ones scroll sideways on a phone instead of breaking
  // the layout.
  let html = marked.parse(markdown)
  html = html
    .replace(/<table>/g, '<div class="table-wrap" tabindex="0"><table>')
    .replace(/<\/table>/g, '</table></div>')

  const words = stripHtml(html).split(' ').filter(Boolean).length
  return { html, toc, words }
}

function renderInline(markdown) {
  return new Marked({ gfm: true }).parseInline(String(markdown ?? ''))
}

/**
 * Reads, validates and renders every guide. Invalid files are skipped with a
 * warning rather than failing the build, so one bad article never blocks the
 * daily publish of the others.
 */
export function loadGuides({ contentDir, includeFuture = false, today = londonToday(), logger = console }) {
  if (!fs.existsSync(contentDir)) return []

  const files = fs.readdirSync(contentDir).filter((f) => f.endsWith('.md') && !f.startsWith('_'))
  const guides = []
  const seen = new Set()

  for (const file of files) {
    const full = path.join(contentDir, file)
    let parsed
    try {
      parsed = matter(fs.readFileSync(full, 'utf8'))
    } catch (err) {
      logger.warn(`[guides] ${file}: could not parse frontmatter (${err.message}), skipped`)
      continue
    }
    const fm = parsed.data ?? {}
    const problems = []

    const slug = typeof fm.slug === 'string' ? fm.slug.trim() : ''
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) problems.push('slug must be lowercase words joined by hyphens')
    if (slug.startsWith('posts')) problems.push('slug must not start with "posts" (disallowed in robots.txt)')
    if (!fm.title) problems.push('title is required')
    if (!fm.description) problems.push('description is required')
    const date = toIsoDate(fm.date)
    if (!date) problems.push('date must be YYYY-MM-DD')
    const updated = fm.updated ? toIsoDate(fm.updated) : null
    if (fm.updated && !updated) problems.push('updated must be YYYY-MM-DD')
    if (!PILLARS[fm.pillar]) problems.push(`pillar must be one of ${Object.keys(PILLARS).join(', ')}`)
    if (fm.type && !TYPES.includes(fm.type)) problems.push(`type must be one of ${TYPES.join(', ')}`)
    if (seen.has(slug)) problems.push(`duplicate slug "${slug}"`)

    if (problems.length) {
      logger.warn(`[guides] ${file}: ${problems.join('; ')}. Skipped.`)
      continue
    }
    if (String(fm.description).length > 155) {
      logger.warn(`[guides] ${file}: description is ${String(fm.description).length} characters (max 155)`)
    }

    const scheduled = date > today
    if (scheduled && !includeFuture) continue
    seen.add(slug)

    const { html, toc, words } = renderMarkdown(parsed.content)
    const faq = Array.isArray(fm.faq)
      ? fm.faq
          .filter((item) => item && item.q && item.a)
          .map((item) => {
            const answerHtml = renderInline(item.a)
            return { q: String(item.q), a: String(item.a), answerHtml, answerText: stripHtml(answerHtml) }
          })
      : []

    const h2Count = toc.filter((t) => t.level === 2).length
    guides.push({
      slug,
      title: String(fm.title),
      seoTitle: fm.seoTitle ? String(fm.seoTitle) : null,
      description: String(fm.description),
      date,
      updated: updated && updated > date ? updated : null,
      author: fm.author ? String(fm.author) : 'Olly, dijitul',
      pillar: fm.pillar,
      pillarLabel: PILLARS[fm.pillar],
      type: fm.type || 'cluster',
      primaryKeyword: fm.primaryKeyword ? String(fm.primaryKeyword) : null,
      readingMinutes: Math.max(1, Math.round(words / WORDS_PER_MINUTE)),
      scheduled,
      html,
      toc: h2Count >= TOC_MIN_H2 || words >= TOC_MIN_WORDS ? toc : [],
      faq,
    })
  }

  // Newest first; pillar pages lead within the same day.
  guides.sort((a, b) => (a.date === b.date ? (a.type === 'pillar' ? -1 : 1) : a.date < b.date ? 1 : -1))
  return guides
}

function metaOnly(guide) {
  const { html, toc, faq, ...meta } = guide
  return meta
}

export default function guidesPlugin({ contentDir }) {
  let isDev = false
  let cache = null

  const includeFuture = () => isDev || process.env.GUIDES_INCLUDE_FUTURE === '1'
  const today = () => process.env.GUIDES_BUILD_DATE || londonToday()
  const all = () => {
    if (!cache) cache = loadGuides({ contentDir, includeFuture: includeFuture(), today: today() })
    return cache
  }

  return {
    name: 'postd-guides',

    configResolved(config) {
      isDev = config.command === 'serve'
    },

    buildStart() {
      cache = null
      if (fs.existsSync(contentDir)) this.addWatchFile(contentDir)
    },

    resolveId(id) {
      if (id === INDEX_ID || id.startsWith(ARTICLE_PREFIX)) return RESOLVED_PREFIX + id
      return null
    },

    load(id) {
      if (id === RESOLVED_PREFIX + INDEX_ID) {
        const guides = all()
        const loaders = guides
          .map((g) => `  ${JSON.stringify(g.slug)}: () => import(${JSON.stringify(ARTICLE_PREFIX + g.slug)})`)
          .join(',\n')
        return [
          `export const guides = ${JSON.stringify(guides.map(metaOnly))}`,
          `export const pillars = ${JSON.stringify(PILLARS)}`,
          `export const loaders = {\n${loaders}\n}`,
        ].join('\n')
      }
      if (id.startsWith(RESOLVED_PREFIX + ARTICLE_PREFIX)) {
        const slug = id.slice((RESOLVED_PREFIX + ARTICLE_PREFIX).length)
        const guide = all().find((g) => g.slug === slug)
        if (!guide) return 'export default null'
        return `export default ${JSON.stringify(guide)}`
      }
      return null
    },

    configureServer(server) {
      // Live-reload while writing an article in `npm run dev`.
      server.watcher.add(contentDir)
      const onChange = (file) => {
        if (!path.resolve(file).startsWith(path.resolve(contentDir))) return
        cache = null
        for (const mod of server.moduleGraph.idToModuleMap.values()) {
          if (mod.id && mod.id.startsWith(RESOLVED_PREFIX + 'virtual:guide')) {
            server.moduleGraph.invalidateModule(mod)
          }
        }
        server.ws.send({ type: 'full-reload' })
      }
      server.watcher.on('add', onChange)
      server.watcher.on('change', onChange)
      server.watcher.on('unlink', onChange)
    },
  }
}
