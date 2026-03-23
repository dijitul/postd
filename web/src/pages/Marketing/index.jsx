import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, Check, Star, Zap, Globe, MessageSquare, TrendingUp, Clock } from 'lucide-react'
import Logo from '../../components/ui/Logo.jsx'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'

// ── Animated post card ────────────────────────────────────────────────────────
function AnimatedPostCard({ platform, content, delay = 0, className = '' }) {
  return (
    <div
      className={`bg-white rounded-2xl p-4 shadow-lg border border-cream-300 animate-fade-in-up ${className}`}
      style={{ animationDelay: `${delay}ms`, animationFillMode: 'both' }}
    >
      <div className="flex items-center gap-2 mb-3">
        <PlatformIcon platform={platform} size="sm" />
        <div className="flex-1 min-w-0">
          <div className="h-2.5 bg-slate-200 rounded-full w-24 mb-1" />
          <div className="h-2 bg-slate-100 rounded-full w-16" />
        </div>
        <span className="inline-flex items-center gap-1 text-xs font-semibold text-green-600 bg-green-50 px-2 py-0.5 rounded-full">
          <span className="w-1.5 h-1.5 rounded-full bg-green-500 inline-block" />
          Live
        </span>
      </div>
      <p className="text-xs text-slate-600 leading-relaxed">{content}</p>
    </div>
  )
}

// ── Hero illustration ─────────────────────────────────────────────────────────
function HeroIllustration() {
  return (
    <div className="relative w-full max-w-md mx-auto lg:max-w-none" aria-hidden="true">
      {/* Central hub */}
      <div className="relative flex items-center justify-center h-80 sm:h-96">
        {/* Glow ring */}
        <div className="absolute w-40 h-40 rounded-full bg-amber-500/10 animate-pulse" />
        <div className="absolute w-56 h-56 rounded-full bg-amber-500/5" />

        {/* Centre logo mark */}
        <div className="relative z-10 w-20 h-20 bg-amber-500 rounded-3xl flex items-center justify-center shadow-amber-lg">
          <svg viewBox="0 0 64 64" className="w-12 h-12" fill="none">
            <rect x="8" y="16" width="32" height="22" rx="6" fill="white" />
            <path d="M14 38 L11 48 L23 38 Z" fill="white" />
            <path d="M26 20 L20 30 L24.5 30 L22 37 L28 26 L23.5 26 Z" fill="#E07B30" />
          </svg>
        </div>

        {/* Platform cards orbiting */}
        <div className="absolute top-2 left-1/2 -translate-x-1/2 -translate-y-2 animate-bounce-subtle" style={{ animationDelay: '0ms' }}>
          <PlatformIcon platform="facebook" size="lg" className="shadow-lg rounded-2xl" />
        </div>
        <div className="absolute top-12 right-4 animate-bounce-subtle" style={{ animationDelay: '400ms' }}>
          <PlatformIcon platform="instagram" size="lg" className="shadow-lg rounded-2xl" />
        </div>
        <div className="absolute bottom-12 right-4 animate-bounce-subtle" style={{ animationDelay: '800ms' }}>
          <PlatformIcon platform="linkedin" size="lg" className="shadow-lg rounded-2xl" />
        </div>
        <div className="absolute bottom-2 left-1/2 -translate-x-1/2 translate-y-2 animate-bounce-subtle" style={{ animationDelay: '200ms' }}>
          <PlatformIcon platform="google" size="lg" className="shadow-lg rounded-2xl" />
        </div>
        <div className="absolute bottom-12 left-4 animate-bounce-subtle" style={{ animationDelay: '600ms' }}>
          <PlatformIcon platform="x" size="lg" className="shadow-lg rounded-2xl" />
        </div>
        <div className="absolute top-12 left-4 animate-bounce-subtle" style={{ animationDelay: '1000ms' }}>
          <PlatformIcon platform="tiktok" size="lg" className="shadow-lg rounded-2xl" />
        </div>

        {/* Connector lines (decorative) */}
        <svg className="absolute inset-0 w-full h-full opacity-20" viewBox="0 0 320 320">
          <line x1="160" y1="160" x2="160" y2="20" stroke="#E07B30" strokeWidth="1.5" strokeDasharray="4 4" />
          <line x1="160" y1="160" x2="280" y2="80" stroke="#E07B30" strokeWidth="1.5" strokeDasharray="4 4" />
          <line x1="160" y1="160" x2="280" y2="240" stroke="#E07B30" strokeWidth="1.5" strokeDasharray="4 4" />
          <line x1="160" y1="160" x2="160" y2="300" stroke="#E07B30" strokeWidth="1.5" strokeDasharray="4 4" />
          <line x1="160" y1="160" x2="40" y2="240" stroke="#E07B30" strokeWidth="1.5" strokeDasharray="4 4" />
          <line x1="160" y1="160" x2="40" y2="80" stroke="#E07B30" strokeWidth="1.5" strokeDasharray="4 4" />
        </svg>
      </div>

      {/* Sample post cards — staggered */}
      <div className="absolute -bottom-4 -left-4 w-56 hidden sm:block">
        <AnimatedPostCard
          platform="facebook"
          content="Fantastic service from our team today — our customers keep coming back for a reason!"
          delay={600}
        />
      </div>
      <div className="absolute -top-4 -right-4 w-52 hidden lg:block">
        <AnimatedPostCard
          platform="instagram"
          content="Quality you can see. Expertise you can trust. Come and see us today!"
          delay={900}
        />
      </div>
    </div>
  )
}

