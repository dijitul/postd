import { useState, useEffect } from 'react'
import { Check, CreditCard, Star, ArrowRight, FileText, AlertTriangle, Loader2 } from 'lucide-react'
import { billingApi } from '../../lib/api.js'

const PLANS = [
  {
    id: 'starter',
    name: 'Starter',
    price: 19,
    description: 'Perfect for getting started with social media automation.',
    features: [
      'Facebook, Instagram, X & LinkedIn',
      'AI-generated posts daily',
      'Post approval inbox',
      'Website and review content scanning',
      'Email support'
    ]
  },
  {
    id: 'growth',
    name: 'Growth',
    price: 39,
    popular: true,
    description: 'The most popular choice for growing UK businesses.',
    features: [
      'Facebook, Instagram, X & LinkedIn',
      'AI-generated posts daily',
      'Post approval inbox',
      'Local news content hooks',
      'Higher posting frequency',
      'Priority email support'
    ]
  },
  {
    id: 'pro',
    name: 'Pro',
    price: 69,
    description: 'For businesses serious about dominating their social presence.',
    features: [
      'Facebook, Instagram, X & LinkedIn',
      'TikTok video generation included',
      'AI-generated posts daily',
      'Fully auto-posting option',
      'Local news content hooks',
      'Dedicated account manager'
    ]
  }
]


function PlanCard({ plan, currentPlan, onSelect }) {
  const isCurrent = currentPlan === plan.id
  const isUpgrade = !isCurrent

  return (
    <div className={`relative rounded-2xl p-6 border-2 transition-all duration-200 ${
      isCurrent
        ? 'border-amber-500 bg-amber-50'
        : plan.popular
        ? 'border-navy-200 bg-white hover:border-amber-300'
        : 'border-slate-200 bg-white hover:border-slate-300'
    }`}
      style={{ boxShadow: isCurrent ? '0 0 0 4px rgb(224 123 48 / 0.1)' : '0 2px 8px rgb(30 45 74 / 0.05)' }}>

      {plan.popular && !isCurrent && (
        <div className="absolute -top-3 left-1/2 -translate-x-1/2">
          <span className="inline-flex items-center gap-1 bg-navy-800 text-white text-xs font-bold px-3 py-1 rounded-full">
            <Star className="w-3 h-3" fill="currentColor" />
            Most popular
          </span>
        </div>
      )}

      {isCurrent && (
        <div className="absolute -top-3 left-1/2 -translate-x-1/2">
          <span className="inline-flex items-center gap-1 bg-amber-500 text-white text-xs font-bold px-3 py-1 rounded-full">
            <Check className="w-3 h-3" />
            Current plan
          </span>
        </div>
      )}

      <div className="mb-5">
        <h3 className="font-display font-bold text-lg text-navy-800 mb-1">{plan.name}</h3>
        <div className="flex items-baseline gap-1 mb-2">
          <span className="font-display font-black text-3xl text-navy-800">£{plan.price}</span>
          <span className="text-sm text-slate-400">/month ex. VAT</span>
        </div>
        <p className="text-sm text-slate-500">{plan.description}</p>
      </div>

      <ul className="space-y-2 mb-6">
        {plan.features.map((f, i) => (
          <li key={i} className="flex items-start gap-2 text-sm text-slate-600">
            <Check className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
            {f}
          </li>
        ))}
      </ul>

      {isCurrent ? (
        <div className="w-full text-center text-sm font-semibold text-amber-700 py-2.5 bg-amber-100 rounded-xl">
          Your current plan
        </div>
      ) : (
        <button
          onClick={() => onSelect(plan.id)}
          className="w-full flex items-center justify-center gap-2 bg-navy-800 text-white font-semibold text-sm py-2.5 rounded-xl hover:bg-navy-700 active:scale-[0.97] transition-all"
        >
          {isUpgrade ? 'Upgrade to' : 'Switch to'} {plan.name}
          <ArrowRight className="w-4 h-4" />
        </button>
      )}
    </div>
  )
}

