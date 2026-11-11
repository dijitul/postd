import { ArrowRight, Check, Inbox, Sparkles } from 'lucide-react'
import { CONTACT_EMAIL, ORG_ID } from '../../lib/site.js'
import { Logo } from '../../components/ui/Logo.jsx'

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
    <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500">
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
      <div className="bg-white rounded-2xl shadow-marketing border border-line overflow-hidden">
        <div className="flex items-center gap-2 px-5 py-3.5 border-b border-line">
          <Inbox className="w-4 h-4 text-navy-900" />
          <span className="font-display font-bold text-sm text-navy-900">Inbox</span>
          <span className="ml-auto chip bg-sun-400 text-navy-900">3 waiting</span>
        </div>

        <div className="p-5">
          <ChannelChip channel="Instagram" kind="DM" />
          <p className="mt-2 text-sm text-navy-900 font-medium">
            Hiya, have you got any slots this Saturday morning?
          </p>

          <div className="mt-4 rounded-2xl bg-ai-50 p-4">
            <p className="inline-flex items-center gap-1.5 text-xs font-semibold text-ai-600">
              <Sparkles className="w-3.5 h-3.5" />
              AI reply, ready to send
            </p>
            <p className="mt-1.5 text-sm text-navy-900 leading-relaxed">
              Morning! We have 9:30 and 11:00 free on Saturday. Want me to pop you in for one of those?
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
              <span className="inline-flex items-center bg-cobalt-600 text-white font-display text-xs font-semibold px-3.5 py-2 rounded-full">
                Approve and send
              </span>
              <span className="inline-flex items-center bg-white text-navy-900 font-display text-xs font-semibold px-3.5 py-2 rounded-full ring-[1.5px] ring-inset ring-line">
                Edit
              </span>
            </div>
          </div>
        </div>

        <ul className="border-t border-line divide-y divide-line pl-0">
          <li className="px-5 py-3.5 mb-0">
            <ChannelChip channel="Facebook" kind="comment" />
            <p className="mt-1 text-sm text-slate-500 truncate">Do you deliver to Sutton-in-Ashfield?</p>
          </li>
          <li className="px-5 py-3.5 mb-0">
            <ChannelChip channel="TikTok" kind="comment" />
            <p className="mt-1 text-sm text-slate-500 truncate">How much was the one in this video?</p>
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
      className="section-padding bg-white scroll-mt-16"
      aria-labelledby="postd-office-heading"
    >
      <div className="container-brand">
        <div className="relative overflow-hidden rounded-[2rem] bg-cloud px-5 py-10 sm:px-10 sm:py-14 lg:px-14 lg:py-16">
          <div className="relative grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
            <div className="min-w-0 text-center lg:text-left">
              <div className="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3">
                <Logo product="office" size="md" asLink={false} />
                <span className="badge-sun">New from the postd team</span>
              </div>

              <h2
                id="postd-office-heading"
                className="mt-6 h2-marketing text-balance"
              >
                Now replying, not just posting
              </h2>

              <p className="mt-5 lead max-w-xl mx-auto lg:mx-0 text-pretty">
                Posts bring people back with questions. <strong className="text-navy-900 font-semibold">Postd Office</strong>{' '}
                is our companion app for what comes back: every DM and comment from Facebook,
                Instagram and TikTok in one place, with replies drafted for you.
              </p>

              <ul className="mt-7 space-y-3 text-left max-w-xl mx-auto lg:mx-0 pl-0">
                {POINTS.map((point) => (
                  <li key={point} className="flex gap-3 text-navy-900 mb-0">
                    <span className="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full bg-navy-900 flex items-center justify-center">
                      <Check className="w-3.5 h-3.5 text-white" aria-hidden="true" />
                    </span>
                    <span className="text-[15px] sm:text-base leading-relaxed">{point}</span>
                  </li>
                ))}
              </ul>

              <p className="mt-8 text-navy-900">
                <span className="text-sm text-slate-500">From </span>
                <span className="font-display font-extrabold text-2xl tracking-tight">£49</span>
                <span className="text-sm text-slate-500"> + VAT a month</span>
              </p>

              <div className="mt-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-center lg:justify-start gap-3 sm:gap-4">
                <a
                  href={OFFICE_URL}
                  className="btn btn-lg bg-navy-900 text-white hover:bg-navy-700 hover:text-white"
                >
                  Explore Postd Office
                  <ArrowRight className="w-5 h-5" aria-hidden="true" />
                </a>
                <a href={OFFICE_PRICING_URL} className="btn btn-ghost btn-lg">
                  See Postd Office pricing
                </a>
              </div>

              <p className="mt-5 text-sm text-slate-500 break-words">
                Works alongside postd.uk or on its own. Questions?{' '}
                <a href={`mailto:${CONTACT_EMAIL}`} className="inline-flex items-center min-h-[44px] font-semibold underline underline-offset-2">
                  {CONTACT_EMAIL}
                </a>
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
