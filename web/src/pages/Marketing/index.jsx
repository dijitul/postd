import { Link } from 'react-router-dom'
import { ArrowRight, Check, Sparkles, Zap, Globe, MessageSquare, CalendarClock } from 'lucide-react'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'
import SiteHeader from '../../components/marketing/SiteHeader.jsx'
import SiteFooter from '../../components/marketing/SiteFooter.jsx'
import HomeFaq, { HomeSeo } from './HomeFaq.jsx'
import PricingSection from './PricingSection.jsx'
import OfficePromo from './OfficePromo.jsx'
import { pricingJsonLdOffers } from '../../lib/plans.js'

// ── Floating post card (decorative) ──────────────────────────────────────────
// Each card is one post as postd would write it: the violet chip marks the
// words as AI-written, the second chip shows where it is in the schedule.
function FloatingPost({ platform, platformName, business, text, status, className = '', delay = 0 }) {
  return (
    <div
      className={`card-marketing p-4 sm:p-5 w-full max-w-[20rem] motion-safe:animate-float ${className}`}
      style={{ animationDelay: `${delay}ms` }}
    >
      <div className="flex items-center gap-3">
        <PlatformIcon platform={platform} size="md" container="soft" />
        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold text-navy-900 leading-tight truncate">{business}</p>
          <p className="text-xs text-slate-500 leading-tight mt-0.5">{platformName}</p>
        </div>
      </div>
      <p className="mt-3 text-sm text-navy-900 leading-relaxed">{text}</p>
      <div className="mt-4 flex flex-wrap items-center gap-2">
        <span className="chip chip-ai">
          <Sparkles className="w-3 h-3" aria-hidden="true" />
          Written by postd
        </span>
        {status === 'posted' ? (
          <span className="chip chip-success">
            <Check className="w-3 h-3" aria-hidden="true" />
            Posted
          </span>
        ) : (
          <span className="chip chip-cobalt">
            <CalendarClock className="w-3 h-3" aria-hidden="true" />
            {status}
          </span>
        )}
      </div>
    </div>
  )
}

// ── Hero illustration ────────────────────────────────────────────────────────
function HeroIllustration() {
  return (
    <div className="relative w-full" aria-hidden="true">
      {/* Cloud panel the cards float over */}
      <div className="absolute inset-x-0 top-6 bottom-6 sm:inset-x-6 lg:inset-x-0 lg:top-0 lg:bottom-0 rounded-[2rem] bg-cloud" />
      <div className="absolute right-6 top-4 w-14 h-14 rounded-full bg-sun-400 hidden lg:block" />

      <div className="relative flex flex-col items-center gap-4 py-10 px-4 lg:block lg:h-[38rem] lg:py-0">
        <FloatingPost
          platform="google"
          platformName="Google Business Profile"
          business="Hart and Sons Bakery"
          text="Our sourdough is back on the shelves from 8am. Bake days are Tuesday, Thursday and Saturday, so pop in early."
          status="posted"
          className="lg:absolute lg:left-0 lg:top-8"
        />
        <FloatingPost
          platform="facebook"
          platformName="Facebook"
          business="Hart and Sons Bakery"
          text={'"Best custard slice in Mansfield, hands down." Thank you, Sarah. We will have a fresh batch ready for you on Friday.'}
          status="Fri 9:30am"
          className="lg:absolute lg:right-0 lg:top-[15rem]"
          delay={900}
        />
        <FloatingPost
          platform="linkedin"
          platformName="LinkedIn"
          business="Hart and Sons Bakery"
          text="We now supply fresh bread to three offices in town. Weekly orders, delivered before 9."
          status="Mon 8:00am"
          className="hidden sm:block lg:absolute lg:left-6 lg:bottom-2"
          delay={1800}
        />
      </div>
    </div>
  )
}

const TRUST = ['No card required', 'Cancel anytime', 'UK-based team', 'GDPR compliant']

