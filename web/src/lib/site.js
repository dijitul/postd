// Site-wide facts used by page heads, structured data and the footer.
// Keep these in step with web/public/llms.txt.

export const SITE_URL = 'https://postd.uk'
export const SITE_NAME = 'postd.uk'
export const CONTACT_EMAIL = 'hello@postd.uk'
export const DEFAULT_OG_IMAGE = `${SITE_URL}/og-image.png`

export const ORG_ID = 'https://dijitul.uk/#organization'
export const WEBSITE_ID = `${SITE_URL}/#website`
export const SOFTWARE_ID = `${SITE_URL}/#software`

export const CANONICAL_DESCRIPTION =
  'postd.uk writes and posts social media for UK small businesses. It reads your website and Google reviews, then publishes to Google Business Profile, Facebook, LinkedIn and X automatically. Built in the UK by dijitul.'

export function absoluteUrl(path = '/') {
  if (/^https?:\/\//.test(path)) return path
  return `${SITE_URL}${path.startsWith('/') ? path : `/${path}`}`
}

// ── Structured data ─────────────────────────────────────────────────────────
// Entities share @ids so engines can join them up across pages. No prices in
// here on purpose: pricing is under review and lives with the pricing section.

export const organizationSchema = {
  '@type': 'Organization',
  '@id': ORG_ID,
  name: 'dijitul',
  url: 'https://dijitul.uk',
  email: CONTACT_EMAIL,
  address: {
    '@type': 'PostalAddress',
    addressLocality: 'Mansfield',
    addressRegion: 'Nottinghamshire',
    addressCountry: 'GB',
  },
}

export const websiteSchema = {
  '@type': 'WebSite',
  '@id': WEBSITE_ID,
  name: SITE_NAME,
  url: SITE_URL,
  inLanguage: 'en-GB',
  publisher: { '@id': ORG_ID },
}

export const softwareSchema = {
  '@type': 'SoftwareApplication',
  '@id': SOFTWARE_ID,
  name: SITE_NAME,
  alternateName: 'postd',
  url: SITE_URL,
  applicationCategory: 'BusinessApplication',
  applicationSubCategory: 'Social media automation',
  operatingSystem: 'Web',
  inLanguage: 'en-GB',
  image: DEFAULT_OG_IMAGE,
  description:
    "Automated social media posting for UK small businesses. postd.uk reads a business's website and Google reviews, writes platform-native posts with Claude AI, and publishes them to Google Business Profile, Facebook Pages, LinkedIn Company Pages and X.",
  featureList: [
    'Writes posts from your own website and Google reviews',
    'Publishes to Google Business Profile, Facebook, LinkedIn Company Pages and X',
    'Separate copy for each platform',
    'Optional approval queue or full autopilot',
    'Scheduled in UK time with overnight quiet hours',
    'UK English spelling',
  ],
  areaServed: { '@type': 'Country', name: 'United Kingdom' },
  audience: { '@type': 'BusinessAudience', audienceType: 'UK small and local businesses' },
  creator: { '@id': ORG_ID },
  publisher: { '@id': ORG_ID },
}

export function faqSchema(items, pageUrl) {
  return {
    '@type': 'FAQPage',
    '@id': `${pageUrl}#faq`,
    mainEntity: items.map((item) => ({
      '@type': 'Question',
      name: item.q,
      acceptedAnswer: { '@type': 'Answer', text: item.answerText ?? item.a },
    })),
  }
}

export function breadcrumbSchema(crumbs, pageUrl) {
  return {
    '@type': 'BreadcrumbList',
    '@id': `${pageUrl}#breadcrumb`,
    itemListElement: crumbs.map((crumb, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      name: crumb.name,
      item: absoluteUrl(crumb.path),
    })),
  }
}

export function graph(...nodes) {
  return { '@context': 'https://schema.org', '@graph': nodes.filter(Boolean) }
}
