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
    <div className={`relative flex flex-col card-marketing p-7 lg:p-8 ${popular ? 'ring-2 ring-cobalt-600 md:-my-3 md:py-10' : ''}`}>
      {popular && (
        <div className="absolute -top-3.5 left-1/2 -translate-x-1/2">
          <span className="badge-sun whitespace-nowrap">
            <Star className="w-3.5 h-3.5" fill="currentColor" aria-hidden="true" />
            Most popular
          </span>
        </div>
      )}

      <div className="mb-6">
        <h3 className="font-display font-bold text-xl mb-2 text-navy-900">{plan.name}</h3>
        <div className="flex items-baseline gap-1 flex-wrap">
          <span className="font-display font-extrabold text-[2.75rem] leading-none tracking-tightest text-navy-900">
            {formatPounds(isAnnual ? plan.annual : plan.monthly)}
          </span>
          <span className="text-sm font-medium text-slate-500">
            /{isAnnual ? 'year' : 'month'}{VAT_SUFFIX}
          </span>
        </div>
        <p className="text-xs mt-2 h-4 text-slate-500">
          {isAnnual ? `Works out at ${formatPounds(annualPerMonth(plan.annual))}/month` : ''}
        </p>
        <p className="text-[15px] mt-2 text-slate-500">{plan.tagline}</p>
      </div>

      <ul className="space-y-3 mb-8 flex-1 pl-0">
        {plan.features.map((f) => (
          <li key={f} className="flex items-start gap-2.5 text-[15px] mb-0">
            <span className="mt-0.5 w-5 h-5 rounded-full bg-cobalt-50 flex items-center justify-center flex-shrink-0">
              <Check className="w-3.5 h-3.5 text-cobalt-600" aria-hidden="true" />
            </span>
            <span className="text-navy-900">{f}</span>
          </li>
        ))}
      </ul>

      <Link to="/register" className={`btn w-full ${popular ? 'btn-primary' : 'btn-secondary'}`}>
        Start free trial
        <ArrowRight className="w-4 h-4" aria-hidden="true" />
      </Link>
    </div>
  )
}

export default function PricingSection() {
  const [interval, setBillingInterval] = useState('monthly')

  return (
    <section id="pricing" className="section-padding bg-cloud scroll-mt-16">
      <div className="container-brand">
        <div className="text-center mb-10">
          <span className="eyebrow mb-3">Simple pricing</span>
          <h2 className="h2-marketing mb-4 text-balance">
            Simple pricing for UK small businesses
          </h2>
          <p className="lead max-w-2xl mx-auto">
            Try everything in Growth free for {TRIAL.days} days, no card required.
            Then pick the plan that fits. Cancel anytime.
          </p>
        </div>

        <div className="flex justify-center mb-12">
          <IntervalToggle interval={interval} onChange={setBillingInterval} />
        </div>

        <div className="grid md:grid-cols-3 gap-8 md:gap-5 lg:gap-8 items-stretch max-w-5xl mx-auto">
          {PLANS.map((plan) => (
            <PricingCard key={plan.id} plan={plan} interval={interval} />
          ))}
        </div>

        <div className="mt-12 max-w-3xl mx-auto text-center">
          <p className="text-sm font-semibold text-navy-900 mb-3">On every plan</p>
          <ul className="flex flex-col sm:flex-row sm:flex-wrap justify-center gap-x-6 gap-y-2 pl-0">
            {EVERY_PLAN.map((f) => (
              <li key={f} className="inline-flex items-center justify-center gap-2 text-sm text-slate-500 mb-0">
                <Check className="w-4 h-4 text-cobalt-600 flex-shrink-0" aria-hidden="true" />
                {f}
              </li>
            ))}
          </ul>
          <p className="text-xs text-slate-500 mt-6">
            When your trial ends, posting pauses until you choose a plan. Nothing is deleted.
          </p>
        </div>
      </div>
    </section>
  )
}
