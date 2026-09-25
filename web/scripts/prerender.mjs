/**
 * Build-time prerender for the public site.
 *
 * Runs after `vite build` (client) and `vite build --ssr` (server entry):
 *   1. renders every public route to static HTML in dist/, using the built
 *      dist/index.html as the template, so crawlers that do not run
 *      JavaScript (GPTBot, ClaudeBot, PerplexityBot) get the full page;
 *   2. writes dist/404.html for nginx to serve with a real 404 status;
 *   3. writes dist/sitemap.xml;
 *   4. adds the published guides to dist/llms.txt.
 *
 * The browser then hydrates the static HTML (see src/main.jsx). App routes
 * are not prerendered; nginx serves them dist/app.html, the empty SPA shell.
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const distDir = path.join(root, 'dist')
const ssrEntry = path.join(root, '.ssr', 'entry-server.js')
const SITE_URL = 'https://postd.uk'

// Public, indexable routes that are not guides. /login is prerendered for
// speed but carries noindex, so it stays out of the sitemap.
const STATIC_ROUTES = [
  { url: '/', sitemap: true, priority: '1.0' },
  { url: '/guides', sitemap: true, priority: '0.9' },
  { url: '/register', sitemap: true, priority: '0.6' },
  { url: '/login', sitemap: false },
  { url: '/terms', sitemap: true, priority: '0.3' },
  { url: '/privacy', sitemap: true, priority: '0.3' },
]
// Any path that matches no route renders the not-found page.
const NOT_FOUND_URL = '/__not-found__'

function fail(message) {
  console.error(`\n[prerender] ${message}\n`)
  process.exit(1)
}

function outputFile(url) {
  if (url === '/') return path.join(distDir, 'index.html')
  return path.join(distDir, ...url.split('/').filter(Boolean), 'index.html')
}

function xmlEscape(value) {
  return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

async function main() {
  const templatePath = path.join(distDir, 'index.html')
  if (!fs.existsSync(templatePath)) fail('dist/index.html not found. Run `vite build` first.')
  if (!fs.existsSync(ssrEntry)) fail('.ssr/entry-server.js not found. Run `vite build --ssr src/entry-server.jsx --outDir .ssr` first.')

  const template = fs.readFileSync(templatePath, 'utf8')
  if (!template.includes('<!--app-head-->') || !template.includes('<!--app-html-->')) {
    fail('dist/index.html is missing the <!--app-head--> or <!--app-html--> marker.')
  }
  if (!fs.existsSync(path.join(distDir, 'app.html'))) fail('dist/app.html (the SPA shell) was not emitted.')

  const { render, guides } = await import(pathToFileURL(ssrEntry).href)

  const pages = [
    ...STATIC_ROUTES,
    ...guides.map((g) => ({
      url: `/guides/${g.slug}`,
      sitemap: true,
      priority: g.type === 'pillar' ? '0.8' : '0.7',
      lastmod: g.updated ?? g.date,
    })),
  ]

  // The route each file was rendered for is stamped on the root element, so
  // the browser only hydrates when the URL matches. A server that falls back
  // to this HTML for some other path (an older nginx config serving index.html
  // for /dashboard, say) then gets a clean client render instead of a
  // mismatched hydration. '*' marks the 404 page, which fits any path.
  const fill = ({ html, head }, route) =>
    template
      .replace('<!--app-head-->', head)
      .replace('<div id="root">', `<div id="root" data-route="${xmlEscape(route)}">`)
      .replace('<!--app-html-->', html)

  for (const page of pages) {
    const result = await render(page.url)
    if (!result.head) fail(`${page.url} rendered without a <Seo /> head.`)
    if (!/<h1[\s>]/.test(result.html)) fail(`${page.url} rendered without an <h1>. Is the page suspending or redirecting?`)
    const file = outputFile(page.url)
    fs.mkdirSync(path.dirname(file), { recursive: true })
    fs.writeFileSync(file, fill(result, page.url))
    console.log(`[prerender] ${page.url.padEnd(48)} -> ${path.relative(root, file)}`)
  }

  const notFound = await render(NOT_FOUND_URL)
  fs.writeFileSync(path.join(distDir, '404.html'), fill(notFound, '*'))
  console.log(`[prerender] ${'(not found)'.padEnd(48)} -> dist/404.html`)

  // ── sitemap.xml ───────────────────────────────────────────────────────────
  const latestGuide = guides.reduce((max, g) => {
    const d = g.updated ?? g.date
    return d > max ? d : max
  }, '')
  const entries = pages
    .filter((p) => p.sitemap)
    .map((p) => {
      const lastmod = p.lastmod ?? (p.url === '/guides' && latestGuide ? latestGuide : null)
      return [
        '  <url>',
        `    <loc>${xmlEscape(SITE_URL + (p.url === '/' ? '/' : p.url))}</loc>`,
        lastmod ? `    <lastmod>${lastmod}</lastmod>` : null,
        p.priority ? `    <priority>${p.priority}</priority>` : null,
        '  </url>',
      ]
        .filter(Boolean)
        .join('\n')
    })
  const sitemap = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${entries.join('\n')}\n</urlset>\n`
  fs.writeFileSync(path.join(distDir, 'sitemap.xml'), sitemap)
  console.log(`[prerender] sitemap.xml with ${entries.length} URLs`)

  // ── llms.txt ──────────────────────────────────────────────────────────────
  // web/public/llms.txt is the hand-written fact sheet. The build adds the
  // guides index to its Pages list and a Guides section with every published
  // guide, so new articles appear there on the day they go live.
  const llmsPath = path.join(distDir, 'llms.txt')
  if (fs.existsSync(llmsPath)) {
    let llms = fs.readFileSync(llmsPath, 'utf8').replace(/\r\n/g, '\n')
    const guidesLink = `- [Guides](${SITE_URL}/guides): practical guides to Google Business Profile posts, automated posting and social media for UK local businesses`
    if (!llms.includes(`${SITE_URL}/guides)`)) {
      llms = llms.replace(/(- \[Home\]\([^\n]*\n)/, `$1${guidesLink}\n`)
    }
    if (guides.length > 0 && !llms.includes('\n## Guides\n')) {
      const list = guides
        .map((g) => `- [${g.title}](${SITE_URL}/guides/${g.slug}): ${g.description}`)
        .join('\n')
      const section = `## Guides\n\n${list}\n\n`
      llms = llms.includes('\n## Company\n')
        ? llms.replace('\n## Company\n', `\n${section}## Company\n`)
        : `${llms.trimEnd()}\n\n${section}`
    }
    fs.writeFileSync(llmsPath, llms)
    console.log(`[prerender] llms.txt lists ${guides.length} guide(s)`)
  }

  console.log(`[prerender] done: ${pages.length} pages, ${guides.length} guide(s) published`)
}

main().catch((err) => fail(err?.stack || String(err)))
