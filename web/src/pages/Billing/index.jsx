import { useState, useEffect, useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  Check, CreditCard, Star, ArrowRight, FileText, AlertTriangle, Loader2,
  Image as ImageIcon, Share2, CalendarDays, MapPin, PauseCircle, CheckCircle2,
} from 'lucide-react'
import { billingApi } from '../../lib/api.js'
import {
  PLANS as PLAN_COPY, PLAN_NAMES, PLATFORM_NAMES, EVERY_PLAN, VAT_SUFFIX,
  formatPounds, annualPerMonth,
} from '../../lib/plans.js'
import IntervalToggle from '../../components/ui/IntervalToggle.jsx'

const pounds = (pence) => (pence == null ? null : pence / 100)

function listPlatforms(ids) {
  const names = ids.map((id) => PLATFORM_NAMES[id] ?? id)
  if (names.length <= 1) return names.join('')
  return `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`
}

// Card bullets are built from the limits the API enforces, so the Billing page
// cannot promise something the backend would refuse.
function planFeatures(plan, extraLocation) {
  const l = plan.limits
  const features = []

  let locations = l.locations === 1 ? '1 location' : `${l.locations} locations`
  if (l.extra_locations && extraLocation?.available) {
    locations += `, then ${formatPounds(pounds(extraLocation.price))}/month each`
  }
  features.push(locations)

  features.push(
    l.platform_limit
      ? `Any ${l.platform_limit} of ${listPlatforms(l.platforms)}`
      : l.locations > 1
        ? 'Every platform, for every location'
        : listPlatforms(l.platforms)
  )

  const gbpCap = l.posts_per_week_by_platform?.google_business_profile
  features.push(
    `Up to ${l.posts_per_week} posts a week on each` +
      (gbpCap && gbpCap < l.posts_per_week ? ` (Google up to ${gbpCap})` : '')
  )

  features.push(
    `${l.ai_images_per_month} AI images a month` + (l.locations > 1 ? ', shared across locations' : '')
  )
  features.push(l.analytics_days >= 365 ? '12 months of analytics' : `${l.analytics_days} days of analytics`)

  return features
}

function UsageBar({ used, limit }) {
  const pct = limit > 0 ? Math.min(100, Math.round((used / limit) * 100)) : 100
  return (
    <div className="h-2 rounded-full bg-cream-300 overflow-hidden" aria-hidden="true">
      <div
        className={`h-full rounded-full ${pct >= 100 ? 'bg-amber-600' : 'bg-amber-500'}`}
        style={{ width: `${pct}%` }}
      />
    </div>
  )
}

function UsageRow({ icon: Icon, label, value, detail, used, limit }) {
  return (
    <div className="py-3">
      <div className="flex items-center justify-between gap-3 mb-1.5">
        <span className="flex items-center gap-2 text-sm font-semibold text-navy-800">
          <Icon className="w-4 h-4 text-amber-500 flex-shrink-0" />
          {label}
        </span>
        <span className="text-sm font-semibold text-navy-800 whitespace-nowrap">{value}</span>
      </div>
      {limit != null && <UsageBar used={used} limit={limit} />}
      {detail && <p className="text-xs text-slate-500 mt-1.5">{detail}</p>}
    </div>
  )
}

