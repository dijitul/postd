import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Users, Zap, AlertTriangle, CheckCircle, PoundSterling, BarChart3,
  Activity, ShieldCheck, RefreshCw, Search, Gift, Link2, Server,
  TrendingUp, Clock, Cpu, Inbox, XCircle, ChevronLeft, ChevronRight,
} from 'lucide-react'
import Logo from '../../components/ui/Logo.jsx'
import { adminApi } from '../../lib/api.js'
import CustomerDrawer from './CustomerDrawer.jsx'
import {
  Panel, StatCard, EmptyState, SkeletonRows, Pill, StatusPill, BarChart, ShareBar,
  gbp, usd, num, compactTokens, formatDate, formatDateTime, timeAgo, platformLabel,
} from './components.jsx'

const TABS = [
  { id: 'overview', label: 'Overview', icon: BarChart3 },
  { id: 'customers', label: 'Customers', icon: Users },
  { id: 'activity', label: 'Activity', icon: Activity },
  { id: 'health', label: 'Health', icon: Server },
  { id: 'costs', label: 'AI costs', icon: Cpu },
]

const REFRESH_MS = 30000

const COST_RANGES = [
  { id: 'month', label: 'This month' },
  { id: '30d', label: 'Last 30 days' },
  { id: '90d', label: 'Last 90 days' },
  { id: 'all', label: 'All time' },
]

/** Turns a range id into the from/to dates the AI costs endpoint expects. */
function costRangeParams(range) {
  const to = new Date()
  const from = new Date()

  if (range === 'month') from.setDate(1)
  else if (range === '30d') from.setDate(to.getDate() - 29)
  else if (range === '90d') from.setDate(to.getDate() - 89)
  else from.setFullYear(2024, 0, 1) // before any usage was logged

  const iso = (d) => d.toISOString().slice(0, 10)
  return { from: iso(from), to: iso(to) }
}

/**
 * Unwraps an admin API call into [data, error]. The dashboard shows partial
 * data rather than blanking out when one endpoint is unhappy.
 */
async function safeGet(fn) {
  try {
    const res = await fn()
    return [res.data, null]
  } catch (e) {
    return [null, e.response?.data?.message ?? e.message ?? 'Request failed']
  }
}