// ── Pricing card ──────────────────────────────────────────────────────────────
function PricingCard({ tier, price, platforms, features, popular = false, cta = 'Start free trial' }) {
  return (
    <div className={`relative flex flex-col rounded-3xl p-7 ${popular ? 'bg-navy-800 text-white ring-2 ring-amber-500 ring-offset-2' : 'bg-white border border-cream-300'}`}
      style={{ boxShadow: popular ? '0 20px 40px -8px rgb(30 45 74 / 0.3)' : '0 2px 8px rgb(30 45 74 / 0.06)' }}
    >
      {popular && (
        <div className="absolute -top-4 left-1/2 -translate-x-1/2">
          <span className="inline-flex items-center gap-1.5 bg-amber-500 text-white text-xs font-bold px-4 py-1.5 rounded-full shadow-lg">
            <Star className="w-3.5 h-3.5" fill="currentColor" />
            Most popular
          </span>
        </div>
      )}

      <div className="mb-6">
        <h3 className={`font-display font-bold text-xl mb-1 ${popular ? 'text-white' : 'text-navy-800'}`}>{tier}</h3>
        <div className="flex items-baseline gap-1">
          <span className={`font-display font-black text-4xl ${popular ? 'text-white' : 'text-navy-800'}`}>£{price}</span>
          <span className={`text-sm font-medium ${popular ? 'text-white/60' : 'text-slate-400'}`}>/month</span>
          <span className={`text-xs ml-1 ${popular ? 'text-white/50' : 'text-slate-400'}`}>ex. VAT</span>
        </div>
        <p className={`text-sm mt-2 ${popular ? 'text-white/70' : 'text-slate-500'}`}>{platforms}</p>
      </div>

      <ul className="space-y-3 mb-8 flex-1">
        {features.map((f, i) => (
          <li key={i} className="flex items-start gap-2.5 text-sm">
            <Check className={`w-4 h-4 mt-0.5 flex-shrink-0 ${popular ? 'text-amber-400' : 'text-amber-500'}`} />
            <span className={popular ? 'text-white/80' : 'text-slate-600'}>{f}</span>
          </li>
        ))}
      </ul>

      <Link
        to="/register"
        className={`w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl font-semibold text-sm transition-all duration-200 active:scale-[0.98] ${
          popular
            ? 'bg-amber-500 text-white hover:bg-amber-600 shadow-amber-lg'
            : 'bg-navy-800 text-white hover:bg-navy-700'
        }`}
      >
        {cta}
        <ArrowRight className="w-4 h-4" />
      </Link>
    </div>
  )
}