function CurrentPlanCard({ data, onUpgrade }) {
  const { account, current_plan: plan, current_interval: interval, is_on_trial: onTrial } = data
  if (!account) return null

  const e = account.entitlements
  const u = account.usage
  const upgrade = account.upgrade_plan
  const imagePeriod = e.ai_images_period === 'trial' ? 'during your trial' : 'this month'

  const platformDetail = u.platforms_paused.length > 0
    ? `${listPlatforms(u.platforms_paused)} ${u.platforms_paused.length === 1 ? 'is' : 'are'} connected but paused on this plan. Disconnect one on the Platforms page to swap which ones post.`
    : e.platform_limit
      ? `Your plan covers any ${e.platform_limit}. X is on Growth and above.`
      : null

  return (
    <div className="bg-white rounded-2xl border border-cream-300 p-5 sm:p-6" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.05)' }}>
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-2">
        <div>
          <p className="text-xs font-bold text-slate-500 uppercase tracking-wide">Your plan</p>
          <h2 className="font-display font-black text-xl text-navy-800">
            {e.active ? (PLAN_NAMES[plan] ?? e.plan_name) : 'No plan'}
            {onTrial && e.active && <span className="text-sm font-semibold text-amber-600 ml-2">free trial</span>}
            {interval === 'annual' && <span className="text-sm font-semibold text-slate-500 ml-2">billed yearly</span>}
          </h2>
          {plan === 'pro' && (
            <p className="text-xs text-slate-500 mt-1">
              A legacy plan: you keep your original price with everything in Agency. Switching plan gives this price up.
            </p>
          )}
        </div>
        {upgrade && e.active && (
          <button
            onClick={() => onUpgrade(upgrade)}
            className="inline-flex items-center justify-center gap-2 bg-amber-500 text-white font-semibold text-sm px-4 py-2.5 rounded-xl hover:bg-amber-600 transition-all"
          >
            See {PLAN_NAMES[upgrade]} <ArrowRight className="w-4 h-4" />
          </button>
        )}
      </div>

      {e.active ? (
        <div className="divide-y divide-cream-300">
          <UsageRow
            icon={ImageIcon}
            label="AI images"
            value={`${u.ai_images_used} of ${e.ai_images}`}
            used={u.ai_images_used}
            limit={e.ai_images}
            detail={u.ai_images_remaining === 0
              ? `You have used every AI image ${imagePeriod}. Posts carry on as normal without one until the allowance resets.`
              : `${u.ai_images_remaining} left ${imagePeriod}.`}
          />
          {e.twitter_post_cap != null && (
            <UsageRow
              icon={Share2}
              label="X posts on your trial"
              value={`${u.twitter_posts_used ?? 0} of ${e.twitter_post_cap}`}
              used={u.twitter_posts_used ?? 0}
              limit={e.twitter_post_cap}
              detail="X charges for every post, so trials include a set number. Growth and Agency include X every week."
            />
          )}
          <UsageRow
            icon={Share2}
            label="Platforms posting"
            value={e.platform_limit ? `${u.platforms_active.length} of ${e.platform_limit}` : `${u.platforms_active.length} connected`}
            used={u.platforms_active.length}
            limit={e.platform_limit}
            detail={platformDetail}
          />
          <UsageRow
            icon={CalendarDays}
            label="Posts a week, per platform"
            value={`Up to ${e.posts_per_week}`}
            detail="Change how often each platform posts in Settings."
          />
          <UsageRow
            icon={MapPin}
            label="Locations"
            value={`${u.locations} of ${u.location_limit}`}
            used={u.locations}
            limit={u.location_limit}
          />
        </div>
      ) : (
        <p className="text-sm text-slate-600 mt-2">
          Posting is paused until you choose a plan. Your posts, settings and connections are all still here.
        </p>
      )}
    </div>
  )
}

