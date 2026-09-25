import { createContext, useContext, useEffect } from 'react'
import { SITE_NAME, DEFAULT_OG_IMAGE, absoluteUrl } from './site.js'

/**
 * Per-route <head> management without an extra dependency.
 *
 * Pages render <Seo ... />. During the build-time prerender a collector is
 * passed through HeadProvider and the prerender script turns it into real
 * tags with renderHead(). In the browser the same props update the existing
 * tags after client-side navigation, so the head stays correct either way.
 */
const HeadContext = createContext(null)

export function HeadProvider({ collector, children }) {
  return <HeadContext.Provider value={collector}>{children}</HeadContext.Provider>
}

function normalise({
  title,
  description,
  path = '/',
  robots = 'index, follow',
  type = 'website',
  image = DEFAULT_OG_IMAGE,
  imageAlt = 'postd.uk, automated social media for UK small businesses',
  jsonLd = null,
  publishedTime = null,
  modifiedTime = null,
}) {
  return {
    title,
    description,
    // path={null} leaves out the canonical, for the 404 page.
    canonical: path === null ? null : absoluteUrl(path),
    robots,
    type,
    image,
    imageAlt,
    jsonLd,
    publishedTime,
    modifiedTime,
  }
}

export function Seo(props) {
  const collector = useContext(HeadContext)
  const head = normalise(props)

  // Server: record during render (effects never run there).
  if (collector) collector.head = head

  // Browser: bring the existing tags in line after a client-side navigation.
  const serialised = JSON.stringify(head)
  useEffect(() => {
    applyHead(JSON.parse(serialised))
  }, [serialised])

  return null
}

// ── Server side ─────────────────────────────────────────────────────────────

function esc(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
}

export function serialiseJsonLd(data) {
  // Escaping "<" stops any "</script>" inside a string from closing the tag.
  return JSON.stringify(data).replace(/</g, '\\u003c')
}

export function renderHead(head) {
  if (!head) return ''
  const lines = [
    `<title>${esc(head.title)}</title>`,
    `<meta name="description" content="${esc(head.description)}" />`,
    `<meta name="robots" content="${esc(head.robots)}" />`,
    head.canonical && `<link rel="canonical" href="${esc(head.canonical)}" />`,
    `<meta property="og:type" content="${esc(head.type)}" />`,
    head.canonical && `<meta property="og:url" content="${esc(head.canonical)}" />`,
    `<meta property="og:title" content="${esc(head.title)}" />`,
    `<meta property="og:description" content="${esc(head.description)}" />`,
    `<meta property="og:image" content="${esc(head.image)}" />`,
    `<meta property="og:image:width" content="1200" />`,
    `<meta property="og:image:height" content="630" />`,
    `<meta property="og:image:alt" content="${esc(head.imageAlt)}" />`,
    `<meta property="og:site_name" content="${SITE_NAME}" />`,
    `<meta property="og:locale" content="en_GB" />`,
    `<meta name="twitter:card" content="summary_large_image" />`,
    `<meta name="twitter:title" content="${esc(head.title)}" />`,
    `<meta name="twitter:description" content="${esc(head.description)}" />`,
    `<meta name="twitter:image" content="${esc(head.image)}" />`,
  ].filter(Boolean)
  if (head.publishedTime) lines.push(`<meta property="article:published_time" content="${esc(head.publishedTime)}" />`)
  if (head.modifiedTime) lines.push(`<meta property="article:modified_time" content="${esc(head.modifiedTime)}" />`)
  if (head.jsonLd) {
    lines.push(`<script type="application/ld+json" id="seo-jsonld">${serialiseJsonLd(head.jsonLd)}</script>`)
  }
  return lines.join('\n    ')
}

// ── Browser side ────────────────────────────────────────────────────────────

function upsert(selector, create, attrs) {
  let el = document.head.querySelector(selector)
  if (!el) {
    el = document.createElement(create)
    document.head.appendChild(el)
  }
  for (const [key, value] of Object.entries(attrs)) el.setAttribute(key, value)
  return el
}

function setMeta(key, attr, content) {
  if (content == null) {
    document.head.querySelector(`meta[${attr}="${key}"]`)?.remove()
    return
  }
  upsert(`meta[${attr}="${key}"]`, 'meta', { [attr]: key, content })
}

function applyHead(head) {
  if (typeof document === 'undefined') return
  document.title = head.title
  setMeta('description', 'name', head.description)
  setMeta('robots', 'name', head.robots)
  if (head.canonical) {
    upsert('link[rel="canonical"]', 'link', { rel: 'canonical', href: head.canonical })
  } else {
    document.head.querySelector('link[rel="canonical"]')?.remove()
  }
  setMeta('og:type', 'property', head.type)
  setMeta('og:url', 'property', head.canonical)
  setMeta('og:title', 'property', head.title)
  setMeta('og:description', 'property', head.description)
  setMeta('og:image', 'property', head.image)
  setMeta('og:image:alt', 'property', head.imageAlt)
  setMeta('twitter:title', 'name', head.title)
  setMeta('twitter:description', 'name', head.description)
  setMeta('twitter:image', 'name', head.image)
  setMeta('article:published_time', 'property', head.publishedTime)
  setMeta('article:modified_time', 'property', head.modifiedTime)

  const existing = document.getElementById('seo-jsonld')
  if (head.jsonLd) {
    const script = existing ?? document.createElement('script')
    script.type = 'application/ld+json'
    script.id = 'seo-jsonld'
    script.textContent = JSON.stringify(head.jsonLd)
    if (!existing) document.head.appendChild(script)
  } else {
    existing?.remove()
  }
}