const PLATFORMS = [
  { id: 'google', label: 'Google Business', badge: null },
  { id: 'facebook', label: 'Facebook', badge: null },
  { id: 'linkedin', label: 'LinkedIn', badge: null },
  { id: 'x', label: 'X (Twitter)', badge: null },
]

const STEPS = [
  {
    number: '01',
    icon: MessageSquare,
    title: 'Tell us about your business',
    body: 'Your business name, industry, and website URL. That is genuinely all we need to get started.',
  },
  {
    number: '02',
    icon: Globe,
    title: 'Connect your platforms',
    body: 'Link your Google Business Profile, Facebook, LinkedIn and X (Twitter) in a few taps. We handle the rest.',
  },
  {
    number: '03',
    icon: Zap,
    title: 'Your AI social media posts go live automatically',
    body: 'Our AI reads your website and reviews, writes platform-native content for Google Business Profile, Facebook, LinkedIn and X, and publishes it all on a sensible schedule, fully automated.',
  },
]

export default function MarketingPage() {
  return (
    <div className="min-h-screen bg-white">
      <HomeSeo />

      <SiteHeader />

      <main>
        {/* ── Hero ──────────────────────────────────────────────────────────── */}
        <section className="relative overflow-hidden bg-white">
          <div className="container-brand pt-10 pb-16 sm:pt-16 lg:pt-20 lg:pb-24">
            <div className="grid lg:grid-cols-[1.05fr_1fr] gap-10 lg:gap-14 items-center">

              <div className="min-w-0 text-center lg:text-left">
                <p className="inline-flex items-center gap-2 bg-cobalt-50 text-cobalt-700 text-xs font-semibold px-3.5 py-1.5 rounded-full mb-6">
                  <span className="w-1.5 h-1.5 rounded-full bg-cobalt-600" aria-hidden="true" />
                  AI social media automation for UK small businesses
                </p>

                <h1 className="display text-balance mb-6">
                  Post every week without{' '}
                  <span className="highlight">thinking</span>{' '}
                  about it.
                </h1>

                <p className="lead max-w-xl mx-auto lg:mx-0 mb-8 text-pretty">
                  Give us your website, your Google Reviews link, and a bit about your business.
                  Our AI writes and automatically posts content to Google Business Profile, Facebook, LinkedIn and X every week, so you never have to think about social media again.
                </p>

                <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-center lg:justify-start gap-3 mb-8">
                  <Link to="/register" className="btn btn-primary btn-lg">
                    Start free 14-day trial
                    <ArrowRight className="w-5 h-5" aria-hidden="true" />
                  </Link>
                  <a href="#how-it-works" className="btn btn-secondary btn-lg">
                    See how it works
                  </a>
                </div>

                <ul className="flex flex-wrap items-center justify-center lg:justify-start gap-x-5 gap-y-2 pl-0 text-sm text-slate-500">
                  {TRUST.map((t) => (
                    <li key={t} className="flex items-center gap-1.5 mb-0">
                      <Check className="w-4 h-4 text-success" aria-hidden="true" />
                      {t}
                    </li>
                  ))}
                </ul>
              </div>

              <div className="min-w-0">
                <HeroIllustration />
              </div>
            </div>
          </div>
        </section>

        {/* ── How it works ──────────────────────────────────────────────────── */}
        <section id="how-it-works" className="section-padding bg-cloud scroll-mt-16">
          <div className="container-brand">
            <div className="text-center mb-12 lg:mb-16">
              <span className="eyebrow mb-3">Simple as that</span>
              <h2 className="h2-marketing mb-4 text-balance">
                How AI social media automation works
              </h2>
              <p className="lead max-w-2xl mx-auto text-pretty">
                Most social media management tools are a second job. postd.uk is different.
                Set it up once and your automated social media posts go out every week without any effort from you.
              </p>
            </div>

            <ol className="grid md:grid-cols-3 gap-5 lg:gap-6 pl-0">
              {STEPS.map((step) => (
                <li key={step.number} className="card-marketing p-7 lg:p-8 mb-0">
                  <div className="flex items-center justify-between mb-6">
                    <span className="w-12 h-12 rounded-full bg-cobalt-50 flex items-center justify-center">
                      <step.icon className="w-6 h-6 text-cobalt-600" aria-hidden="true" />
                    </span>
                    <span className="font-display font-extrabold text-sm text-slate-500 tracking-wide">{step.number}</span>
                  </div>
                  <h3 className="font-display font-bold text-xl text-navy-900 mb-3 tracking-tight">{step.title}</h3>
                  <p className="text-slate-500 leading-relaxed text-[15px]">{step.body}</p>
                </li>
              ))}
            </ol>
          </div>
        </section>

        {/* ── Platforms ─────────────────────────────────────────────────────── */}
        <section id="platforms" className="section-padding bg-white scroll-mt-16">
          <div className="container-brand">
            <div className="text-center mb-12">
              <span className="eyebrow mb-3">All your channels</span>
              <h2 className="h2-marketing mb-4 text-balance">
                Social media platforms we post to automatically
              </h2>
              <p className="lead max-w-2xl mx-auto">
                Every AI-generated post is written natively for the platform it is published to. No generic copy-paste, no recycled content.
              </p>
            </div>

            <ul className="grid grid-cols-2 lg:grid-cols-4 gap-4 max-w-4xl mx-auto pl-0">
              {PLATFORMS.map(({ id, label, badge }) => (
                <li
                  key={id}
                  className="relative card p-6 mb-0 flex flex-col items-center gap-3 transition-shadow duration-200 hover:shadow-marketing"
                >
                  {badge && <span className="badge-sun absolute -top-3 left-1/2 -translate-x-1/2">{badge}</span>}
                  <PlatformIcon platform={id} size="xl" container="soft" />
                  <span className="text-sm font-semibold text-navy-900 text-center leading-tight">{label}</span>
                </li>
              ))}
            </ul>
          </div>
        </section>

        <PricingSection />

        {/* Sister product: Postd Office */}
        <OfficePromo />

        <HomeFaq />

        {/* ── Closing call to action (navy band) ────────────────────────────── */}
        <section className="section-padding bg-navy-900 relative overflow-hidden">
          <div className="absolute -top-16 -right-16 w-48 h-48 rounded-full bg-sun-400/90 pointer-events-none hidden md:block" aria-hidden="true" />
          <div className="relative max-w-3xl mx-auto px-4 sm:px-6 text-center">
            <h2 className="h2-marketing !text-white mb-5 text-balance">
              Automated social media posting, sorted.
            </h2>
            <p className="text-white/80 text-[17px] lg:text-[19px] leading-relaxed mb-9 max-w-xl mx-auto text-pretty">
              Stop worrying about social media. postd writes and publishes your posts from your
              own website and reviews, so you can get on with what you do best.
            </p>
            <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
              <Link to="/register" className="btn btn-on-dark btn-lg w-full sm:w-auto">
                Start your free 14-day trial
                <ArrowRight className="w-5 h-5" aria-hidden="true" />
              </Link>
              <p className="text-white/75 text-sm">No card required. Cancel anytime.</p>
            </div>
          </div>
        </section>
      </main>

      {/* ── Structured data (JSON-LD) ──────────────────────────────────────── */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: JSON.stringify({
            "@context": "https://schema.org",
            "@type": "SoftwareApplication",
            // Same @id as the site-wide schema in lib/site.js, so crawlers
            // merge the two into one entity rather than seeing two products.
            "@id": "https://postd.uk/#software",
            "name": "postd.uk",
            "url": "https://postd.uk",
            "description": "AI-powered social media automation for UK small businesses. Automatically writes and posts content to Google Business Profile, Facebook, LinkedIn and X. From £15/month.",
            "applicationCategory": "BusinessApplication",
            "operatingSystem": "Web",
            "offers": pricingJsonLdOffers(),
            "creator": {
              "@type": "Organization",
              "name": "dijitul",
              "url": "https://dijitul.uk"
            }
          })
        }}
      />

      <SiteFooter />
    </div>
  )
}
