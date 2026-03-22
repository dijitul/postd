import { useState } from 'react'
import { Link } from 'react-router-dom'
import {
  TrendingUp, Users, Zap, AlertTriangle, CheckCircle,
  DollarSign, BarChart3, Activity, ArrowUpRight, ArrowDownRight,
  ShieldCheck
} from 'lucide-react'
import Logo from '../../components/ui/Logo.jsx'

// Mock admin metrics
const METRICS = {
  mrr: 4680,
  mrrGrowth: 12.4,
  activeSubscribers: 98,
  trialUsers: 24,
  churnedThisMonth: 3,
  platformHealthPct: 94,
  postsGeneratedThisWeek: 2841,
  postSuccessRate: 97.8,
  avgApiCostPerBusiness: 2.40
}

const AT_RISK = [
  { id: 1, name: 'Smith & Co. Plumbers', plan: 'Starter', lastActive: '8 days ago', postsApproved: 2, risk: 'high' },
  { id: 2, name: 'Riverside Cafe', plan: 'Growth', lastActive: '5 days ago', postsApproved: 8, risk: 'medium' },
  { id: 3, name: 'AutoFix Mansfield', plan: 'Growth', lastActive: '4 days ago', postsApproved: 5, risk: 'medium' }
]

const RECENT_SIGNUPS = [
  { name: 'Oak Tree Garden Centre', plan: 'Growth', signedUp: '2 hours ago', status: 'trial' },
  { name: 'Nottingham Nails Studio', plan: 'Starter', signedUp: '1 day ago', status: 'active' },
  { name: 'Peak District Pods', plan: 'Pro', signedUp: '2 days ago', status: 'active' },
  { name: 'Sheffield Barbershop', plan: 'Growth', signedUp: '3 days ago', status: 'trial' }
]

function MetricCard({ label, value, sub, trend, trendPositive = true, icon: Icon, accent = false }) {
  return (
    <div className={`rounded-2xl p-5 border ${accent ? 'bg-amber-500 border-amber-600 text-white' : 'bg-white border-cream-300'}`}
      style={{ boxShadow: accent ? '0 8px 24px rgb(224 123 48 / 0.3)' : '0 2px 8px rgb(30 45 74 / 0.06)' }}>
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
      <div className={`font-display font-black text-2xl mb-0.5 ${accent ? 'text-white' : 'text-navy-800'}`}>{value}</div>
      <div className={`text-xs font-semibold ${accent ? 'text-white/70' : 'text-slate-500'}`}>{label}</div>
      {sub && <div className={`text-xs mt-0.5 ${accent ? 'text-white/60' : 'text-slate-400'}`}>{sub}</div>}
    </div>
  )
}

export default function AdminPage() {
  const [tab, setTab] = useState('overview')

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
        <Link to="/dashboard" className="text-xs text-white/40 hover:text-white/70 transition-colors">
          Exit admin
        </Link>
      </header>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        {/* Title */}
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
            {/* Key metrics */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <MetricCard label="Monthly Recurring Revenue" value={`£${METRICS.mrr.toLocaleString()}`} trend={METRICS.mrrGrowth} trendPositive icon={DollarSign} accent />
              <MetricCard label="Active subscribers" value={METRICS.activeSubscribers} sub={`+${METRICS.trialUsers} on trial`} icon={Users} />
              <MetricCard label="Churned this month" value={METRICS.churnedThisMonth} trendPositive={false} icon={AlertTriangle} />
              <MetricCard label="Posts this week" value={METRICS.postsGeneratedThisWeek.toLocaleString()} sub={`${METRICS.postSuccessRate}% success rate`} icon={Zap} />
            </div>

            <div className="grid sm:grid-cols-3 gap-4">
              <MetricCard label="Platform token health" value={`${METRICS.platformHealthPct}%`} sub="Connections with valid tokens" icon={Activity} />
              <MetricCard label="Avg API cost / business" value={`£${METRICS.avgApiCostPerBusiness.toFixed(2)}`} sub="OpenAI spend per month" icon={BarChart3} />
              <MetricCard label="Post success rate" value={`${METRICS.postSuccessRate}%`} sub="Posts delivered successfully" icon={CheckCircle} />
            </div>

            {/* At-risk list */}
            <div>
              <h2 className="font-display font-bold text-lg mb-4 text-white/90">At-risk businesses</h2>
              <div className="bg-white/5 rounded-2xl border border-white/10 overflow-hidden">
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
                    {AT_RISK.map((biz) => (
                      <tr key={biz.id} className="border-b border-white/5 last:border-0 hover:bg-white/5 transition-colors">
                        <td className="px-5 py-3.5 text-sm font-semibold text-white">{biz.name}</td>
                        <td className="px-5 py-3.5 text-sm text-white/50 hidden sm:table-cell">{biz.plan}</td>
                        <td className="px-5 py-3.5 text-sm text-white/50 hidden sm:table-cell">{biz.lastActive}</td>
                        <td className="px-5 py-3.5">
                          <span className={`badge text-xs ${biz.risk === 'high' ? 'bg-red-900/50 text-red-400' : 'bg-amber-900/50 text-amber-400'}`}>
                            {biz.risk === 'high' ? 'High risk' : 'Watch'}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Recent signups */}
            <div>
              <h2 className="font-display font-bold text-lg mb-4 text-white/90">Recent signups</h2>
              <div className="space-y-2">
                {RECENT_SIGNUPS.map((biz, i) => (
                  <div key={i} className="bg-white/5 rounded-xl px-4 py-3 flex items-center gap-3 border border-white/5">
                    <div className="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center text-amber-400 text-sm font-bold flex-shrink-0">
                      {biz.name.charAt(0)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-semibold text-white truncate">{biz.name}</p>
                      <p className="text-xs text-white/40">{biz.plan} plan · {biz.signedUp}</p>
                    </div>
                    <span className={`badge text-xs ${biz.status === 'trial' ? 'bg-amber-900/50 text-amber-400' : 'bg-green-900/50 text-green-400'}`}>
                      {biz.status === 'trial' ? 'Trial' : 'Active'}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </>
        )}

        {/* Businesses tab */}
        {tab === 'businesses' && (
          <div className="bg-white/5 rounded-2xl border border-white/10 p-8 text-center">
            <Users className="w-10 h-10 text-white/20 mx-auto mb-3" />
            <p className="text-white/50 text-sm">Full business management table — connect to real API to populate.</p>
          </div>
        )}

        {/* Health tab */}
        {tab === 'health' && (
          <div className="space-y-4">
            {['API server', 'Queue workers (Horizon)', 'Redis', 'Database (PostgreSQL)', 'DigitalOcean Spaces', 'Stripe webhooks'].map((service) => (
              <div key={service} className="bg-white/5 rounded-xl px-4 py-3 flex items-center justify-between border border-white/5">
                <span className="text-sm font-semibold text-white">{service}</span>
                <div className="flex items-center gap-2">
                  <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
                  <span className="text-xs font-semibold text-green-400">Operational</span>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Footer */}
        <div className="border-t border-white/10 pt-6 text-center">
          <p className="text-xs text-white/20">postd.uk admin — Dijitul internal use only. Built in Mansfield, UK.</p>
        </div>
      </div>
    </div>
  )
}
