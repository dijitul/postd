import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import {
  Users, Zap, AlertTriangle, CheckCircle,
  DollarSign, BarChart3, Activity, ArrowUpRight, ArrowDownRight,
  ShieldCheck, RefreshCw
} from 'lucide-react'
import Logo from '../../components/ui/Logo.jsx'
import { adminApi } from '../../lib/api.js'

function MetricCard({ label, value, sub, trend, trendPositive = true, icon: Icon, accent = false, loading = false }) {
  return (
    <div
      className={`rounded-2xl p-5 border ${accent ? 'bg-amber-500 border-amber-600 text-white' : 'bg-white border-cream-300'}`}
      style={{ boxShadow: accent ? '0 8px 24px rgb(224 123 48 / 0.3)' : '0 2px 8px rgb(30 45 74 / 0.06)' }}
    >
      <div className="flex items-start justify-between mb-3">
        <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${accent ? 'bg-white/20' : 'bg-amber-50'}`}>
          <Icon className={`w-5 h-5 ${accent ? 'text-white' : 'text-amber-500'}`} />
        </div>
        {trend !== undefined && (
          <div className={`flex items-center gap-1 text-xs font-bold ${trendPositive ? (accent ? 'text-white/80' : 'text-green-600') : (accent ? 'text-white/80' : 'text-red-500')}`}>
            {trendPositive ? <ArrowUpRight className="w-3.5 h-3.5" /> : <ArrowDownRight className="w-3.5 h-3.5" />}
            {Math.abs(trend)}%
          </div>
        )}
      </div>
      {loading ? (
        <div className="space-y-2">
          <div className={`h-7 w-24 rounded animate-pulse ${accent ? 'bg-white/20' : 'bg-cream-300'}`} />
          <div className={`h-3 w-32 rounded animate-pulse ${accent ? 'bg-white/20' : 'bg-cream-200'}`} />
        </div>
      ) : (
        <>
          <div className={`font-display font-black text-2xl mb-0.5 ${accent ? 'text-white' : 'text-navy-800'}`}>{value}</div>
          <div className={`text-xs font-semibold ${accent ? 'text-white/70' : 'text-slate-500'}`}>{label}</div>
          {sub && <div className={`text-xs mt-0.5 ${accent ? 'text-white/60' : 'text-slate-400'}`}>{sub}</div>}
        </>
      )}
    </div>
  )
}

export default function AdminPage() {
  const [tab, setTab] = useState('overview')
  const [metrics, setMetrics] = useState(null)
  const [atRisk, setAtRisk] = useState([])
  const [businesses, setBusinesses] = useState([])
  const [health, setHealth] = useState([])
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const loadData = async (showRefresh = false) => {
    if (showRefresh) setRefreshing(true)
    try {
      const [metricsRes, atRiskRes, healthRes, bizRes] = await Promise.all([
        adminApi.getMetrics(),
        adminApi.getAtRisk(),
        adminApi.getSystemHealth(),
        adminApi.getBusinesses({ per_page: 10 }),
      ])
      const data = metricsRes.data
      setMetrics(data?.stats ?? data)
      setAtRisk(atRiskRes.data?.businesses ?? atRiskRes.data ?? [])
      setBusinesses(bizRes.data?.data ?? bizRes.data?.businesses ?? [])
      setHealth(healthRes.data?.services ?? healthRes.data ?? [])
    } catch (e) {
      console.error('Admin load failed', e)
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }

  useEffect(() => { loadData() }, [])

  const m = metrics ?? {}

  return (
    <div className="min-h-screen bg-navy-950 text-white">

      {/* Admin top bar */}
      <header className="border-b border-white/10 px-6 py-4 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Logo size="sm" variant="reversed" asLink={false} />
          <div className="h-6 w-px bg-white/20" />
          <div className="flex items-center gap-2">
            <ShieldCheck className="w-4 h-4 text-amber-400" />
            <span className="text-sm font-semibold text-white/80">Dijitul Admin</span>
          </div>
        </div>
        <div className="flex items-center gap-4">
          <button
            onClick={() => loadData(true)}
            disabled={refreshing}
            className="flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70 transition-colors"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${refreshing ? 'animate-spin' : ''}`} />
            Refresh
          </button>
          <Link to="/dashboard" className="text-xs text-white/40 hover:text-white/70 transition-colors">Exit admin</Link>
        </div>
      </header>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <div>
          <h1 className="font-display font-black text-2xl sm:text-3xl mb-1">postd.uk Overview</h1>
          <p className="text-white/50 text-sm">Internal Dijitul team dashboard</p>
        </div>

        {/* Tab bar */}
        <div className="flex gap-1 bg-white/5 p-1 rounded-xl w-fit">
          {['overview', 'businesses', 'health'].map((t) => (
            <button
              key={t}
              onClick={() => setTab(t)}
              className={`px-4 py-2 rounded-lg text-sm font-semibold transition-all ${tab === t ? 'bg-amber-500 text-white' : 'text-white/50 hover:text-white/80'}`}
            >
              {t.charAt(0).toUpperCase() + t.slice(1)}
            </button>
          ))}
        </div>

        {/* Overview tab */}
        {tab === 'overview' && (
          <>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <MetricCard label="Monthly Recurring Revenue" value={m.mrr ? `£${Number(m.mrr).toLocaleString()}` : '£0'} trend={m.mrr_growth} trendPositive icon={DollarSign} accent loading={loading} />
              <MetricCard label="Active subscribers" value={m.active_subscribers ?? 0} sub={`+${m.trial_users ?? 0} on trial`} icon={Users} loading={loading} />
              <MetricCard label="Churned this month" value={m.churned_this_month ?? 0} trendPositive={false} icon={AlertTriangle} loading={loading} />
              <MetricCard label="Posts this week" value={(m.posts_this_week ?? 0).toLocaleString()} sub={m.post_success_rate ? `${m.post_success_rate}% success` : ''} icon={Zap} loading={loading} />
            </div>

            <div className="grid sm:grid-cols-3 gap-4">
              <MetricCard label="Platform token health" value={m.platform_health_pct ? `${m.platform_health_pct}%` : '—'} sub="Connections with valid tokens" icon={Activity} loading={loading} />
              <MetricCard label="Avg AI cost / business" value={m.avg_api_cost ? `£${Number(m.avg_api_cost).toFixed(2)}` : '—'} sub="OpenAI spend per month" icon={BarChart3} loading={loading} />
              <MetricCard label="Post success rate" value={m.post_success_rate ? `${m.post_success_rate}%` : '—'} sub="Posts delivered successfully" icon={CheckCircle} loading={loading} />
            </div>

            {/* At-risk */}
            <div>
              <h2 className="font-display font-bold text-lg mb-4 text-white/90">At-risk businesses</h2>
              <div className="bg-white/5 rounded-2xl border border-white/10 overflow-hidden">
                {loading ? (
                  <div className="p-8 text-center text-white/30 text-sm">Loading...</div>
                ) : atRisk.length === 0 ? (
                  <div className="p-8 text-center">
                    <CheckCircle className="w-8 h-8 text-green-500/40 mx-auto mb-2" />
                    <p className="text-white/40 text-sm">No at-risk businesses right now.</p>
                  </div>
                ) : (
                  <table className="w-full">
                    <thead>
                      <tr className="border-b border-white/10">
                        <th className="text-left px-5 py-3 text-xs font-bold text-white/40 uppercase tracking-wide">Business</th>
                        <th className="text-left px-5 py-3 text-xs font-bold text-white/40 uppercase tracking-wide hidden sm:table-cell">Plan</th>
                        <th className="text-left px-5 py-3 text-xs font-bold text-white/40 uppercase tracking-wide hidden sm:table-cell">Last active</th>
                        <th className="text-left px-5 py-3 text-xs font-bold text-white/40 uppercase tracking-wide">Risk</th>
                      </tr>
                    </thead>
                    <tbody>
                      {atRisk.map((biz) => (
                        <tr key={biz.id} className="border-b border-white/5 last:border-0 hover:bg-white/5 transition-colors">
                          <td className="px-5 py-3.5 text-sm font-semibold text-white">{biz.name}</td>
                          <td className="px-5 py-3.5 text-sm text-white/50 hidden sm:table-cell capitalize">{biz.plan ?? '—'}</td>
                          <td className="px-5 py-3.5 text-sm text-white/50 hidden sm:table-cell">
                            {biz.last_active_at ? new Date(biz.last_active_at).toLocaleDateString('en-GB') : '—'}
                          </td>
                          <td className="px-5 py-3.5">
                            <span className={`badge text-xs ${biz.risk === 'high' ? 'bg-red-900/50 text-red-400' : 'bg-amber-900/50 text-amber-400'}`}>
                              {biz.risk === 'high' ? 'High risk' : 'Watch'}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>

            {/* Recent signups */}
            <div>
              <h2 className="font-display font-bold text-lg mb-4 text-white/90">Recent signups</h2>
              {loading ? (
                <div className="space-y-2">
                  {[...Array(4)].map((_, i) => (
                    <div key={i} className="bg-white/5 rounded-xl h-14 animate-pulse border border-white/5" />
                  ))}
                </div>
              ) : businesses.length === 0 ? (
                <div className="bg-white/5 rounded-2xl border border-white/10 p-8 text-center">
                  <p className="text-white/40 text-sm">No recent signups.</p>
                </div>
              ) : (
                <div className="space-y-2">
                  {businesses.map((biz, i) => (
                    <div key={biz.id ?? i} className="bg-white/5 rounded-xl px-4 py-3 flex items-center gap-3 border border-white/5">
                      <div className="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center text-amber-400 text-sm font-bold flex-shrink-0">
                        {(biz.name ?? '?').charAt(0)}
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-semibold text-white truncate">{biz.name}</p>
                        <p className="text-xs text-white/40 capitalize">
                          {biz.plan ?? '—'} plan · {biz.created_at ? new Date(biz.created_at).toLocaleDateString('en-GB') : ''}
                        </p>
                      </div>
                      <span className={`badge text-xs ${biz.status === 'trial' ? 'bg-amber-900/50 text-amber-400' : 'bg-green-900/50 text-green-400'}`}>
                        {biz.status === 'trial' ? 'Trial' : 'Active'}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </>
        )}

        {/* Businesses tab */}
        {tab === 'businesses' && (
          <div className="bg-white/5 rounded-2xl border border-white/10 overflow-hidden">
            {loading ? (
              <div className="p-8 text-center text-white/30 text-sm">Loading...</div>
            ) : businesses.length === 0 ? (
              <div className="p-8 text-center">
                <Users className="w-10 h-10 text-white/20 mx-auto mb-3" />
                <p className="text-white/50 text-sm">No businesses found.</p>
              </div>
            ) : (
              <table className="w-full">
                <thead>
                  <tr className="border-b border-white/10">
                    <th className="text-left px-5 py-3 text-xs font-bold text-white/40 uppercase tracking-wide">Business</th>
                    <th className="text-left px-5 py-3 text-xs font-bold text-white/40 uppercase tracking-wide hidden sm:table-cell">Owner</th>
                    <th className="text-left px-5 py-3 text-xs font-bold text-white/40 uppercase tracking-wide hidden sm:table-cell">Plan</th>
                    <th className="text-left px-5 py-3 text-xs font-bold text-white/40 uppercase tracking-wide">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {businesses.map((biz) => (
                    <tr key={biz.id} className="border-b border-white/5 last:border-0 hover:bg-white/5 transition-colors">
                      <td className="px-5 py-3.5 text-sm font-semibold text-white">{biz.name}</td>
                      <td className="px-5 py-3.5 text-sm text-white/50 hidden sm:table-cell">{biz.owner_email ?? '—'}</td>
                      <td className="px-5 py-3.5 text-sm text-white/50 hidden sm:table-cell capitalize">{biz.plan ?? '—'}</td>
                      <td className="px-5 py-3.5">
                        <span className={`badge text-xs ${biz.status === 'trial' ? 'bg-amber-900/50 text-amber-400' : biz.status === 'active' ? 'bg-green-900/50 text-green-400' : 'bg-slate-800 text-slate-400'}`}>
                          {biz.status ?? 'unknown'}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}

        {/* Health tab */}
        {tab === 'health' && (
          <div className="space-y-4">
            {loading ? (
              [...Array(6)].map((_, i) => (
                <div key={i} className="bg-white/5 rounded-xl h-12 animate-pulse border border-white/5" />
              ))
            ) : health.length > 0 ? health.map((service) => (
              <div key={service.name} className="bg-white/5 rounded-xl px-4 py-3 flex items-center justify-between border border-white/5">
                <span className="text-sm font-semibold text-white">{service.name}</span>
                <div className="flex items-center gap-2">
                  <span className={`w-2 h-2 rounded-full ${service.healthy ? 'bg-green-500 animate-pulse' : 'bg-red-500'}`} />
                  <span className={`text-xs font-semibold ${service.healthy ? 'text-green-400' : 'text-red-400'}`}>
                    {service.healthy ? 'Operational' : service.message ?? 'Issue detected'}
                  </span>
                </div>
              </div>
            )) : (
              ['API server', 'Queue workers (Horizon)', 'Redis', 'Database (PostgreSQL)', 'DigitalOcean Spaces', 'Stripe webhooks'].map((name) => (
                <div key={name} className="bg-white/5 rounded-xl px-4 py-3 flex items-center justify-between border border-white/5">
                  <span className="text-sm font-semibold text-white">{name}</span>
                  <div className="flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
                    <span className="text-xs font-semibold text-green-400">Operational</span>
                  </div>
                </div>
              ))
            )}
          </div>
        )}

        <div className="border-t border-white/10 pt-6 text-center">
          <p className="text-xs text-white/20">postd.uk admin — Dijitul internal use only. Built in Mansfield, UK.</p>
        </div>
      </div>
    </div>
  )
}