export default function BillingPage() {
  const [currentPlan, setCurrentPlan] = useState('growth')
  const [showUpgradeModal, setShowUpgradeModal] = useState(null)
  const [isOnTrial, setIsOnTrial] = useState(false)
  const [trialEndsAt, setTrialEndsAt] = useState(null)
  const [invoices, setInvoices] = useState([])
  const [loadingBilling, setLoadingBilling] = useState(true)
  const [portalLoading, setPortalLoading] = useState(false)

  useEffect(() => {
    const load = async () => {
      try {
        const [subRes, invoiceRes] = await Promise.all([
          billingApi.getSubscription(),
          billingApi.getInvoices(),
        ])
        const sub = subRes.data
        if (sub?.subscription?.plan) setCurrentPlan(sub.subscription.plan)
        if (sub?.on_trial) setIsOnTrial(true)
        if (sub?.trial_ends_at) setTrialEndsAt(new Date(sub.trial_ends_at))
        setInvoices(invoiceRes.data?.invoices ?? [])
      } catch {
        // silently fall back to defaults
      } finally {
        setLoadingBilling(false)
      }
    }
    load()
  }, [])

  const handlePortal = async () => {
    setPortalLoading(true)
    try {
      const res = await billingApi.createPortal()
      if (res.data?.url) window.location.href = res.data.url
    } catch {
      // ignore
    } finally {
      setPortalLoading(false)
    }
  }

  const daysLeft = trialEndsAt
    ? Math.ceil((trialEndsAt - new Date()) / (1000 * 60 * 60 * 24))
    : 0

  return (
    <div className="max-w-4xl mx-auto animate-fade-in-up space-y-8">

      {/* Header */}
      <div className="flex items-center gap-3">
        <CreditCard className="w-5 h-5 text-amber-500" />
        <h1 className="font-display font-black text-2xl text-navy-800">Billing</h1>
      </div>

      {/* Trial banner */}
      {isOnTrial && daysLeft > 0 && (
        <div className="bg-gradient-to-r from-amber-500 to-honey-400 rounded-2xl p-5 text-navy-800">
          <div className="flex items-start gap-4">
            <div className="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
              <Star className="w-5 h-5 text-navy-800" fill="currentColor" />
            </div>
            <div>
              <h2 className="font-display font-bold text-lg mb-1">
                {daysLeft} day{daysLeft !== 1 ? 's' : ''} left on your free trial
              </h2>
              <p className="text-sm opacity-80 mb-3">
                You&apos;re on the Growth plan trial. Add a payment method to continue after your trial ends.
              </p>
              <button
                onClick={handlePortal}
                disabled={portalLoading}
                className="inline-flex items-center gap-2 bg-navy-800 text-white font-bold text-sm px-5 py-2.5 rounded-xl hover:bg-navy-700 transition-all disabled:opacity-60"
              >
                {portalLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : <CreditCard className="w-4 h-4" />}
                Add payment method
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Plans */}
      <div>
        <h2 className="font-display font-bold text-lg text-navy-800 mb-4">Choose your plan</h2>
        <div className="grid sm:grid-cols-3 gap-4 sm:gap-5">
          {PLANS.map((plan) => (
            <PlanCard
              key={plan.id}
              plan={plan}
              currentPlan={currentPlan}
              onSelect={setShowUpgradeModal}
            />
          ))}
        </div>
        <p className="text-xs text-slate-400 mt-4 text-center">
          All prices exclude VAT. UK VAT (20%) applied at checkout. TikTok video add-on: +£15/month on Starter and Growth.
          Google Business Profile posting is included on all plans.
        </p>
      </div>

      {/* TikTok add-on */}
      {currentPlan !== 'pro' && (
        <div className="bg-navy-800 rounded-2xl p-5 flex items-start gap-4 text-white">
          <div className="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center flex-shrink-0 text-lg">
            🎵
          </div>
          <div className="flex-1">
            <h3 className="font-display font-bold text-base mb-1">TikTok video add-on — £15/month</h3>
            <p className="text-white/60 text-sm leading-relaxed mb-3">
              Add AI-generated TikTok video content to your plan. Included free on Pro.
            </p>
            <button className="inline-flex items-center gap-2 bg-amber-500 text-white font-semibold text-sm px-4 py-2 rounded-xl hover:bg-amber-600 transition-all">
              Add TikTok
              <ArrowRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}

      {/* Invoice history */}
      <div>
        <div className="flex items-center gap-3 mb-4">
          <FileText className="w-4 h-4 text-slate-400" />
          <h2 className="font-display font-bold text-lg text-navy-800">Invoice history</h2>
        </div>
        <div className="bg-white rounded-2xl border border-cream-300 overflow-hidden" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.05)' }}>
          {loadingBilling ? (
            <div className="text-center py-10 text-slate-400 text-sm flex items-center justify-center gap-2">
              <Loader2 className="w-4 h-4 animate-spin" /> Loading invoices...
            </div>
          ) : invoices.length === 0 ? (
            <div className="text-center py-10 text-slate-400 text-sm">No invoices yet.</div>
          ) : (
            <table className="w-full">
              <thead>
                <tr className="border-b border-cream-300">
                  <th className="text-left px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Invoice</th>
                  <th className="text-left px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Date</th>
                  <th className="text-left px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Amount</th>
                  <th className="text-left px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                </tr>
              </thead>
              <tbody>
                {invoices.map((inv) => (
                  <tr key={inv.id} className="border-b border-cream-300 last:border-0 hover:bg-cream-200/50 transition-colors">
                    <td className="px-5 py-3 text-sm font-mono text-navy-800">{inv.id}</td>
                    <td className="px-5 py-3 text-sm text-slate-600">{inv.date}</td>
                    <td className="px-5 py-3 text-sm font-semibold text-navy-800">{inv.total || inv.amount}</td>
                    <td className="px-5 py-3">
                      <span className="badge bg-green-100 text-green-700 text-xs">{inv.status || 'Paid'}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {/* Danger zone */}
      <div className="border border-red-200 rounded-2xl p-5">
        <div className="flex items-start gap-3">
          <AlertTriangle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
          <div>
            <h3 className="font-display font-bold text-base text-red-700 mb-1">Cancel subscription</h3>
            <p className="text-sm text-slate-600 mb-3">
              Cancelling will stop all posts at the end of your current billing period. Your data is kept for 30 days.
            </p>
            <button
              onClick={handlePortal}
              disabled={portalLoading}
              className="text-sm font-semibold text-red-600 hover:text-red-700 underline transition-colors disabled:opacity-60"
            >
              {portalLoading ? 'Opening portal...' : 'Manage subscription via Stripe portal'}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