export default function AdminPage() {
  const [tab, setTab] = useState('overview')
  const [period, setPeriod] = useState(30)
  const [autoRefresh, setAutoRefresh] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [lastUpdated, setLastUpdated] = useState(null)
  const [errors, setErrors] = useState([])

  const [overview, setOverview] = useState(null)
  const [atRisk, setAtRisk] = useState(null)
  const [activity, setActivity] = useState(null)
  const [health, setHealth] = useState(null)
  const [costs, setCosts] = useState(null)

  const [customers, setCustomers] = useState(null)
  const [customerMeta, setCustomerMeta] = useState(null)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [page, setPage] = useState(1)
  const [selectedCustomer, setSelectedCustomer] = useState(null)

  const [chartMetric, setChartMetric] = useState('posted')
  const [costRange, setCostRange] = useState('month')

  // Debounced search so typing does not fire a request per keystroke
  const [debouncedSearch, setDebouncedSearch] = useState('')
  useEffect(() => {
    const t = setTimeout(() => { setDebouncedSearch(search); setPage(1) }, 350)
    return () => clearTimeout(t)
  }, [search])

  const loadAll = useCallback(async ({ silent = false } = {}) => {
    if (!silent) setRefreshing(true)
    const found = []

    const [ov, ovErr] = await safeGet(() => adminApi.getOverview({ period }))
    if (ov) setOverview(ov); else found.push(`Overview: ${ovErr}`)

    const [ar, arErr] = await safeGet(() => adminApi.getAtRisk())
    if (ar) setAtRisk(ar); else found.push(`At risk: ${arErr}`)

    const [hl, hlErr] = await safeGet(() => adminApi.getSystemHealth())
    if (hl) setHealth(hl); else found.push(`Health: ${hlErr}`)

    setErrors(found)
    setLastUpdated(new Date().toISOString())
    setRefreshing(false)
  }, [period])

  const loadCustomers = useCallback(async () => {
    const [data, err] = await safeGet(() => adminApi.getCustomers({
      page,
      per_page: 25,
      search: debouncedSearch || undefined,
      status: statusFilter || undefined,
    }))
    if (data) {
      setCustomers(data.customers ?? [])
      setCustomerMeta(data.meta ?? null)
    } else {
      setCustomers([])
      setErrors((prev) => [...prev.filter((e) => !e.startsWith('Customers:')), `Customers: ${err}`])
    }
  }, [page, debouncedSearch, statusFilter])

  const loadActivity = useCallback(async () => {
    const [data] = await safeGet(() => adminApi.getActivity({ limit: 80, days: 14 }))
    setActivity(data?.events ?? [])
  }, [])

  const loadCosts = useCallback(async () => {
    const [data] = await safeGet(() => adminApi.getAiCosts(costRangeParams(costRange)))
    setCosts(data)
  }, [costRange])

  useEffect(() => { loadAll() }, [loadAll])
  useEffect(() => { if (tab === 'customers') loadCustomers() }, [tab, loadCustomers])
  useEffect(() => { if (tab === 'activity') loadActivity() }, [tab, loadActivity])
  useEffect(() => { if (tab === 'costs') loadCosts() }, [tab, loadCosts])

  // Live polling. Paused while a drawer is open so an action is not clobbered
  // mid-edit by a background refresh.
  const timerRef = useRef(null)
  useEffect(() => {
    clearInterval(timerRef.current)
    if (!autoRefresh || selectedCustomer) return undefined

    timerRef.current = setInterval(() => {
      loadAll({ silent: true })
      if (tab === 'customers') loadCustomers()
      if (tab === 'activity') loadActivity()
      if (tab === 'costs') loadCosts()
    }, REFRESH_MS)

    return () => clearInterval(timerRef.current)
  }, [autoRefresh, selectedCustomer, tab, loadAll, loadCustomers, loadActivity, loadCosts])

  const refreshNow = () => {
    loadAll()
    if (tab === 'customers') loadCustomers()
    if (tab === 'activity') loadActivity()
    if (tab === 'costs') loadCosts()
  }

  const loading = overview === null && errors.length === 0
  const rev = overview?.revenue
  const cust = overview?.customers
  const content = overview?.content
  const conns = overview?.connections
  const ai = overview?.ai

  const healthDown = useMemo(
    () => health?.services?.filter((s) => !s.healthy) ?? [],
    [health],
  )

  return (
    <div className="min-h-screen bg-navy-950 text-white">

      <header className="sticky top-0 z-30 bg-navy-950/95 backdrop-blur border-b border-white/10 px-4 sm:px-6 py-3">
        <div className="max-w-[1400px] mx-auto flex items-center justify-between gap-4">
          <div className="flex items-center gap-3 min-w-0">
            <Logo size="sm" variant="reversed" asLink={false} />
            <div className="h-6 w-px bg-white/15 hidden sm:block" />
            <div className="hidden sm:flex items-center gap-2">
              <ShieldCheck className="w-4 h-4 text-amber-400" />
              <span className="text-sm font-semibold text-white/80">Dijitul Admin</span>
            </div>
          </div>

          <div className="flex items-center gap-2 sm:gap-3">
            <button
              type="button"
              onClick={() => setAutoRefresh((v) => !v)}
              className="flex items-center gap-1.5 text-2xs font-semibold text-white/40 hover:text-white/70 transition-colors"
              title={autoRefresh ? 'Live updates on' : 'Live updates paused'}
            >
              <span className={`w-1.5 h-1.5 rounded-full ${autoRefresh ? 'bg-green-500 animate-pulse' : 'bg-white/30'}`} />
              {autoRefresh ? 'Live' : 'Paused'}
            </button>

            <span className="text-2xs text-white/25 hidden md:inline">
              {lastUpdated ? `Updated ${timeAgo(lastUpdated)}` : ''}
            </span>

            <button
              type="button"
              onClick={refreshNow}
              disabled={refreshing}
              className="flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70 transition-colors"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${refreshing ? 'animate-spin' : ''}`} />
              <span className="hidden sm:inline">Refresh</span>
            </button>

            <Link to="/dashboard" className="text-xs text-white/40 hover:text-white/70 transition-colors">
              Exit
            </Link>
          </div>
        </div>
      </header>

      <div className="max-w-[1400px] mx-auto px-4 sm:px-6 py-6 space-y-6">

        {/* Failures are surfaced rather than swallowed into an empty dashboard */}
        {errors.length > 0 && (
          <div className="rounded-2xl border border-red-500/25 bg-red-500/10 p-4">
            <div className="flex items-center gap-2 mb-1.5">
              <AlertTriangle className="w-4 h-4 text-red-400" />
              <span className="text-sm font-bold text-red-300">Some data could not be loaded</span>
            </div>
            <ul className="text-xs text-red-300/70 space-y-0.5">
              {errors.map((e) => <li key={e}>{e}</li>)}
            </ul>
          </div>
        )}

        {healthDown.length > 0 && (
          <div className="rounded-2xl border border-red-500/25 bg-red-500/10 p-4 flex items-start gap-3">
            <XCircle className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
            <div>
              <p className="text-sm font-bold text-red-300">
                {healthDown.length} service{healthDown.length > 1 ? 's' : ''} down
              </p>
              <p className="text-xs text-red-300/70">
                {healthDown.map((s) => `${s.name} (${s.message})`).join(' · ')}
              </p>
            </div>
          </div>
        )}

        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex gap-1 bg-white/[0.06] p-1 rounded-xl overflow-x-auto">
            {TABS.map((t) => (
              <button
                key={t.id}
                type="button"
                onClick={() => setTab(t.id)}
                className={`flex items-center gap-1.5 px-3 sm:px-4 py-2 rounded-lg text-xs sm:text-sm font-semibold transition-all whitespace-nowrap ${
                  tab === t.id ? 'bg-amber-500 text-white' : 'text-white/50 hover:text-white/80'
                }`}
              >
                <t.icon className="w-3.5 h-3.5" />
                {t.label}
              </button>
            ))}
          </div>

          {tab === 'overview' && (
            <div className="flex gap-1 bg-white/[0.06] p-1 rounded-xl">
              {[7, 30, 90].map((d) => (
                <button
                  key={d}
                  type="button"
                  onClick={() => setPeriod(d)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                    period === d ? 'bg-white/10 text-white' : 'text-white/40 hover:text-white/70'
                  }`}
                >
                  {d}d
                </button>
              ))}
            </div>
          )}
        </div>

        {/* ── Overview ─────────────────────────────────────────────────── */}
        {tab === 'overview' && (
          <div className="space-y-6">

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
              <StatCard
                accent
                loading={loading}
                icon={PoundSterling}
                label="Monthly recurring revenue"
                value={gbp(rev?.mrr)}
                sub={`${num(rev?.active_subscriptions)} paying · ${gbp(rev?.arr)} ARR`}
              />
              <StatCard
                loading={loading}
                icon={Users}
                label="Customers"
                value={num(cust?.total)}
                sub={`${num(cust?.trialing)} on trial · ${num(cust?.new_this_period)} new in ${period}d`}
              />
              <StatCard
                loading={loading}
                icon={Gift}
                label="Free accounts"
                value={num(rev?.comped_customers)}
                sub={`${gbp(rev?.comped_mrr_forgone)}/mo given away`}
              />
              <StatCard
                loading={loading}
                icon={AlertTriangle}
                label="Churn this month"
                value={num(cust?.churned_this_month)}
                tone={cust?.churned_this_month > 0 ? 'danger' : undefined}
                sub={`${gbp(rev?.churned_mrr_this_month)}/mo lost`}
              />
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
              <StatCard
                loading={loading}
                icon={Zap}
                label={`Posts published (${period}d)`}
                value={num(content?.posted)}
                sub={`${content?.success_rate ?? 0}% success rate`}
              />
              <StatCard
                loading={loading}
                icon={XCircle}
                label="Failed posts (24h)"
                value={num(content?.failed_24h)}
                tone={content?.failed_24h > 0 ? 'danger' : undefined}
                sub={`${num(content?.failed)} in ${period}d`}
              />
              <StatCard
                loading={loading}
                icon={Inbox}
                label="Awaiting approval"
                value={num(content?.awaiting_approval)}
                sub={`${num(content?.scheduled)} scheduled`}
              />
              <StatCard
                loading={loading}
                icon={Cpu}
                label="AI spend this month"
                value={usd(ai?.cost_usd_this_month)}
                sub={`${compactTokens(ai?.tokens_this_month)} tokens · ${usd(ai?.per_customer_usd)} per paying customer`}
              />
            </div>

            {/* Running totals since launch */}
            <Panel title="Token usage and cost" subtitle="All time, across every account">
              <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                  <p className="text-2xs font-bold uppercase tracking-wide text-white/30 mb-1">Total spend</p>
                  <p className="font-display font-black text-2xl text-white">{usd(ai?.cost_usd_all_time)}</p>
                  <p className="text-2xs text-white/35 mt-0.5">about {gbp(ai?.cost_gbp_all_time, { decimals: 2 })}</p>
                </div>
                <div>
                  <p className="text-2xs font-bold uppercase tracking-wide text-white/30 mb-1">Total tokens</p>
                  <p className="font-display font-black text-2xl text-white">{compactTokens(ai?.tokens_all_time)}</p>
                  <p className="text-2xs text-white/35 mt-0.5">
                    {compactTokens(ai?.prompt_tokens_all_time)} in · {compactTokens(ai?.completion_tokens_all_time)} out
                  </p>
                </div>
                <div>
                  <p className="text-2xs font-bold uppercase tracking-wide text-white/30 mb-1">Cost per 1k tokens</p>
                  <p className="font-display font-black text-2xl text-white">
                    {usd(ai?.cost_per_1k_tokens_usd, { decimals: 4 })}
                  </p>
                  <p className="text-2xs text-white/35 mt-0.5">{num(ai?.operations_all_time)} operations</p>
                </div>
                <div>
                  <p className="text-2xs font-bold uppercase tracking-wide text-white/30 mb-1">Spend in {period}d</p>
                  <p className="font-display font-black text-2xl text-white">{usd(ai?.cost_usd_period)}</p>
                  <p className="text-2xs text-white/35 mt-0.5">{compactTokens(ai?.tokens_period)} tokens</p>
                </div>
              </div>
            </Panel>

            <div className="grid lg:grid-cols-3 gap-4">
              <Panel
                className="lg:col-span-2"
                title={`Last ${period} days`}
                subtitle="Daily totals"
                action={
                  <div className="flex gap-1 bg-white/[0.06] p-0.5 rounded-lg">
                    {[
                      { id: 'posted', label: 'Published' },
                      { id: 'signups', label: 'Signups' },
                      { id: 'failed', label: 'Failures' },
                    ].map((m) => (
                      <button
                        key={m.id}
                        type="button"
                        onClick={() => setChartMetric(m.id)}
                        className={`px-2.5 py-1 rounded text-2xs font-bold transition-colors ${
                          chartMetric === m.id ? 'bg-white/10 text-white' : 'text-white/40 hover:text-white/70'
                        }`}
                      >
                        {m.label}
                      </button>
                    ))}
                  </div>
                }
              >
                <BarChart
                  data={overview?.series}
                  metric={chartMetric}
                  colour={chartMetric === 'failed' ? '#EF4444' : chartMetric === 'signups' ? '#F5C842' : '#E07B30'}
                />
              </Panel>

              <Panel title="Revenue by plan" subtitle="Active subscriptions">
                {rev?.by_plan?.length ? (
                  <ul className="space-y-4">
                    {rev.by_plan.map((p) => (
                      <li key={p.plan}>
                        <div className="flex items-baseline justify-between mb-1.5">
                          <span className="text-sm font-semibold text-white capitalize">{p.name}</span>
                          <span className="text-sm font-display font-black text-white">{gbp(p.mrr)}</span>
                        </div>
                        <ShareBar value={p.mrr_pence} total={rev.mrr_pence} />
                        <p className="text-2xs text-white/35 mt-1">
                          {p.customers} customer{p.customers === 1 ? '' : 's'} at {gbp(p.price)}/mo
                        </p>
                      </li>
                    ))}
                    {rev.unpriced_subscriptions > 0 && (
                      <li className="text-2xs text-amber-400/70 pt-1 border-t border-white/10">
                        {rev.unpriced_subscriptions} subscription(s) on a price ID not in the plan config, so excluded from MRR.
                      </li>
                    )}
                  </ul>
                ) : (
                  <EmptyState icon={PoundSterling} title="No active subscriptions yet." />
                )}
              </Panel>
            </div>

            <div className="grid lg:grid-cols-2 gap-4">
              <Panel title="Platform connections" subtitle={`${conns?.health_pct ?? 100}% of tokens valid`}>
                {conns?.by_platform?.length ? (
                  <ul className="space-y-3">
                    {conns.by_platform.map((p) => (
                      <li key={p.platform} className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2 min-w-0">
                          <Link2 className="w-3.5 h-3.5 text-white/25 shrink-0" />
                          <span className="text-sm text-white/80 truncate">{platformLabel(p.platform)}</span>
                        </div>
                        <div className="flex items-center gap-1.5 shrink-0">
                          <Pill tone="green">{p.active} active</Pill>
                          {p.expiring_7d > 0 && <Pill tone="amber">{p.expiring_7d} expiring</Pill>}
                          {p.expired > 0 && <Pill tone="red">{p.expired} expired</Pill>}
                          {p.broken > 0 && <Pill tone="red">{p.broken} broken</Pill>}
                        </div>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <EmptyState icon={Link2} title="No platforms connected yet." />
                )}
              </Panel>

              <Panel title="Posts by platform" subtitle={`Last ${period} days`}>
                {content?.by_platform?.length ? (
                  <ul className="space-y-3">
                    {content.by_platform.map((p) => (
                      <li key={p.platform}>
                        <div className="flex items-baseline justify-between mb-1.5">
                          <span className="text-sm text-white/80">{platformLabel(p.platform)}</span>
                          <span className="text-xs text-white/40">
                            {num(p.posted)} published
                            {p.failed > 0 && <span className="text-red-400"> · {num(p.failed)} failed</span>}
                          </span>
                        </div>
                        <ShareBar
                          value={p.posted}
                          total={p.total}
                          colour={p.failed > p.posted ? 'bg-red-500' : 'bg-amber-500'}
                        />
                      </li>
                    ))}
                  </ul>
                ) : (
                  <EmptyState icon={Zap} title="No posts in this period." />
                )}
              </Panel>
            </div>

            <Panel
              title="Needs attention"
              subtitle={atRisk ? `${atRisk.totals?.high ?? 0} high risk, ${atRisk.totals?.medium ?? 0} to watch` : ''}
              padded={false}
            >
              {!atRisk ? (
                <div className="p-5"><SkeletonRows rows={3} /></div>
              ) : atRisk.at_risk?.length === 0 ? (
                <EmptyState icon={CheckCircle} title="Nothing at risk right now." hint="Trials, activity and connections all look healthy." />
              ) : (
                <ul className="divide-y divide-white/5">
                  {atRisk.at_risk.map((r, i) => (
                    <li key={`${r.customer_id}-${r.reason}-${i}`}>
                      <button
                        type="button"
                        onClick={() => r.customer_id && setSelectedCustomer(r.customer_id)}
                        className="w-full text-left px-5 py-3 flex items-center justify-between gap-4 hover:bg-white/[0.04] transition-colors"
                      >
                        <div className="min-w-0">
                          <p className="text-sm font-semibold text-white truncate">{r.name}</p>
                          <p className="text-xs text-white/40 truncate">{r.email}</p>
                        </div>
                        <div className="flex items-center gap-3 shrink-0">
                          <span className="text-xs text-white/50 hidden sm:inline">{r.detail}</span>
                          <Pill tone={r.risk === 'high' ? 'red' : 'amber'}>
                            {r.risk === 'high' ? 'High risk' : 'Watch'}
                          </Pill>
                        </div>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </Panel>
          </div>
        )}

        {/* ── Customers ────────────────────────────────────────────────── */}
        {tab === 'customers' && (
          <div className="space-y-4">
            <div className="flex flex-wrap gap-3">
              <div className="relative flex-1 min-w-[240px]">
                <Search className="w-4 h-4 text-white/30 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="search"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Search by name, email or business"
                  className="w-full rounded-xl bg-white/[0.06] border border-white/10 pl-9 pr-3 py-2.5 text-sm text-white placeholder:text-white/30 focus:border-amber-500 focus:outline-none"
                />
              </div>

              <div className="flex gap-1 bg-white/[0.06] p-1 rounded-xl overflow-x-auto">
                {[
                  { id: '', label: 'All' },
                  { id: 'subscribed', label: 'Paying' },
                  { id: 'trialing', label: 'Trial' },
                  { id: 'comped', label: 'Free' },
                  { id: 'expired', label: 'Expired' },
                  { id: 'not_onboarded', label: 'Not set up' },
                ].map((f) => (
                  <button
                    key={f.id}
                    type="button"
                    onClick={() => { setStatusFilter(f.id); setPage(1) }}
                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all ${
                      statusFilter === f.id ? 'bg-white/10 text-white' : 'text-white/40 hover:text-white/70'
                    }`}
                  >
                    {f.label}
                  </button>
                ))}
              </div>
            </div>

            <Panel padded={false}>
              {customers === null ? (
                <div className="p-5"><SkeletonRows rows={6} /></div>
              ) : customers.length === 0 ? (
                <EmptyState icon={Users} title="No customers match that filter." />
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full min-w-[820px]">
                    <thead>
                      <tr className="border-b border-white/10">
                        {['Customer', 'Status', 'Plan', 'MRR', 'Posts', 'Platforms', 'Last seen'].map((h) => (
                          <th key={h} className="text-left px-4 py-3 text-2xs font-bold text-white/35 uppercase tracking-wide">
                            {h}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {customers.map((c) => (
                        <tr
                          key={c.id}
                          onClick={() => setSelectedCustomer(c.id)}
                          className="border-b border-white/5 last:border-0 hover:bg-white/[0.04] transition-colors cursor-pointer"
                        >
                          <td className="px-4 py-3">
                            <p className="text-sm font-semibold text-white">
                              {c.business?.name ?? c.name}
                            </p>
                            <p className="text-2xs text-white/40">{c.email}</p>
                          </td>
                          <td className="px-4 py-3"><StatusPill status={c.status} /></td>
                          <td className="px-4 py-3 text-sm text-white/60 capitalize">{c.plan_label}</td>
                          <td className="px-4 py-3 text-sm font-semibold text-white/80">
                            {c.mrr > 0 ? gbp(c.mrr) : <span className="text-white/25">—</span>}
                          </td>
                          <td className="px-4 py-3 text-sm text-white/60">
                            {num(c.business?.posts_count ?? 0)}
                            {c.business?.pending_posts_count > 0 && (
                              <span className="text-amber-400 text-2xs ml-1.5">
                                · {c.business.pending_posts_count} pending
                              </span>
                            )}
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex flex-wrap gap-1">
                              {c.business?.platforms?.length
                                ? c.business.platforms.map((p) => (
                                    <Pill key={p} tone="slate">{platformLabel(p)}</Pill>
                                  ))
                                : <span className="text-white/25 text-xs">None</span>}
                            </div>
                          </td>
                          <td className="px-4 py-3 text-xs text-white/40">
                            {c.last_seen_at ? timeAgo(c.last_seen_at) : 'Never'}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Panel>

            {customerMeta && customerMeta.last_page > 1 && (
              <div className="flex items-center justify-between">
                <p className="text-xs text-white/40">
                  Page {customerMeta.current_page} of {customerMeta.last_page} · {num(customerMeta.total)} customers
                </p>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                    disabled={customerMeta.current_page <= 1}
                    className="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white/[0.06] text-xs font-semibold text-white/70 disabled:opacity-30 hover:bg-white/10 transition-colors"
                  >
                    <ChevronLeft className="w-3.5 h-3.5" /> Previous
                  </button>
                  <button
                    type="button"
                    onClick={() => setPage((p) => p + 1)}
                    disabled={customerMeta.current_page >= customerMeta.last_page}
                    className="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white/[0.06] text-xs font-semibold text-white/70 disabled:opacity-30 hover:bg-white/10 transition-colors"
                  >
                    Next <ChevronRight className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            )}
          </div>
        )}

        {/* ── Activity ─────────────────────────────────────────────────── */}
        {tab === 'activity' && (
          <Panel title="Recent activity" subtitle="Last 14 days across every account" padded={false}>
            {activity === null ? (
              <div className="p-5"><SkeletonRows rows={8} /></div>
            ) : activity.length === 0 ? (
              <EmptyState icon={Activity} title="Nothing has happened in the last 14 days." />
            ) : (
              <ul className="divide-y divide-white/5">
                {activity.map((e, i) => (
                  <li key={`${e.type}-${e.at}-${i}`}>
                    <button
                      type="button"
                      onClick={() => e.customer_id && setSelectedCustomer(e.customer_id)}
                      disabled={!e.customer_id}
                      className="w-full text-left px-5 py-3 flex items-start gap-3 hover:bg-white/[0.04] transition-colors disabled:hover:bg-transparent"
                    >
                      <span className={`w-1.5 h-1.5 rounded-full mt-2 shrink-0 ${
                        e.severity === 'error' ? 'bg-red-500'
                          : e.severity === 'success' ? 'bg-green-500'
                          : e.severity === 'warning' ? 'bg-amber-500'
                          : 'bg-blue-400'
                      }`} />
                      <div className="min-w-0 flex-1">
                        <p className="text-sm text-white/90">{e.title}</p>
                        <p className="text-xs text-white/40 truncate">{e.detail}</p>
                      </div>
                      <span className="text-2xs text-white/30 shrink-0 mt-0.5" title={formatDateTime(e.at)}>
                        {timeAgo(e.at)}
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        )}

        {/* ── Health ───────────────────────────────────────────────────── */}
        {tab === 'health' && (
          <div className="space-y-4">
            <Panel title="Services" subtitle={health ? `Checked ${timeAgo(health.checked_at)}` : ''}>
              {!health ? (
                <SkeletonRows rows={5} />
              ) : (
                <ul className="space-y-2">
                  {health.services.map((s) => (
                    <li key={s.name} className="flex items-center justify-between gap-3 rounded-xl bg-white/[0.03] px-4 py-3">
                      <div className="flex items-center gap-2.5 min-w-0">
                        <span className={`w-2 h-2 rounded-full shrink-0 ${s.healthy ? 'bg-green-500 animate-pulse' : 'bg-red-500'}`} />
                        <span className="text-sm font-semibold text-white truncate">{s.name}</span>
                      </div>
                      <div className="flex items-center gap-3 shrink-0">
                        <span className={`text-xs ${s.healthy ? 'text-green-400' : 'text-red-400'}`}>
                          {s.message}
                        </span>
                        <span className="text-2xs text-white/25 tabular-nums">{s.latency_ms}ms</span>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </Panel>

            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
              <StatCard
                icon={Clock}
                label="Queue depth"
                value={num(health?.queues?.reduce((sum, q) => sum + (q.pending ?? 0), 0))}
                sub={health?.queues?.map((q) => `${q.queue}: ${q.pending ?? '?'}`).join(' · ')}
                loading={!health}
              />
              <StatCard
                icon={XCircle}
                label="Failed jobs (24h)"
                value={num(health?.failed_jobs_24h)}
                tone={health?.failed_jobs_24h > 0 ? 'danger' : undefined}
                loading={!health}
              />
              <StatCard
                icon={AlertTriangle}
                label="Failed posts (24h)"
                value={num(health?.failed_posts_24h)}
                tone={health?.failed_posts_24h > 0 ? 'danger' : undefined}
                loading={!health}
              />
              <StatCard
                icon={Link2}
                label="Tokens expiring (7d)"
                value={num(health?.expiring_connections_7d)}
                tone={health?.expiring_connections_7d > 0 ? 'warning' : undefined}
                loading={!health}
              />
            </div>

            <Panel title="Recent failed jobs" padded={false}>
              {!health ? (
                <div className="p-5"><SkeletonRows rows={3} /></div>
              ) : health.recent_failed_jobs?.length === 0 ? (
                <EmptyState icon={CheckCircle} title="No failed jobs." />
              ) : (
                <ul className="divide-y divide-white/5">
                  {health.recent_failed_jobs.map((j) => (
                    <li key={j.id} className="px-5 py-3">
                      <div className="flex items-center justify-between gap-3 mb-1">
                        <Pill tone="slate">{j.queue}</Pill>
                        <span className="text-2xs text-white/30">{timeAgo(j.failed_at)}</span>
                      </div>
                      <p className="text-xs text-red-300/80 font-mono break-all">{j.error}</p>
                    </li>
                  ))}
                </ul>
              )}
            </Panel>

            <Panel title="Health check log" padded={false}>
              {!health ? (
                <div className="p-5"><SkeletonRows rows={3} /></div>
              ) : health.recent_logs?.length === 0 ? (
                <EmptyState icon={Activity} title="No health checks recorded yet." />
              ) : (
                <ul className="divide-y divide-white/5">
                  {health.recent_logs.map((log) => (
                    <li key={log.id} className="px-5 py-3 flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="text-sm text-white/80">{log.check_type}</p>
                        <p className="text-xs text-white/40 truncate">
                          {[log.business_name, log.platform && platformLabel(log.platform), log.message]
                            .filter(Boolean).join(' · ')}
                        </p>
                      </div>
                      <div className="flex items-center gap-2 shrink-0">
                        <Pill tone={log.status === 'ok' ? 'green' : log.status === 'warning' ? 'amber' : 'red'}>
                          {log.status}
                        </Pill>
                        <span className="text-2xs text-white/30">{timeAgo(log.checked_at)}</span>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </Panel>
          </div>
        )}

        {/* ── AI costs ─────────────────────────────────────────────────── */}
        {tab === 'costs' && (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="text-xs text-white/40">
                {costs ? `Showing ${formatDate(costs.period.from)} to ${formatDate(costs.period.to)}` : 'Loading spend…'}
              </p>
              <div className="flex gap-1 bg-white/[0.06] p-1 rounded-xl">
                {COST_RANGES.map((r) => (
                  <button
                    key={r.id}
                    type="button"
                    onClick={() => setCostRange(r.id)}
                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                      costRange === r.id ? 'bg-white/10 text-white' : 'text-white/40 hover:text-white/70'
                    }`}
                  >
                    {r.label}
                  </button>
                ))}
              </div>
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
              <StatCard
                accent
                icon={Cpu}
                label="Spend in range"
                value={usd(costs?.total_cost_usd)}
                sub={`about ${gbp(costs?.total_cost_gbp, { decimals: 2 })} · ${compactTokens(costs?.total_tokens)} tokens`}
                loading={!costs}
              />
              <StatCard
                icon={TrendingUp}
                label="Total spend (all time)"
                value={usd(costs?.all_time?.cost_usd)}
                sub={`${compactTokens(costs?.all_time?.tokens)} tokens · ${num(costs?.all_time?.operations)} operations`}
                loading={!costs}
              />
              <StatCard
                icon={Users}
                label="Cost per paying customer"
                value={usd(ai?.per_customer_usd)}
                sub="This calendar month"
                loading={!overview}
              />
              <StatCard
                icon={PoundSterling}
                label="Gross margin proxy"
                value={rev?.mrr > 0
                  ? `${Math.round(((rev.mrr - (costs?.total_cost_gbp ?? 0)) / rev.mrr) * 100)}%`
                  : '—'}
                sub="MRR less AI cost in range"
                loading={!overview || !costs}
              />
            </div>

            <div className="grid lg:grid-cols-3 gap-4">
              <Panel className="lg:col-span-2" title="Daily spend" subtitle="US dollars per day">
                {!costs ? (
                  <SkeletonRows rows={3} />
                ) : (
                  <BarChart data={costs.daily} metric="cost_usd" colour="#F5C842" />
                )}
              </Panel>

              <Panel title="Token split" subtitle="All time">
                {!costs ? (
                  <SkeletonRows rows={3} />
                ) : (
                  <div className="space-y-4">
                    <div>
                      <div className="flex items-baseline justify-between mb-1.5">
                        <span className="text-sm text-white/80">Prompt tokens</span>
                        <span className="text-sm font-display font-black text-white">
                          {compactTokens(costs.all_time?.prompt_tokens)}
                        </span>
                      </div>
                      <ShareBar value={costs.all_time?.prompt_tokens} total={costs.all_time?.tokens} />
                    </div>
                    <div>
                      <div className="flex items-baseline justify-between mb-1.5">
                        <span className="text-sm text-white/80">Completion tokens</span>
                        <span className="text-sm font-display font-black text-white">
                          {compactTokens(costs.all_time?.completion_tokens)}
                        </span>
                      </div>
                      <ShareBar
                        value={costs.all_time?.completion_tokens}
                        total={costs.all_time?.tokens}
                        colour="bg-honey-400"
                      />
                    </div>
                    <div className="pt-3 border-t border-white/10 space-y-1">
                      <div className="flex justify-between text-xs">
                        <span className="text-white/40">Cost per 1k tokens</span>
                        <span className="text-white/80 tabular-nums">
                          {usd(costs.all_time?.cost_per_1k_tokens_usd, { decimals: 4 })}
                        </span>
                      </div>
                      <div className="flex justify-between text-xs">
                        <span className="text-white/40">Tracking since</span>
                        <span className="text-white/80">{formatDate(costs.all_time?.since)}</span>
                      </div>
                    </div>
                  </div>
                )}
              </Panel>
            </div>

            <Panel title="By operation and model" subtitle="Token usage and cost in range" padded={false}>
              {!costs ? (
                <div className="p-5"><SkeletonRows rows={3} /></div>
              ) : costs.by_operation?.length === 0 ? (
                <EmptyState icon={Cpu} title="No AI usage logged in this range." />
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full min-w-[640px]">
                    <thead>
                      <tr className="border-b border-white/10">
                        {['Operation', 'Model', 'Cost', 'Tokens', 'In / out', 'Calls'].map((h) => (
                          <th key={h} className="text-left px-4 py-3 text-2xs font-bold text-white/35 uppercase tracking-wide">{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {costs.by_operation.map((op, i) => (
                        <tr key={`${op.operation}-${op.model}-${i}`} className="border-b border-white/5 last:border-0">
                          <td className="px-4 py-3 text-sm text-white/90">{op.operation}</td>
                          <td className="px-4 py-3 text-xs text-white/45 font-mono">{op.model}</td>
                          <td className="px-4 py-3 text-sm font-semibold text-white">{usd(op.cost_usd, { decimals: 3 })}</td>
                          <td className="px-4 py-3 text-sm text-white/60">{compactTokens(op.tokens)}</td>
                          <td className="px-4 py-3 text-xs text-white/40">
                            {compactTokens(op.prompt_tokens)} / {compactTokens(op.completion_tokens)}
                          </td>
                          <td className="px-4 py-3 text-sm text-white/50">{num(op.operations)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Panel>

            <Panel title="By business" subtitle="Highest spend first" padded={false}>
              {!costs ? (
                <div className="p-5"><SkeletonRows rows={4} /></div>
              ) : costs.by_business?.length === 0 ? (
                <EmptyState icon={Cpu} title="No business has generated anything this month." />
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full min-w-[560px]">
                    <thead>
                      <tr className="border-b border-white/10">
                        {['Business', 'Cost', 'Tokens', 'Operations', 'Last used'].map((h) => (
                          <th key={h} className="text-left px-4 py-3 text-2xs font-bold text-white/35 uppercase tracking-wide">{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {costs.by_business.map((row) => (
                        <tr
                          key={row.business_id}
                          onClick={() => row.customer_id && setSelectedCustomer(row.customer_id)}
                          className="border-b border-white/5 last:border-0 hover:bg-white/[0.04] transition-colors cursor-pointer"
                        >
                          <td className="px-4 py-3 text-sm font-semibold text-white">{row.business_name}</td>
                          <td className="px-4 py-3 text-sm text-white/80">{usd(row.total_cost_usd, { decimals: 3 })}</td>
                          <td className="px-4 py-3 text-sm text-white/50">{compactTokens(row.total_tokens)}</td>
                          <td className="px-4 py-3 text-sm text-white/50">{num(row.operations)}</td>
                          <td className="px-4 py-3 text-xs text-white/40">{timeAgo(row.last_operation)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Panel>
          </div>
        )}

        <footer className="border-t border-white/10 pt-6 text-center">
          <p className="text-2xs text-white/20">
            postd.uk admin — Dijitul internal use only. Built in Mansfield, UK.
            {overview?.generated_at && ` Data as at ${formatDate(overview.generated_at)}.`}
          </p>
        </footer>
      </div>

      {selectedCustomer && (
        <CustomerDrawer
          customerId={selectedCustomer}
          onClose={() => setSelectedCustomer(null)}
          onChanged={() => { loadAll({ silent: true }); if (tab === 'customers') loadCustomers() }}
        />
      )}
    </div>
  )
}
