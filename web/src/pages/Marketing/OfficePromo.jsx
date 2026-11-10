import { ArrowRight, Check, Inbox } from 'lucide-react'
import { CONTACT_EMAIL, ORG_ID } from '../../lib/site.js'

// Postd Office: the sister product for everything that comes back after a
// post goes out. Static content only, so the prerendered HTML and the first
// client render are identical.

export const OFFICE_URL = 'https://office.postd.uk'
// Matches the id on the pricing section of office.postd.uk (AutoDM/src/app/page.tsx).
export const OFFICE_PRICING_URL = `${OFFICE_URL}/#pricing`

const POINTS = [
  'Facebook, Instagram and TikTok DMs and comments in one inbox',
  'AI replies drafted from answers you have approved',
  'Automatic translation, with abuse and spam flagged for you',
  'Unlimited team seats and a 30-day money-back guarantee',
]

const CHANNEL_DOT = {
  Facebook: '#1877F2',
  Instagram: '#E1306C',
  TikTok: '#010101',
}

function ChannelChip({ channel, kind }) {
  return (
    <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600">
      <span
        className="w-2 h-2 rounded-full ring-2 ring-white"
        style={{ backgroundColor: CHANNEL_DOT[channel] }}
        aria-hidden="true"
      />
      {channel} {kind}
    </span>
  )
}

/** A small, decorative inbox: one message with its drafted reply, two waiting. */
function InboxPreview() {
  return (
    <div className="relative w-full max-w-md mx-auto lg:mx-0 lg:ml-auto" aria-hidden="true">
      <div className="bg-white rounded-3xl shadow-xl border border-cream-300 overflow-hidden">
        <div className="flex items-center gap-2 px-5 py-3.5 border-b border-cream-300 bg-cream-100">
          <Inbox className="w-4 h-4 text-navy-700" />
          <span className="font-display font-bold text-sm text-navy-800">Inbox</span>
          <span className="ml-auto text-2xs font-bold uppercase tracking-wide bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full">
            3 waiting
          </span>
        </div>

        <div className="p-5">
          <ChannelChip channel="Instagram" kind="DM" />
          <p className="mt-2 text-sm text-navy-800 font-medium">
            Hiya, have you got any slots this Saturday morning?
          </p>

          <div className="mt-4 rounded-2xl bg-cream-200 border border-cream-300 p-4">
            <p className="text-2xs font-bold uppercase tracking-widest text-navy-600">AI reply, ready to send</p>
            <p className="mt-1.5 text-sm text-slate-700 leading-relaxed">
              Morning! We have 9:30 and 11:00 free on Saturday. Want me to pop you in for one of those?
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
              <span className="inline-flex items-center bg-navy-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg">
                Approve and send
              </span>
              <span className="inline-flex items-center border border-navy-200 text-navy-700 text-xs font-semibold px-3 py-1.5 rounded-lg">
                Edit
              </span>
            </div>
          </div>
        </div>

        <ul className="border-t border-cream-300 divide-y divide-cream-300">
          <li className="px-5 py-3.5">
            <ChannelChip channel="Facebook" kind="comment" />
            <p className="mt-1 text-sm text-slate-600 truncate">Do you deliver to Sutton-in-Ashfield?</p>
          </li>
          <li className="px-5 py-3.5">
            <ChannelChip channel="TikTok" kind="comment" />
            <p className="mt-1 text-sm text-slate-600 truncate">How much was the one in this video?</p>
          </li>
        </ul>
      </div>
    </div>
  )
}

