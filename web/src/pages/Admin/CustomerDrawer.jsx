import { useEffect, useState } from 'react'
import {
  X, Gift, Clock, LogIn, Loader2, AlertTriangle, Globe, Mail,
  Building2, Zap, Link2, CheckCircle2, XCircle, Copy, Check,
} from 'lucide-react'
import { adminApi } from '../../lib/api.js'
import {
  Pill, StatusPill, PostStatusPill, EmptyState, SkeletonRows,
  formatDate, formatDateTime, timeAgo, usd, gbp, platformLabel,
} from './components.jsx'

// Plan keys from api/config/plans.php. Pro is legacy but can still be comped.
const PLANS = [
  { id: 'local', label: 'Local (£15/mo)' },
  { id: 'growth', label: 'Growth (£29/mo)' },
  { id: 'agency', label: 'Agency (£59/mo)' },
  { id: 'pro', label: 'Pro, legacy (£69/mo)' },
]

function Field({ label, children }) {
  return (
    <div>
      <dt className="text-2xs font-bold uppercase tracking-wide text-slate-500 mb-1">{label}</dt>
      <dd className="text-sm text-navy-900">{children ?? '—'}</dd>
    </div>
  )
}

function ActionButton({ onClick, disabled, busy, icon: Icon, children, tone = 'default' }) {
  const tones = {
    default: 'bg-white hover:bg-cloud text-navy-900 border-line',
    primary: 'bg-cobalt-600 hover:bg-cobalt-700 text-white border-cobalt-600',
    danger: 'bg-danger-soft hover:bg-red-100 text-danger-text border-red-200',
  }
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled || busy}
      className={`min-h-[44px] inline-flex items-center justify-center gap-2 rounded-full border px-3 py-2 text-xs font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed ${tones[tone]}`}
    >
      {busy ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : Icon && <Icon className="w-3.5 h-3.5" />}
      {children}
    </button>
  )
}