// ── Main component ────────────────────────────────────────────────────────────
export default function MarketingPage() {
  const [scrolled, setScrolled] = useState(false)

  useEffect(() => {
    const handler = () => setScrolled(window.scrollY > 20)
    window.addEventListener('scroll', handler, { passive: true })
    return () => window.removeEventListener('scroll', handler)
  }, [])

  const platforms = [
    { id: 'facebook', label: 'Facebook', free: false },
    { id: 'instagram', label: 'Instagram', free: false },
    { id: 'x', label: 'X (Twitter)', free: false },
    { id: 'linkedin', label: 'LinkedIn', free: false },
    { id: 'tiktok', label: 'TikTok', free: false },
    { id: 'google', label: 'Google Business', free: true }
  ]

  const steps = [
    {
      number: '01',
      icon: MessageSquare,
      title: 'Tell us about your business',
      body: 'Your business name, industry, website URL, and Google Reviews link. That is genuinely all we need.'
    },
    {
      number: '02',
      icon: Globe,
      title: 'Connect your platforms',
      body: 'Link your Facebook, Instagram, LinkedIn, X, TikTok, and Google Business Profile in a few taps.'
    },
    {
      number: '03',
      icon: Zap,
      title: 'Watch the posts flow',
      body: 'Our AI reads your website and reviews, writes brilliant posts for each platform, and publishes them on a perfect schedule.'
    }
  ]

  const pricingPlans = [
    {
      tier: 'Starter',
      price: 19,
      platforms: '2 platforms + Google Business Profile',
      features: [
        'Google Business Profile (always free)',
        '2 social platforms included',
        'AI-generated posts daily',
        'Post approval inbox',
        'Website + review scanning',
        'Email support'
      ]
    },
    {
      tier: 'Growth',
      price: 39,
      platforms: '4 platforms + Google Business Profile',
      popular: true,
      features: [
        'Google Business Profile (always free)',
        '4 social platforms included',
        'AI-generated posts daily',
        'Post approval inbox',
        'Local news content hooks',
        'Priority email support',
        '14-day free trial included'
      ]
    },
    {
      tier: 'Pro',
      price: 69,
      platforms: 'All platforms + Google Business Profile',
      features: [
        'Google Business Profile (always free)',
        'All 6 platforms + TikTok video',
        'AI-generated posts daily',
        'Fully auto-posting option',
        'Local news content hooks',
        'TikTok video generation',
        'Dedicated account manager'
      ]
    }
  ]

  return (
    <div className="min-h-screen bg-cream-200 honeycomb-bg">

      {/* ── Nav ──────────────────────────────────────────────────────────────── */}
      <header
        className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
          scrolled ? 'bg-white/95 backdrop-blur-md shadow-sm border-b border-cream-300' : 'bg-transparent'
        }`}
      >
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16 sm:h-18">
            <Logo size="md" />

            <nav className="hidden sm:flex items-center gap-6">
              <a href="#how-it-works" className="text-sm font-medium text-navy-700 hover:text-amber-500 transition-colors">
                How it works
              </a>
              <a href="#platforms" className="text-sm font-medium text-navy-700 hover:text-amber-500 transition-colors">
                Platforms
              </a>
              <a href="#pricing" className="text-sm font-medium text-navy-700 hover:text-amber-500 transition-colors">
                Pricing
              </a>
            </nav>

            <div className="flex items-center gap-3">
              <Link to="/login" className="text-sm font-semibold text-navy-700 hover:text-amber-500 transition-colors px-3 py-2 hidden sm:block">
                Log in
              </Link>
              <Link
                to="/register"
                className="inline-flex items-center gap-2 bg-amber-500 text-white text-sm font-bold px-5 py-2.5 rounded-xl hover:bg-amber-700 active:scale-[0.97] transition-all duration-200"
                style={{ boxShadow: '0 4px 16px rgb(224 123 48 / 0.3)' }}
              >
                Start free trial
                <ArrowRight className="w-4 h-4" />
              </Link>
            </div>
          </div>
        </div>
      </header>

      {/* ── Hero ─────────────────────────────────────────────────────────────── */}
      <section className="relative pt-28 sm:pt-32 pb-16 sm:pb-24 overflow-hidden">
        {/* Background gradient blob */}
        <div className="absolute top-0 right-0 w-96 h-96 bg-amber-500/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 pointer-events-none" />
        <div className="absolute bottom-0 left-0 w-80 h-80 bg-honey-400/10 rounded-full blur-3xl translate-y-1/3 -translate-x-1/3 pointer-events-none" />

        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">

            {/* Left — copy */}
            <div className="text-center lg:text-left animate-fade-in-up">
              <div className="inline-flex items-center gap-2 bg-amber-500/10 text-amber-700 text-xs font-bold px-4 py-2 rounded-full mb-6 border border-amber-500/20">
                <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse" />
                Built for UK small businesses
              </div>

              <h1 className="font-display font-black text-navy-800 mb-6 text-balance" style={{ fontSize: 'clamp(2.25rem, 5vw, 3.75rem)', lineHeight: '1.1' }}>
                Your business,
                <br />
                <span className="text-gradient-amber">always posting.</span>
              </h1>

              <p className="text-lg sm:text-xl text-slate-600 mb-8 max-w-xl mx-auto lg:mx-0 text-pretty leading-relaxed">
                Give us your website, your Google Reviews link, and a bit about your business.
                We write and post brilliant social content across all your platforms — automatically, every day.
              </p>

              <div className="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 mb-10">
                <Link
                  to="/register"
                  className="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 bg-amber-500 text-white font-bold text-base px-8 py-4 rounded-2xl hover:bg-amber-700 active:scale-[0.98] transition-all duration-200"
                  style={{ boxShadow: '0 8px 24px rgb(224 123 48 / 0.35)' }}
                >
                  Start free 14-day trial
                  <ArrowRight className="w-5 h-5" />
                </Link>
                <a
                  href="#how-it-works"
                  className="w-full sm:w-auto inline-flex items-center justify-center gap-2 border border-navy-200 text-navy-700 font-semibold text-base px-8 py-4 rounded-2xl hover:bg-white hover:border-navy-300 transition-all duration-200"
                >
                  See how it works
                </a>
              </div>

              {/* Trust signals */}
              <div className="flex flex-wrap items-center justify-center lg:justify-start gap-5 text-sm text-slate-500">
                <span className="flex items-center gap-1.5">
                  <Check className="w-4 h-4 text-green-500" />
                  No card required
                </span>
                <span className="flex items-center gap-1.5">
                  <Check className="w-4 h-4 text-green-500" />
                  Cancel anytime
                </span>
                <span className="flex items-center gap-1.5">
                  <Check className="w-4 h-4 text-green-500" />
                  UK-based team
                </span>
                <span className="flex items-center gap-1.5">
                  <Check className="w-4 h-4 text-green-500" />
                  GDPR compliant
                </span>
              </div>
            </div>

            {/* Right — illustration */}
            <div className="animate-fade-in" style={{ animationDelay: '200ms', animationFillMode: 'both' }}>
              <HeroIllustration />
            </div>
          </div>
        </div>
      </section>

      {/* ── How it works ─────────────────────────────────────────────────────── */}
      <section id="how-it-works" className="py-20 sm:py-28 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <span className="inline-block text-amber-500 text-sm font-bold uppercase tracking-widest mb-4">Simple as that</span>
            <h2 className="font-display font-black text-navy-800 text-3xl sm:text-4xl lg:text-5xl mb-4 text-balance">
              Three things. That&apos;s all.
            </h2>
            <p className="text-lg text-slate-500 max-w-2xl mx-auto text-pretty">
              Most social media tools are a second job. postd.uk is the opposite.
              You set it up once, and it just works.
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-6 lg:gap-8">
            {steps.map((step, i) => (
              <div
                key={step.number}
                className="relative bg-cream-200 rounded-3xl p-8 group hover:-translate-y-1 transition-all duration-300"
                style={{ animationDelay: `${i * 150}ms` }}
              >
                {/* Step connector */}
                {i < steps.length - 1 && (
                  <div className="hidden md:block absolute top-12 -right-4 z-10">
                    <ArrowRight className="w-6 h-6 text-amber-300" />
                  </div>
                )}

                <div className="w-12 h-12 bg-amber-500 rounded-2xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-200">
                  <step.icon className="w-6 h-6 text-white" />
                </div>

                <div className="text-5xl font-display font-black text-amber-500/10 mb-2 leading-none">{step.number}</div>

                <h3 className="font-display font-bold text-lg text-navy-800 mb-3">{step.title}</h3>
                <p className="text-slate-600 leading-relaxed text-sm">{step.body}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Platforms ────────────────────────────────────────────────────────── */}
      <section id="platforms" className="py-20 sm:py-28">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <span className="inline-block text-amber-500 text-sm font-bold uppercase tracking-widest mb-4">All your channels</span>
            <h2 className="font-display font-black text-navy-800 text-3xl sm:text-4xl lg:text-5xl mb-4 text-balance">
              Supported platforms
            </h2>
            <p className="text-lg text-slate-500 max-w-2xl mx-auto">
              One tool. Six platforms. Every post written natively for the platform it&apos;s posted to.
            </p>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            {platforms.map(({ id, label, free }) => (
              <div
                key={id}
                className="relative bg-white rounded-2xl p-5 flex flex-col items-center gap-3 border border-cream-300 hover:-translate-y-1 hover:shadow-lg transition-all duration-200 group"
                style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.05)' }}
              >
                {free && (
                  <span className="absolute -top-3 left-1/2 -translate-x-1/2 bg-honey-400 text-navy-800 text-2xs font-black uppercase tracking-wide px-3 py-1 rounded-full whitespace-nowrap">
                    Free on every plan
                  </span>
                )}
                <PlatformIcon platform={id} size="lg" />
                <span className="text-xs font-semibold text-navy-700 text-center leading-tight">{label}</span>
              </div>
            ))}
          </div>

          {/* GBP differentiator callout */}
          <div className="mt-10 bg-honey-50 border border-honey-200 rounded-2xl p-6 flex flex-col sm:flex-row items-center gap-4">
            <div className="flex-shrink-0">
              <PlatformIcon platform="google" size="lg" />
            </div>
            <div>
              <h3 className="font-display font-bold text-navy-800 mb-1">Google Business Profile is FREE on every plan</h3>
              <p className="text-slate-600 text-sm leading-relaxed">
                Unlike our competitors, we include Google Business Profile posting on every tier — even Starter.
                It&apos;s one of the most powerful local SEO tools available, and we think you shouldn&apos;t have to pay extra for it.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* ── Pricing ──────────────────────────────────────────────────────────── */}
      <section id="pricing" className="py-20 sm:py-28 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <span className="inline-block text-amber-500 text-sm font-bold uppercase tracking-widest mb-4">Simple pricing</span>
            <h2 className="font-display font-black text-navy-800 text-3xl sm:text-4xl lg:text-5xl mb-4 text-balance">
              Start posting in minutes
            </h2>
            <p className="text-lg text-slate-500 max-w-2xl mx-auto">
              All prices ex. VAT. 14-day free trial on Growth — no card required.
              Cancel anytime, no questions asked.
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-6 lg:gap-8 items-center">
            {pricingPlans.map((plan) => (
              <PricingCard key={plan.tier} {...plan} />
            ))}
          </div>

          <p className="text-center text-sm text-slate-400 mt-8">
            All prices are exclusive of VAT. UK VAT (20%) applied at checkout via Stripe Tax.
            TikTok add-on available for +£15/month on Starter and Growth.
          </p>
        </div>
      </section>

      {/* ── Final CTA ────────────────────────────────────────────────────────── */}
      <section className="py-20 sm:py-28 bg-gradient-navy relative overflow-hidden">
        <div className="absolute inset-0 honeycomb-bg opacity-30 pointer-events-none" />
        <div className="relative max-w-3xl mx-auto px-4 sm:px-6 text-center">
          <div className="w-16 h-16 bg-amber-500 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-amber-glow">
            <Zap className="w-8 h-8 text-white" />
          </div>
          <h2 className="font-display font-black text-white text-3xl sm:text-4xl lg:text-5xl mb-6 text-balance">
            All your posts. One hive.
          </h2>
          <p className="text-white/70 text-lg sm:text-xl mb-10 max-w-xl mx-auto text-pretty">
            Join hundreds of UK small businesses who&apos;ve stopped worrying about social media
            and started focusing on what they do best.
          </p>
          <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
            <Link
              to="/register"
              className="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 bg-amber-500 text-white font-bold text-base px-8 py-4 rounded-2xl hover:bg-amber-600 active:scale-[0.98] transition-all duration-200"
              style={{ boxShadow: '0 8px 24px rgb(224 123 48 / 0.4)' }}
            >
              Start your free 14-day trial
              <ArrowRight className="w-5 h-5" />
            </Link>
            <div className="text-white/50 text-sm">No card required. Cancel anytime.</div>
          </div>
        </div>
      </section>

      {/* ── Footer ───────────────────────────────────────────────────────────── */}
      <footer className="bg-navy-950 py-10">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
            <Logo variant="reversed" size="sm" asLink={false} />

            <nav className="flex items-center gap-6">
              <Link to="/privacy" className="text-sm text-white/40 hover:text-white/70 transition-colors">Privacy</Link>
              <Link to="/terms" className="text-sm text-white/40 hover:text-white/70 transition-colors">Terms</Link>
              <Link to="/login" className="text-sm text-white/40 hover:text-white/70 transition-colors">Log in</Link>
            </nav>

            <p className="text-xs text-white/30 text-center sm:text-right">
              Made by{' '}
              <a href="https://dijitul.uk" className="text-white/50 hover:text-white/70 transition-colors" target="_blank" rel="noopener noreferrer">
                dijitul
              </a>{' '}
              in Mansfield, UK
            </p>
          </div>
        </div>
      </footer>
    </div>
  )
}