const officeJsonLd = {
  '@context': 'https://schema.org',
  '@type': 'SoftwareApplication',
  // Same @id as office.postd.uk uses for itself, so the two pages describe one entity.
  '@id': `${OFFICE_URL}/#software`,
  name: 'Postd Office',
  url: OFFICE_URL,
  applicationCategory: 'BusinessApplication',
  applicationSubCategory: 'Social media inbox',
  operatingSystem: 'Web',
  inLanguage: 'en-GB',
  description:
    'One inbox for Facebook, Instagram and TikTok DMs and comments, with AI replies drafted from answers the business has approved, automatic translation, abuse and spam flagging, and optional autopilot.',
  offers: {
    '@type': 'Offer',
    name: 'Sorting Office',
    price: '49.00',
    priceCurrency: 'GBP',
    url: OFFICE_PRICING_URL,
    availability: 'https://schema.org/InStock',
    priceSpecification: {
      '@type': 'UnitPriceSpecification',
      price: '49.00',
      priceCurrency: 'GBP',
      valueAddedTaxIncluded: false,
      billingDuration: 'P1M',
      unitCode: 'MON',
    },
  },
  publisher: { '@id': ORG_ID },
  creator: { '@id': ORG_ID },
}

export default function OfficePromo() {
  return (
    <section
      id="postd-office"
      className="py-20 sm:py-28 scroll-mt-20"
      aria-labelledby="postd-office-heading"
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="relative overflow-hidden rounded-3xl bg-gradient-navy px-6 py-12 sm:px-10 sm:py-14 lg:px-14 lg:py-16">
          {/* Soft brand glows, decorative */}
          <div className="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-amber-500/20 blur-3xl pointer-events-none" aria-hidden="true" />
          <div className="absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-honey-400/10 blur-3xl pointer-events-none" aria-hidden="true" />

          <div className="relative grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            <div className="min-w-0 text-center lg:text-left">
              <span className="inline-flex items-center gap-2 bg-honey-400 text-navy-800 text-xs font-bold uppercase tracking-wide px-3 py-1.5 rounded-full">
                New from the postd team
              </span>

              <h2
                id="postd-office-heading"
                className="mt-5 font-display font-black text-white text-3xl sm:text-4xl lg:text-5xl text-balance"
                style={{ lineHeight: 1.1 }}
              >
                Now replying, not just posting
              </h2>

              <p className="mt-5 text-lg text-white/80 leading-relaxed text-pretty max-w-xl mx-auto lg:mx-0">
                Posts bring people back with questions. <strong className="text-white font-semibold">Postd Office</strong>{' '}
                is our companion app for what comes back: every DM and comment from Facebook,
                Instagram and TikTok in one place, with replies drafted for you.
              </p>

              <ul className="mt-7 space-y-3 text-left max-w-xl mx-auto lg:mx-0">
                {POINTS.map((point) => (
                  <li key={point} className="flex gap-3 text-white/85">
                    <span className="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full bg-amber-500/20 flex items-center justify-center">
                      <Check className="w-3.5 h-3.5 text-amber-300" aria-hidden="true" />
                    </span>
                    <span className="text-sm sm:text-base leading-relaxed">{point}</span>
                  </li>
                ))}
              </ul>

              <p className="mt-8 text-white">
                <span className="text-sm text-white/70">From </span>
                <span className="font-display font-bold text-2xl">£49</span>
                <span className="text-sm text-white/70"> + VAT a month</span>
              </p>

              <div className="mt-6 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3 sm:gap-5">
                <a
                  href={OFFICE_URL}
                  className="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 min-h-[52px] bg-white text-navy-800 font-display font-bold text-base px-7 rounded-2xl hover:bg-cream-200 active:scale-[0.98] transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-navy-800"
                >
                  Explore Postd Office
                  <ArrowRight className="w-5 h-5" aria-hidden="true" />
                </a>
                <a
                  href={OFFICE_PRICING_URL}
                  className="inline-flex items-center min-h-[48px] px-2 text-base font-semibold text-white/90 underline underline-offset-4 decoration-white/40 hover:decoration-white hover:text-white transition-colors"
                >
                  See Postd Office pricing
                </a>
              </div>

              <p className="mt-5 text-xs text-white/70">
                Works alongside postd.uk or on its own. Questions? <a href={`mailto:${CONTACT_EMAIL}`} className="underline underline-offset-2 hover:text-white">{CONTACT_EMAIL}</a>
              </p>
            </div>

            <div className="min-w-0">
              <InboxPreview />
            </div>
          </div>
        </div>
      </div>

      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(officeJsonLd).replace(/</g, '\\u003c') }}
      />
    </section>
  )
}
