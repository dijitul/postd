import { useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, Check, Star } from 'lucide-react'
import IntervalToggle from '../../components/ui/IntervalToggle.jsx'
import {
  PLANS, EVERY_PLAN, TRIAL, VAT_SUFFIX,
  formatPounds, annualPerMonth,
} from '../../lib/plans.js'

function PricingCard({ plan, interval }) {
  const { popular } = plan
  const isAnnual = interval === 'annual'

  return (
    <div
      className={`relative flex flex-col rounded-3xl p-7 ${popular ? 'bg-navy-800 text-white ring-2 ring-amber-500 ring-offset-2' : 'bg-white border border-cream-300'}`}
      style={{ boxShadow: popular ? '0 20px 40px -8px rgb(30 45 74 / 0.3)' : '0 2px 8px rgb(30 45 74 / 0.06)' }}
    >
      {popular && (
        <div className="absolute -top-4 left-1/2 -translate-x-1/2">
          <span className="inline-flex items-center gap-1.5 bg-amber-500 text-white text-xs font-bold px-4 py-1.5 rounded-full shadow-lg whitespace-nowrap">
            <Star className="w-3.5 h-3.5" fill="currentColor" />
            Most popular
          </span>
        </div>
      )}

      <div className="mb-6">
        <h3 className={`font-display font-bold text-xl mb-1 ${popular ? 'text-white' : 'text-navy-800'}`}>{plan.name}</h3>
        <div className="flex items-baseline gap-1 flex-wrap">
          <span className={`font-display font-black text-4xl ${popular ? 'text-white' : 'text-navy-800'}`}>
            {formatPounds(isAnnual ? plan.annual : plan.monthly)}
          </span>
          <span className={`text-sm font-medium ${popular ? 'text-white/60' : 'text-slate-400'}`}>
            /{isAnnual ? 'year' : 'month'}{VAT_SUFFIX}
          </span>
        </div>
        <p className={`text-xs mt-1 h-4 ${popular ? 'text-white/60' : 'text-slate-400'}`}>
          {isAnnual ? `Works out at ${formatPounds(annualPerMonth(plan.annual))}/month` : ''}
        </p>
        <p className={`text-sm mt-2 ${popular ? 'text-white/70' : 'text-slate-500'}`}>{plan.tagline}</p>
      </div>

      <ul className="space-y-3 mb-8 flex-1">
        {plan.features.map((f) => (
          <li key={f} className="flex items-start gap-2.5 text-sm">
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
        Start free trial
        <ArrowRight className="w-4 h-4" />
      </Link>
    </div>
  )
}

export default function PricingSection() {
  const [interval, setBillingInterval] = useState('monthly')

  return (
    <section id="pricing" className="py-20 sm:py-28 bg-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-10">
          <span className="inline-block text-amber-500 text-sm font-bold uppercase tracking-widest mb-4">Simple pricing</span>
          <h2 className="font-display font-black text-navy-800 text-3xl sm:text-4xl lg:text-5xl mb-4 text-balance">
            Simple pricing for UK small businesses
          </h2>
          <p className="text-lg text-slate-500 max-w-2xl mx-auto">
            Try everything in Growth free for {TRIAL.days} days, no card required.
            Then pick the plan that fits. Cancel anytime.
          </p>
        </div>

        <div className="flex justify-center mb-12">
          <IntervalToggle interval={interval} onChange={setBillingInterval} />
        </div>

        <div className="grid md:grid-cols-3 gap-6 lg:gap-8 items-stretch">
          {PLANS.map((plan) => (
            <PricingCard key={plan.id} plan={plan} interval={interval} />
          ))}
        </div>

        <div className="mt-10 max-w-3xl mx-auto text-center">
          <p className="text-sm font-semibold text-navy-800 mb-3">On every plan</p>
          <ul className="flex flex-col sm:flex-row sm:flex-wrap justify-center gap-x-6 gap-y-2">
            {EVERY_PLAN.map((f) => (
              <li key={f} className="inline-flex items-center justify-center gap-2 text-sm text-slate-600">
                <Check className="w-4 h-4 text-amber-500 flex-shrink-0" />
                {f}
              </li>
            ))}
          </ul>
          <p className="text-xs text-slate-400 mt-6">
            When your trial ends, posting pauses until you choose a plan. Nothing is deleted.
          </p>
        </div>
      </div>
    </section>
  )
}
