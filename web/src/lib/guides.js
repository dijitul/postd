import { guides, pillars, loaders } from 'virtual:guides'

export { guides, pillars }

// Loaded article bodies, keyed by slug. Filled before hydration (see
// main.jsx) and before the prerender, so a guide page can render its body
// synchronously and match the static HTML exactly.
const loaded = new Map()
const pending = new Map()

export function findGuide(slug) {
  return guides.find((g) => g.slug === slug) ?? null
}

export function loadGuide(slug) {
  if (loaded.has(slug)) return Promise.resolve(loaded.get(slug))
  if (!loaders[slug]) return Promise.resolve(null)
  if (!pending.has(slug)) {
    pending.set(
      slug,
      loaders[slug]().then((mod) => {
        loaded.set(slug, mod.default)
        pending.delete(slug)
        return mod.default
      })
    )
  }
  return pending.get(slug)
}

/**
 * Suspense-friendly read: returns the article if it is loaded, null if the
 * slug is unknown, and otherwise throws the loading promise so the nearest
 * <Suspense> waits for it.
 */
export function readGuide(slug) {
  if (loaded.has(slug)) return loaded.get(slug)
  if (!loaders[slug]) return null
  throw loadGuide(slug)
}

export function relatedGuides(guide, limit = 3) {
  return guides.filter((g) => g.slug !== guide.slug && g.pillar === guide.pillar).slice(0, limit)
}

const DATE_FORMAT = new Intl.DateTimeFormat('en-GB', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'UTC',
})

export function formatDate(isoDate) {
  return DATE_FORMAT.format(new Date(`${isoDate}T00:00:00Z`))
}