export default function CustomerDrawer({ customerId, onClose, onChanged }) {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(null)
  const [notice, setNotice] = useState(null)

  // Comp form
  const [compPlan, setCompPlan] = useState('growth')
  const [compUntil, setCompUntil] = useState('')
  const [compNote, setCompNote] = useState('')
  const [trialDays, setTrialDays] = useState(14)
  const [copied, setCopied] = useState(false)

  const load = async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await adminApi.getCustomer(customerId)
      setData(res.data)
      setCompPlan(res.data?.customer?.comped_plan ?? res.data?.customer?.plan ?? 'growth')
      setCompNote(res.data?.customer?.comp_note ?? '')
      setCompUntil(res.data?.customer?.comped_until?.slice(0, 10) ?? '')
    } catch (e) {
      setError(e.response?.data?.message ?? 'Could not load this customer.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (customerId) load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [customerId])

  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose() }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  const run = async (key, fn, successFallback) => {
    setBusy(key)
    setNotice(null)
    try {
      const res = await fn()
      setNotice({ type: 'success', message: res?.data?.message ?? successFallback })
      await load()
      onChanged?.()
      return res
    } catch (e) {
      setNotice({
        type: 'error',
        message: e.response?.data?.message ?? 'That did not work. Try again.',
      })
      return null
    } finally {
      setBusy(null)
    }
  }

  const handleComp = () => run(
    'comp',
    () => adminApi.compCustomer(customerId, {
      plan: compPlan,
      until: compUntil || null,
      note: compNote || null,
    }),
    'Customer is now free.',
  )

  const handleUncomp = () => run(
    'uncomp',
    () => adminApi.uncompCustomer(customerId),
    'Free access removed.',
  )

  const handleExtendTrial = () => run(
    'trial',
    () => adminApi.extendTrial(customerId, Number(trialDays)),
    'Trial extended.',
  )

  const handleImpersonate = async () => {
    const res = await run('impersonate', () => adminApi.impersonate(customerId), 'Impersonation token created.')
    const token = res?.data?.token
    if (token) {
      try {
        await navigator.clipboard.writeText(token)
        setCopied(true)
        setTimeout(() => setCopied(false), 3000)
      } catch {
        setNotice({ type: 'success', message: `Token: ${token}` })
      }
    }
  }

  const c = data?.customer
  const business = data?.business
  const isComped = Boolean(c?.comped_plan)

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <button
        type="button"
        aria-label="Close customer details"
        onClick={onClose}
        className="absolute inset-0 bg-navy-900/40 backdrop-blur-sm"
      />

      <aside className="relative w-full max-w-xl bg-cloud border-l border-line h-full overflow-y-auto">
        <header className="sticky top-0 z-10 bg-white/95 backdrop-blur border-b border-line px-5 py-4 flex items-start justify-between gap-4">
          <div className="min-w-0">
            {loading ? (
              <div className="h-5 w-40 bg-line rounded animate-pulse" />
            ) : (
              <>
                <h2 className="font-display font-extrabold text-lg text-navy-900 truncate">
                  {business?.name ?? c?.name ?? 'Customer'}
                </h2>
                <p className="text-xs text-slate-500 truncate">{c?.email}</p>
              </>
            )}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="w-11 h-11 inline-flex items-center justify-center rounded-full text-slate-500 hover:text-navy-900 hover:bg-cloud transition-colors shrink-0"
          >
            <X className="w-4 h-4" />
          </button>
        </header>

        {loading ? (
          <div className="p-5"><SkeletonRows rows={6} /></div>
        ) : error ? (
          <div className="p-5">
            <div className="rounded-xl border border-red-200 bg-danger-soft p-4 text-sm text-danger-text">
              {error}
            </div>
          </div>
        ) : (
          <div className="p-5 space-y-6">

            {notice && (
              <div className={`rounded-xl border p-3 text-xs font-semibold ${notice.type === 'success'
                ? 'border-green-200 bg-success-soft text-success-text'
                : 'border-red-200 bg-danger-soft text-danger-text'}`}>
                {notice.message}
              </div>
            )}

            {copied && (
              <div className="rounded-xl border border-green-200 bg-success-soft p-3 text-xs font-semibold text-success-text flex items-center gap-2">
                <Check className="w-3.5 h-3.5" />
                Impersonation token copied to clipboard. Valid for 2 hours.
              </div>
            )}

            {/* Status summary */}
            <div className="flex flex-wrap items-center gap-2">
              <StatusPill status={c?.status} />
              <Pill tone="slate">{c?.plan_label}</Pill>
              {c?.mrr > 0 && <Pill tone="green">{gbp(c.mrr, { decimals: 0 })}/mo</Pill>}
              {c?.is_admin && <Pill tone="blue">Admin</Pill>}
              {business && !business.onboarding_complete && <Pill tone="amber">Onboarding incomplete</Pill>}
            </div>

            {/* Account */}
            <section className="rounded-2xl border border-line bg-white p-4">
              <dl className="grid grid-cols-2 gap-4">
                <Field label="Signed up">{formatDate(c?.created_at)}</Field>
                <Field label="Last seen">{c?.last_seen_at ? timeAgo(c.last_seen_at) : 'Never'}</Field>
                <Field label="Trial ends">{c?.trial_ends_at ? formatDate(c.trial_ends_at) : '—'}</Field>
                <Field label="Email verified">
                  {c?.email_verified_at ? formatDate(c.email_verified_at) : 'Not verified'}
                </Field>
                <Field label="Stripe customer">{c?.stripe_customer ? 'Yes' : 'No'}</Field>
                <Field label="AI cost (month)">{usd(data?.ai_cost?.this_month_usd)}</Field>
              </dl>
            </section>

            {/* Comp status */}
            {isComped && (
              <div className="rounded-2xl border border-navy-100 bg-navy-50 p-4">
                <div className="flex items-center gap-2 mb-2">
                  <Gift className="w-4 h-4 text-navy-700" />
                  <span className="text-sm font-bold text-navy-900">
                    Free {c.comped_plan} plan
                  </span>
                </div>
                <p className="text-xs text-slate-500">
                  Granted {formatDate(c.comped_at)}
                  {c.comped_by ? ` by ${c.comped_by}` : ''}
                  {c.comped_until ? `, expires ${formatDate(c.comped_until)}` : ', no end date'}.
                </p>
                {c.comp_note && <p className="text-xs text-slate-500 mt-1 italic">"{c.comp_note}"</p>}
              </div>
            )}

            {/* Actions */}
            <section className="rounded-2xl border border-line bg-white p-4 space-y-4">
              <h3 className="font-display font-bold text-sm text-navy-900">Actions</h3>

              <div className="space-y-3">
                <label className="block">
                  <span className="text-2xs font-bold uppercase tracking-wide text-slate-500">Free plan</span>
                  <select
                    value={compPlan}
                    onChange={(e) => setCompPlan(e.target.value)}
                    className="mt-1 w-full rounded-lg bg-white border-[1.5px] border-line px-3 py-2 min-h-[44px] text-sm text-navy-900 focus:border-cobalt-600 focus:ring-[3px] focus:ring-cobalt-100 focus:outline-none"
                  >
                    {PLANS.map((p) => (
                      <option key={p.id} value={p.id}>{p.label}</option>
                    ))}
                  </select>
                </label>

                <div className="grid grid-cols-2 gap-3">
                  <label className="block">
                    <span className="text-2xs font-bold uppercase tracking-wide text-slate-500">Until (optional)</span>
                    <input
                      type="date"
                      value={compUntil}
                      onChange={(e) => setCompUntil(e.target.value)}
                      className="mt-1 w-full rounded-lg bg-white border-[1.5px] border-line px-3 py-2 min-h-[44px] text-sm text-navy-900 focus:border-cobalt-600 focus:ring-[3px] focus:ring-cobalt-100 focus:outline-none"
                    />
                  </label>
                  <label className="block">
                    <span className="text-2xs font-bold uppercase tracking-wide text-slate-500">Note</span>
                    <input
                      type="text"
                      value={compNote}
                      onChange={(e) => setCompNote(e.target.value)}
                      placeholder="Why is this free?"
                      className="mt-1 w-full rounded-lg bg-white border-[1.5px] border-line px-3 py-2 min-h-[44px] text-sm text-navy-900 placeholder:text-slate-400 focus:border-cobalt-600 focus:ring-[3px] focus:ring-cobalt-100 focus:outline-none"
                    />
                  </label>
                </div>

                <div className="flex flex-wrap gap-2">
                  <ActionButton
                    onClick={handleComp}
                    busy={busy === 'comp'}
                    icon={Gift}
                    tone="primary"
                  >
                    {isComped ? 'Update free access' : 'Make free'}
                  </ActionButton>

                  {isComped && (
                    <ActionButton onClick={handleUncomp} busy={busy === 'uncomp'} icon={XCircle} tone="danger">
                      Remove free access
                    </ActionButton>
                  )}
                </div>
              </div>

              <div className="border-t border-line pt-4 flex flex-wrap items-end gap-2">
                <label className="block w-24">
                  <span className="text-2xs font-bold uppercase tracking-wide text-slate-500">Trial days</span>
                  <input
                    type="number"
                    min="1"
                    max="365"
                    value={trialDays}
                    onChange={(e) => setTrialDays(e.target.value)}
                    className="mt-1 w-full rounded-lg bg-white border-[1.5px] border-line px-3 py-2 min-h-[44px] text-sm text-navy-900 focus:border-cobalt-600 focus:ring-[3px] focus:ring-cobalt-100 focus:outline-none"
                  />
                </label>
                <ActionButton onClick={handleExtendTrial} busy={busy === 'trial'} icon={Clock}>
                  Extend trial
                </ActionButton>
                <ActionButton
                  onClick={handleImpersonate}
                  busy={busy === 'impersonate'}
                  icon={copied ? Check : LogIn}
                  disabled={c?.is_admin}
                >
                  {copied ? 'Token copied' : 'Copy login token'}
                </ActionButton>
              </div>
            </section>

            {/* Business */}
            <section className="rounded-2xl border border-line bg-white p-4">
              <h3 className="font-display font-bold text-sm text-navy-900 mb-3 flex items-center gap-2">
                <Building2 className="w-4 h-4 text-cobalt-600" /> Business
              </h3>
              {business ? (
                <dl className="grid grid-cols-2 gap-4">
                  <Field label="Name">{business.name}</Field>
                  <Field label="Industry">{business.industry}</Field>
                  <Field label="Location">{business.city}</Field>
                  <Field label="Tone">{business.tone}</Field>
                  <Field label="Website">
                    {business.website_url ? (
                      <a
                        href={business.website_url}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="text-cobalt-600 hover:underline inline-flex items-center gap-1"
                      >
                        <Globe className="w-3 h-3" /> Visit
                      </a>
                    ) : '—'}
                  </Field>
                  <Field label="Last generated">
                    {business.last_generated_at ? timeAgo(business.last_generated_at) : 'Never'}
                  </Field>
                </dl>
              ) : (
                <p className="text-sm text-slate-500">No business set up yet.</p>
              )}
            </section>

            {/* Connections */}
            <section className="rounded-2xl border border-line bg-white p-4">
              <h3 className="font-display font-bold text-sm text-navy-900 mb-3 flex items-center gap-2">
                <Link2 className="w-4 h-4 text-cobalt-600" /> Platform connections
              </h3>
              {data?.connections?.length ? (
                <ul className="space-y-2">
                  {data.connections.map((conn) => (
                    <li key={conn.id} className="flex items-start justify-between gap-3 rounded-xl bg-white px-3 py-2.5">
                      <div className="min-w-0">
                        <p className="text-sm font-semibold text-navy-900">{platformLabel(conn.platform)}</p>
                        <p className="text-2xs text-slate-500">
                          {conn.accounts?.length
                            ? conn.accounts.map((a) => a.name).join(', ')
                            : 'No account selected'}
                        </p>
                        {conn.last_error_message && (
                          <p className="text-2xs text-danger mt-1 flex items-start gap-1">
                            <AlertTriangle className="w-3 h-3 shrink-0 mt-px" />
                            {conn.last_error_message}
                          </p>
                        )}
                      </div>
                      <div className="text-right shrink-0">
                        {!conn.is_active
                          ? <Pill tone="red">Disconnected</Pill>
                          : conn.is_expired
                            ? <Pill tone="amber">Token expired</Pill>
                            : <Pill tone="green">Connected</Pill>}
                        <p className="text-2xs text-slate-500 mt-1">
                          {conn.expires_at ? `Expires ${formatDate(conn.expires_at)}` : 'No expiry'}
                        </p>
                      </div>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-sm text-slate-500">No platforms connected.</p>
              )}
            </section>

            {/* Posts */}
            <section className="rounded-2xl border border-line bg-white p-4">
              <h3 className="font-display font-bold text-sm text-navy-900 mb-3 flex items-center gap-2">
                <Zap className="w-4 h-4 text-cobalt-600" /> Posts
                <span className="text-xs font-normal text-slate-500">({data?.posts?.total ?? 0} total)</span>
              </h3>
              {data?.posts?.recent?.length ? (
                <ul className="space-y-2">
                  {data.posts.recent.map((post) => (
                    <li key={post.id} className="rounded-xl bg-white px-3 py-2.5">
                      <div className="flex items-center justify-between gap-3 mb-1">
                        <span className="text-2xs font-bold uppercase tracking-wide text-slate-500">
                          {platformLabel(post.platform)}
                        </span>
                        <div className="flex items-center gap-2">
                          <PostStatusPill status={post.status} />
                          <span className="text-2xs text-slate-500">
                            {timeAgo(post.posted_at ?? post.scheduled_at ?? post.created_at)}
                          </span>
                        </div>
                      </div>
                      <p className="text-xs text-slate-500 line-clamp-2">{post.excerpt}</p>
                      {post.failure_reason && (
                        <p className="text-2xs text-danger mt-1">{post.failure_reason}</p>
                      )}
                    </li>
                  ))}
                </ul>
              ) : (
                <EmptyState icon={Zap} title="No posts generated yet." />
              )}
            </section>

            {/* Subscription history */}
            {data?.subscription?.length > 0 && (
              <section className="rounded-2xl border border-line bg-white p-4">
                <h3 className="font-display font-bold text-sm text-navy-900 mb-3 flex items-center gap-2">
                  <Mail className="w-4 h-4 text-cobalt-600" /> Subscription history
                </h3>
                <ul className="space-y-2">
                  {data.subscription.map((sub, i) => (
                    <li key={i} className="flex items-center justify-between gap-3 text-xs">
                      <span className="text-slate-500 capitalize">{sub.plan ?? 'unknown plan'}</span>
                      <div className="flex items-center gap-2">
                        {sub.stripe_status === 'active'
                          ? <CheckCircle2 className="w-3.5 h-3.5 text-success-text" />
                          : <XCircle className="w-3.5 h-3.5 text-slate-500" />}
                        <span className="text-slate-500">{sub.stripe_status}</span>
                        <span className="text-slate-500">{formatDateTime(sub.created_at)}</span>
                      </div>
                    </li>
                  ))}
                </ul>
              </section>
            )}
          </div>
        )}
      </aside>
    </div>
  )
}