function PlanCard({ plan, interval, isCurrent, extraLocation, onSelect, busy }) {
  const copy = PLAN_COPY.find((p) => p.id === plan.id)
  const price = pounds(plan[interval].price)
  const isAnnual = interval === 'annual'

  return (
    <div
      id={`plan-${plan.id}`}
      className={`relative flex flex-col rounded-2xl p-6 border-2 transition-all duration-200 ${
        isCurrent
          ? 'border-amber-500 bg-amber-50'
          : plan.is_popular
            ? 'border-navy-200 bg-white hover:border-amber-300'
            : 'border-slate-200 bg-white hover:border-slate-300'
      }`}
      style={{ boxShadow: isCurrent ? '0 0 0 4px rgb(224 123 48 / 0.1)' : '0 2px 8px rgb(30 45 74 / 0.05)' }}
    >
      {plan.is_popular && !isCurrent && (
        <div className="absolute -top-3 left-1/2 -translate-x-1/2">
          <span className="inline-flex items-center gap-1 bg-navy-800 text-white text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap">
            <Star className="w-3 h-3" fill="currentColor" />
            Most popular
          </span>
        </div>
      )}
      {isCurrent && (
        <div className="absolute -top-3 left-1/2 -translate-x-1/2">
          <span className="inline-flex items-center gap-1 bg-amber-500 text-white text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap">
            <Check className="w-3 h-3" />
            Current plan
          </span>
        </div>
      )}

      <div className="mb-5">
        <h3 className="font-display font-bold text-lg text-navy-800 mb-1">{plan.name}</h3>
        <div className="flex items-baseline gap-1 flex-wrap">
          <span className="font-display font-black text-3xl text-navy-800">{formatPounds(price)}</span>
          <span className="text-sm text-slate-400">/{isAnnual ? 'year' : 'month'}{VAT_SUFFIX}</span>
        </div>
        {isAnnual && (
          <p className="text-xs text-slate-500 mt-0.5">Works out at {formatPounds(annualPerMonth(price))}/month</p>
        )}
        <p className="text-sm text-slate-500 mt-2">{copy?.tagline ?? plan.tagline}</p>
      </div>

      <ul className="space-y-2 mb-6 flex-1">
        {planFeatures(plan, extraLocation).map((f) => (
          <li key={f} className="flex items-start gap-2 text-sm text-slate-600">
            <Check className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
            {f}
          </li>
        ))}
      </ul>

      {isCurrent ? (
        <div className="w-full text-center text-sm font-semibold text-amber-700 py-2.5 bg-amber-100 rounded-xl">
          Your current plan
        </div>
      ) : plan.offered ? (
        <button
          onClick={() => onSelect(plan)}
          disabled={busy}
          className="w-full flex items-center justify-center gap-2 bg-navy-800 text-white font-semibold text-sm py-2.5 rounded-xl hover:bg-navy-700 active:scale-[0.97] transition-all disabled:opacity-60"
        >
          {busy ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
          Choose {plan.name}
          {!busy && <ArrowRight className="w-4 h-4" />}
        </button>
      ) : null}
    </div>
  )
}

export default function BillingPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [data, setData] = useState(null)
  const [comp, setComp] = useState(null)
  const [invoices, setInvoices] = useState([])
  const [loadingBilling, setLoadingBilling] = useState(true)
  const [loadError, setLoadError] = useState(false)
  const [portalLoading, setPortalLoading] = useState(false)
  const [interval, setBillingInterval] = useState('monthly')
  const [confirming, setConfirming] = useState(null) // plan awaiting "yes, switch"
  const [busyPlan, setBusyPlan] = useState(null)
  const [actionError, setActionError] = useState(null)
  const [actionMessage, setActionMessage] = useState(null)

  const checkoutResult = searchParams.get('checkout')

  const load = useCallback(async () => {
    try {
      const [plansRes, subRes, invoiceRes] = await Promise.all([
        billingApi.getPlans(),
        billingApi.getSubscription(),
        billingApi.getInvoices().catch(() => ({ data: { invoices: [] } })),
      ])
      setData(plansRes.data)
      if (plansRes.data?.current_interval) setBillingInterval(plansRes.data.current_interval)
      const sub = subRes.data
      setComp(sub?.comped ? { plan: sub.comped_plan, until: sub.comped_until } : null)
      setInvoices(invoiceRes.data?.invoices ?? [])
      setLoadError(false)
    } catch {
      setLoadError(true)
    } finally {
      setLoadingBilling(false)
    }
  }, [])

  useEffect(() => { load() }, [load])

  // Clear ?checkout= once shown, so a refresh does not repeat the banner.
  useEffect(() => {
    if (!checkoutResult) return
    const t = setTimeout(() => setSearchParams({}, { replace: true }), 8000)
    return () => clearTimeout(t)
  }, [checkoutResult, setSearchParams])

  const handlePortal = async () => {
    setPortalLoading(true)
    try {
      const res = await billingApi.createPortal()
      if (res.data?.url) window.location.href = res.data.url
    } catch {
      setActionError('We could not open the billing portal just now. Please try again.')
    } finally {
      setPortalLoading(false)
    }
  }

  const choosePlan = async (plan) => {
    setActionError(null)
    setActionMessage(null)
    setBusyPlan(plan.id)
    try {
      const res = await billingApi.createCheckout(plan.id, interval)
      if (res.data?.url) {
        window.location.href = res.data.url
        return
      }
      setActionMessage(res.data?.message ?? `You are now on the ${plan.name} plan.`)
      setConfirming(null)
      await load()
    } catch (err) {
      setActionError(err.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
      setBusyPlan(null)
    }
  }

  // Existing subscribers are charged the difference straight away, so they
  // confirm first. Anyone else goes to Stripe Checkout, which is its own
  // confirmation step.
  const handleSelect = (plan) => {
    if (data?.subscribed) {
      setConfirming(plan)
    } else {
      choosePlan(plan)
    }
  }

  const scrollToPlan = (planId) => {
    document.getElementById(`plan-${planId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }

  const trialEndsAt = data?.trial_ends_at ? new Date(data.trial_ends_at) : null
  const daysLeft = trialEndsAt ? Math.ceil((trialEndsAt - new Date()) / (1000 * 60 * 60 * 24)) : 0
  const plans = data?.plans ?? []
  const annualAvailable = plans.some((p) => p.offered && p.annual.available)
  const effectiveInterval = annualAvailable ? interval : 'monthly'
  // A plan whose Stripe price is not set up for this interval is hidden rather
  // than offered and then refused. The current plan always shows.
  const visiblePlans = plans.filter((p) => p.id === data?.current_plan || (p.offered && p[effectiveInterval].available))
  const isCurrent = (p) => p.id === data?.current_plan && (data?.subscribed ? (data?.current_interval ?? 'monthly') === effectiveInterval : true) && !data?.is_on_trial

  return (
    <div className="max-w-4xl mx-auto animate-fade-in-up space-y-8">

      <div className="flex items-center gap-3">
        <CreditCard className="w-5 h-5 text-amber-500" />
        <h1 className="font-display font-black text-2xl text-navy-800">Billing</h1>
      </div>

      {checkoutResult === 'success' && (
        <div className="flex items-start gap-3 bg-green-50 border border-green-200 rounded-2xl p-4">
          <CheckCircle2 className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
          <p className="text-sm text-green-800">
            <strong>Thank you, you are all set.</strong> It can take a minute for your new plan to show here.
          </p>
        </div>
      )}
      {checkoutResult === 'cancelled' && (
        <div className="flex items-start gap-3 bg-slate-50 border border-slate-200 rounded-2xl p-4">
          <AlertTriangle className="w-5 h-5 text-slate-500 flex-shrink-0 mt-0.5" />
          <p className="text-sm text-slate-700">Checkout was cancelled and nothing was charged. Choose a plan whenever you are ready.</p>
        </div>
      )}
      {actionMessage && (
        <div className="flex items-start gap-3 bg-green-50 border border-green-200 rounded-2xl p-4">
          <CheckCircle2 className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
          <p className="text-sm text-green-800">{actionMessage}</p>
        </div>
      )}
      {actionError && (
        <div role="alert" className="flex items-start gap-3 bg-red-50 border border-red-200 rounded-2xl p-4">
          <AlertTriangle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
          <p className="text-sm text-red-700">{actionError}</p>
        </div>
      )}

      {/* Comped account. Takes precedence over the trial banner: the trial date is
          still set on these accounts, and showing a countdown to someone whose
          access does not expire is alarming and wrong. */}
      {comp && (
        <div className="bg-gradient-to-r from-green-500 to-emerald-400 rounded-2xl p-5 text-white">
          <div className="flex items-start gap-4">
            <div className="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
              <Star className="w-5 h-5 text-white" fill="currentColor" />
            </div>
            <div>
              <h2 className="font-display font-bold text-lg mb-1">Your account is on us</h2>
              <p className="text-sm opacity-90">
                You have full {comp.plan ? `${PLAN_NAMES[comp.plan] ?? comp.plan} plan ` : ''}access
                {comp.until
                  ? ` until ${new Date(comp.until).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}.`
                  : ' with no end date and nothing to pay.'}
                {' '}You do not need a payment method.
              </p>
            </div>
          </div>
        </div>
      )}

      {!comp && data?.is_on_trial && daysLeft > 0 && (
        <div className="bg-gradient-to-r from-amber-500 to-honey-400 rounded-2xl p-5 text-navy-800">
          <div className="flex items-start gap-4">
            <div className="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
              <Star className="w-5 h-5 text-navy-800" fill="currentColor" />
            </div>
            <div>
              <h2 className="font-display font-bold text-lg mb-1">
                {daysLeft} day{daysLeft !== 1 ? 's' : ''} left on your free trial
              </h2>
              <p className="text-sm opacity-80">
                You are trying everything in Growth. Choose a plan any time: you will not be charged until your
                trial ends. If you do not choose one, posting pauses and nothing is deleted.
              </p>
            </div>
          </div>
        </div>
      )}

      {!comp && data && data.current_plan === 'none' && (
        <div className="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-2xl p-4">
          <PauseCircle className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
          <p className="text-sm text-amber-800">
            <strong>Your posts are paused.</strong> Choose a plan below to start posting again. Everything you set up
            is still here.
          </p>
        </div>
      )}

      {loadingBilling ? (
        <div className="bg-white rounded-2xl border border-cream-300 h-48 animate-pulse" />
      ) : loadError ? (
        <div className="bg-white rounded-2xl border border-cream-300 p-6 text-sm text-slate-600">
          We could not load your plan just now.{' '}
          <button onClick={load} className="font-semibold text-amber-600 underline">Try again</button>
        </div>
      ) : (
        <CurrentPlanCard data={data} onUpgrade={scrollToPlan} />
      )}

      {/* Plans */}
      {!loadingBilling && !loadError && visiblePlans.length > 0 && (
        <div>
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <h2 className="font-display font-bold text-lg text-navy-800">
              {data?.subscribed ? 'Change plan' : 'Choose your plan'}
            </h2>
            {annualAvailable && <IntervalToggle interval={effectiveInterval} onChange={setBillingInterval} />}
          </div>

          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
            {visiblePlans.map((plan) => (
              <PlanCard
                key={plan.id}
                plan={plan}
                interval={plan[effectiveInterval].available || plan.id !== data?.current_plan ? effectiveInterval : 'monthly'}
                isCurrent={isCurrent(plan)}
                extraLocation={data?.extra_location}
                onSelect={handleSelect}
                busy={busyPlan === plan.id}
              />
            ))}
          </div>

          <div className="mt-5">
            <p className="text-xs font-bold text-slate-500 uppercase tracking-wide text-center mb-2">On every plan</p>
            <ul className="flex flex-col sm:flex-row sm:flex-wrap sm:justify-center gap-x-5 gap-y-1.5">
              {EVERY_PLAN.map((f) => (
                <li key={f} className="inline-flex items-center gap-2 text-sm text-slate-600">
                  <Check className="w-4 h-4 text-amber-500 flex-shrink-0" />
                  {f}
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}

      {/* Confirm a switch for an existing subscriber */}
      {confirming && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-navy-800/40 p-4" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
          <div className="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl">
            <h3 id="confirm-title" className="font-display font-bold text-lg text-navy-800 mb-2">
              Switch to {confirming.name}?
            </h3>
            <p className="text-sm text-slate-600 mb-5">
              {confirming.name} is {formatPounds(pounds(confirming[effectiveInterval].price))}/{effectiveInterval === 'annual' ? 'year' : 'month'}{VAT_SUFFIX}.
              The change applies straight away, and Stripe adjusts your next invoice for the part of the period already paid.
            </p>
            <div className="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
              <button
                onClick={() => setConfirming(null)}
                className="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100"
              >
                Keep my plan
              </button>
              <button
                onClick={() => choosePlan(confirming)}
                disabled={busyPlan != null}
                className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-navy-800 text-white hover:bg-navy-700 disabled:opacity-60"
              >
                {busyPlan && <Loader2 className="w-4 h-4 animate-spin" />}
                Yes, switch to {confirming.name}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Invoice history */}
      <div>
        <div className="flex items-center gap-3 mb-4">
          <FileText className="w-4 h-4 text-slate-400" />
          <h2 className="font-display font-bold text-lg text-navy-800">Invoice history</h2>
        </div>
        <div className="bg-white rounded-2xl border border-cream-300 overflow-x-auto" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.05)' }}>
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

      {/* Payment details and cancelling live in Stripe's portal */}
      <div className="border border-cream-300 rounded-2xl p-5">
        <div className="flex items-start gap-3">
          <AlertTriangle className="w-5 h-5 text-slate-400 flex-shrink-0 mt-0.5" />
          <div>
            <h3 className="font-display font-bold text-base text-navy-800 mb-1">Payment details and cancelling</h3>
            <p className="text-sm text-slate-600 mb-3">
              Update your card, download receipts or cancel in the Stripe billing portal. If you cancel, posting
              pauses at the end of your current billing period and nothing is deleted.
            </p>
            <button
              onClick={handlePortal}
              disabled={portalLoading}
              className="text-sm font-semibold text-navy-800 hover:text-amber-600 underline transition-colors disabled:opacity-60"
            >
              {portalLoading ? 'Opening portal...' : 'Open the billing portal'}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
