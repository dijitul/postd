// Plan copy shared by the landing page and the Billing page.
//
// The numbers here mirror api/config/plans.php, which is the source of truth
// the API enforces and serves. The landing page is prerendered, so it cannot
// wait on the API; keep the two in step when a price or limit changes. The
// Billing page takes prices, availability and limits from the API and only
// borrows the wording below.

// Shown after every price. Empty while VAT status is being confirmed. Set it
// to something like ' + VAT' and every price on the site follows.
export const VAT_SUFFIX = ''

export const ANNUAL_MONTHS_FREE = 2

export const TRIAL = {
  days: 14,
  plan: 'growth',
  xPosts: 10,
  aiImages: 10,
}

export const EXTRA_LOCATION_PRICE = 19

// Everything every plan includes. Kept separate so the cards only list what
// differs, and the section can say "on every plan" once.
export const EVERY_PLAN = [
  'Autopilot, or approve each post first',
  'Posts written from your reviews and website',
  'Edit any post before it goes out',
]

export const PLANS = [
  {
    id: 'local',
    name: 'Local',
    monthly: 19,
    annual: 190,
    popular: false,
    tagline: 'For one business on the two channels that matter most to it.',
    features: [
      '1 location',
      'Any 2 of Google Business Profile, Facebook and LinkedIn',
      'Up to 3 posts a week on each',
      '5 AI images a month',
      '30 days of analytics',
    ],
  },
  {
    id: 'growth',
    name: 'Growth',
    monthly: 39,
    annual: 390,
    popular: true,
    tagline: 'Every platform, including X, at up to a post a day.',
    features: [
      '1 location',
      'Google Business Profile, Facebook, LinkedIn and X',
      'Up to 7 posts a week on each',
      '30 AI images a month',
      '12 months of analytics',
    ],
  },
  {
    id: 'agency',
    name: 'Agency',
    monthly: 79,
    annual: 790,
    popular: false,
    tagline: 'For multi-site owners and small agencies with a handful of locations.',
    features: [
      `3 locations, then £${EXTRA_LOCATION_PRICE}/month each`,
      'Every platform, for every location',
      'Up to 14 posts a week on each (Google up to 7)',
      '100 AI images a month, shared across locations',
      '12 months of analytics',
    ],
  },
]

export const PLAN_NAMES = {
  local: 'Local',
  starter: 'Local',
  growth: 'Growth',
  agency: 'Agency',
  pro: 'Pro',
}

export const PLATFORM_NAMES = {
  google_business_profile: 'Google Business Profile',
  facebook: 'Facebook',
  linkedin: 'LinkedIn',
  twitter: 'X',
}

/** £19, or £32.50 when there are pence. */
export function formatPounds(pounds) {
  return Number.isInteger(pounds) ? `£${pounds}` : `£${pounds.toFixed(2)}`
}

/** "£19/month" or "£190/year", with the VAT suffix. */
export function priceLabel(pounds, interval = 'monthly') {
  return `${formatPounds(pounds)}/${interval === 'annual' ? 'year' : 'month'}${VAT_SUFFIX}`
}

/** What an annual price works out at per month, for "£32.50/month billed yearly". */
export function annualPerMonth(annualPounds) {
  return Math.round((annualPounds / 12) * 100) / 100
}

/** schema.org Offer objects for the landing page's JSON-LD. */
export function pricingJsonLdOffers() {
  return PLANS.flatMap((plan) => [
    {
      '@type': 'Offer',
      name: `${plan.name} (monthly)`,
      price: plan.monthly.toFixed(2),
      priceCurrency: 'GBP',
      priceSpecification: {
        '@type': 'UnitPriceSpecification',
        price: plan.monthly.toFixed(2),
        priceCurrency: 'GBP',
        unitText: 'MONTH',
      },
    },
    {
      '@type': 'Offer',
      name: `${plan.name} (annual)`,
      price: plan.annual.toFixed(2),
      priceCurrency: 'GBP',
      priceSpecification: {
        '@type': 'UnitPriceSpecification',
        price: plan.annual.toFixed(2),
        priceCurrency: 'GBP',
        unitText: 'YEAR',
      },
    },
  ])
}
