import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import {
  FileText, Share2, Clock, AlertCircle, Plus, X,
  TrendingUp, ChevronRight, Zap, Send
} from 'lucide-react'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'
import useAuthStore from '../../stores/authStore.js'
import { postsApi, platformsApi } from '../../lib/api.js'

// ── Stat card ─────────────────────────────────────────────────────────────────
function StatCard({ icon: Icon, label, value, sub, colour = 'amber' }) {
  const colours = {
    amber: { bg: 'bg-amber-50', text: 'text-amber-600', icon: 'text-amber-500' },
    green: { bg: 'bg-green-50', text: 'text-green-600', icon: 'text-green-500' },
    navy:  { bg: 'bg-navy-50',  text: 'text-navy-600',  icon: 'text-navy-500'  }
  }
  const c = colours[colour] ?? colours.amber
  return (
    <div className="bg-white rounded-2xl p-5 border border-cream-300 flex items-center gap-4" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.06)' }}>
      <div className={`w-12 h-12 ${c.bg} rounded-xl flex items-center justify-center flex-shrink-0`}>
        <Icon className={`w-5 h-5 ${c.icon}`} />
      </div>
      <div>
        <div className="font-display font-black text-2xl text-navy-800 leading-none mb-0.5">{value}</div>
        <div className="text-xs font-semibold text-slate-500">{label}</div>
        {sub && <div className={`text-xs ${c.text} font-medium mt-0.5`}>{sub}</div>}
      </div>
    </div>
  )
}

// ── Platform health dot ───────────────────────────────────────────────────────
const STATUS_DOT = {
  green: 'bg-green-500',
  amber: 'bg-amber-500 animate-pulse',
  red:   'bg-red-500',
  grey:  'bg-slate-300'
}

function platformStatus(connection) {
  if (!connection) return 'grey'
  if (connection.is_expired) return 'amber'
  if (!connection.is_active) return 'red'
  return 'green'
}

// ── Post status badge ─────────────────────────────────────────────────────────
function StatusBadge({ status }) {
  const map = {
    published: 'bg-green-100 text-green-700',
    approved:  'bg-blue-100 text-blue-700',
    pending:   'bg-amber-100 text-amber-700',
    rejected:  'bg-red-100 text-red-700',
    scheduled: 'bg-navy-100 text-navy-700',
  }
  const labels = {
    published: 'Published', approved: 'Approved',
    pending: 'Pending', rejected: 'Rejected', scheduled: 'Scheduled',
  }
  return (
    <span className={`badge text-2xs ${map[status] ?? 'bg-slate-100 text-slate-600'}`}>
      {labels[status] ?? status}
    </span>
  )
}

// ── Post idea modal ───────────────────────────────────────────────────────────
function IdeaModal({ onClose }) {
  const [idea, setIdea] = useState('')
  const [submitted, setSubmitted] = useState(false)
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!idea.trim()) return
    setLoading(true)
    try {
      await postsApi.generate({ idea })
      setSubmitted(true)
      setTimeout(onClose, 2500)
    } catch {
      // Show submitted optimistically even on error
      setSubmitted(true)
      setTimeout(onClose, 2500)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4" aria-modal="true" role="dialog">
      <div className="absolute inset-0 bg-navy-800/50 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
      <div className="relative w-full max-w-lg bg-white rounded-3xl p-6 sm:p-7 border border-cream-300 animate-fade-in-up" style={{ boxShadow: '0 24px 48px rgb(30 45 74 / 0.2)' }}>
        <div className="flex items-start justify-between mb-5">
          <div>
            <h2 className="font-display font-bold text-lg text-navy-800">Drop an idea</h2>
            <p className="text-sm text-slate-500 mt-0.5">We&apos;ll turn it into posts for all your platforms.</p>
          </div>
          <button onClick={onClose} className="p-2 rounded-xl text-slate-400 hover:bg-slate-100 transition-colors">
            <X className="w-4 h-4" />
          </button>
        </div>

        {submitted ? (
          <div className="text-center py-6">
            <div className="w-12 h-12 bg-green-500 rounded-2xl flex items-center justify-center mx-auto mb-3">
              <Zap className="w-6 h-6 text-white" />
            </div>
            <p className="font-semibold text-navy-800">Idea received!</p>
            <p className="text-sm text-slate-500 mt-1">We&apos;re generating your posts now. Check your inbox shortly.</p>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            <textarea
              value={idea}
              onChange={(e) => setIdea(e.target.value)}
              placeholder="e.g. We're running a 10% off sale this weekend on all services..."
              className="input resize-none h-28"
              autoFocus
            />
            <button
              type="submit"
              disabled={!idea.trim() || loading}
              className="w-full flex items-center justify-center gap-2 bg-amber-500 text-white font-bold py-3 rounded-xl hover:bg-amber-700 active:scale-[0.98] transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              {loading ? <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : <Send className="w-4 h-4" />}
              {loading ? 'Generating...' : 'Generate posts from this idea'}
            </button>
          </form>
        )}
      </div>
    </div>
  )
}

// ── Main dashboard ────────────────────────────────────────────────────────────
const PLATFORM_IDS = ['facebook', 'instagram', 'linkedin', 'google', 'x', 'tiktok']
const BACKEND_MAP = { google: 'google_business_profile', x: 'twitter' }

export default function Dashboard() {
  const [ideaModalOpen, setIdeaModalOpen] = useState(false)
  const [summary, setSummary] = useState(null)
  const [recentPosts, setRecentPosts] = useState([])
  const [loading, setLoading] = useState(true)
  const { user } = useAuthStore()

  const businessName = user?.business?.name ?? user?.name ?? 'there'
  const hour = new Date().getHours()
  const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'
  const today = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })

  useEffect(() => {
    const load = async () => {
      try {
        const [connectionsRes, postsRes, pendingRes] = await Promise.all([
          platformsApi.getAll(),
          postsApi.getAll({ per_page: 5 }),
          postsApi.getPending({ per_page: 1 }),
        ])
        const connections = connectionsRes.data?.connections ?? []
        const posts = postsRes.data?.data ?? postsRes.data?.posts ?? []
        const pendingTotal = pendingRes.data?.total ?? pendingRes.data?.meta?.total ?? 0
        const nextPost = posts.find((p) => p.status === 'scheduled' || p.status === 'approved') ?? null

        setSummary({
          connections,
          pending_count: pendingTotal,
          posts_this_week: posts.filter((p) => {
            const d = new Date(p.created_at)
            const weekAgo = new Date(); weekAgo.setDate(weekAgo.getDate() - 7)
            return d > weekAgo
          }).length,
          connected_count: connections.filter((c) => c.is_active && !c.is_expired).length,
          next_post: nextPost,
        })
        setRecentPosts(posts)
      } catch (e) {
        console.error('Dashboard load failed', e)
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [])

  const connections = summary?.connections ?? []
  const getConnection = (frontendId) => {
    const backendId = BACKEND_MAP[frontendId] ?? frontendId
    return connections.find((c) => c.platform === backendId) ?? null
  }

  const pendingCount = summary?.pending_count ?? recentPosts.filter((p) => p.status === 'pending').length
  const postsThisWeek = summary?.posts_this_week ?? 0
  const connectedCount = summary?.connected_count ?? connections.filter((c) => c.is_active && !c.is_expired).length
  const nextPost = summary?.next_post ?? null

  return (
    <div className="space-y-6 max-w-5xl animate-fade-in-up">

      {/* ── Greeting ── */}
      <div className="flex items-end justify-between">
        <div>
          <h1 className="font-display font-black text-2xl sm:text-3xl text-navy-800">
            {greeting}, {businessName.split(' ')[0]}
          </h1>
          <p className="text-slate-500 text-sm mt-1">{today}</p>
        </div>
        <button
          onClick={() => setIdeaModalOpen(true)}
          className="hidden sm:flex items-center gap-2 bg-amber-500 text-white text-sm font-bold px-4 py-2.5 rounded-xl hover:bg-amber-700 active:scale-[0.97] transition-all"
        >
          <Plus className="w-4 h-4" /> Post idea
        </button>
      </div>

      {/* ── Inbox alert ── */}
      {pendingCount > 0 && (
        <Link
          to="/posts"
          className="flex items-center gap-4 bg-amber-50 border border-amber-200 rounded-2xl p-4 hover:bg-amber-100 transition-colors group"
        >
          <div className="w-10 h-10 bg-amber-500 rounded-xl flex items-center justify-center flex-shrink-0">
            <AlertCircle className="w-5 h-5 text-white" />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-bold text-amber-800">
              {pendingCount} post{pendingCount !== 1 ? 's' : ''} waiting for your approval
            </p>
            <p className="text-xs text-amber-600 mt-0.5">Tap to review and approve before they go live</p>
          </div>
          <ChevronRight className="w-5 h-5 text-amber-500 group-hover:translate-x-1 transition-transform flex-shrink-0" />
        </Link>
      )}

      {/* ── Stats row ── */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <StatCard icon={FileText} label="Posts this week" value={loading ? '—' : postsThisWeek} colour="amber" />
        <StatCard icon={Share2}   label="Platforms connected" value={loading ? '—' : connectedCount} sub="of 6 available" colour="green" />
        <StatCard
          icon={Clock}
          label="Next post"
          value={loading ? '—' : nextPost ? new Date(nextPost.scheduled_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : 'None scheduled'}
          sub={nextPost?.platform ?? ''}
          colour="navy"
        />
      </div>

      {/* ── Platform health ── */}
      <div className="bg-white rounded-2xl p-5 border border-cream-300" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.06)' }}>
        <div className="flex items-center justify-between mb-4">
          <h2 className="font-display font-bold text-base text-navy-800">Platform health</h2>
          <Link to="/platforms" className="text-xs font-semibold text-amber-600 hover:text-amber-700 transition-colors">Manage</Link>
        </div>
        <div className="grid grid-cols-3 sm:grid-cols-6 gap-3">
          {PLATFORM_IDS.map((id) => {
            const conn = getConnection(id)
            const status = loading ? 'grey' : platformStatus(conn)
            return (
              <div key={id} className="flex flex-col items-center gap-2">
                <div className="relative">
                  <PlatformIcon platform={id} size="lg" container="soft" />
                  <span
                    className={`absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white ${STATUS_DOT[status]}`}
                  />
                </div>
                <span className="text-xs text-slate-500">
                  {id === 'x' ? 'X' : id === 'google' ? 'GBP' : id.charAt(0).toUpperCase() + id.slice(1)}
                </span>
              </div>
            )
          })}
        </div>
      </div>

      {/* ── Recent posts ── */}
      <div className="bg-white rounded-2xl border border-cream-300 overflow-hidden" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.06)' }}>
        <div className="flex items-center justify-between px-5 py-4 border-b border-cream-300">
          <h2 className="font-display font-bold text-base text-navy-800">Recent posts</h2>
          <Link to="/posts" className="text-xs font-semibold text-amber-600 hover:text-amber-700 transition-colors">View all</Link>
        </div>

        {loading ? (
          <div className="divide-y divide-cream-300">
            {[...Array(3)].map((_, i) => (
              <div key={i} className="flex items-start gap-4 px-5 py-4">
                <div className="w-8 h-8 bg-cream-300 rounded-lg animate-pulse flex-shrink-0" />
                <div className="flex-1 space-y-2">
                  <div className="h-3 bg-cream-300 rounded animate-pulse w-3/4" />
                  <div className="h-3 bg-cream-300 rounded animate-pulse w-1/2" />
                </div>
              </div>
            ))}
          </div>
        ) : recentPosts.length === 0 ? (
          <div className="px-5 py-10 text-center">
            <TrendingUp className="w-8 h-8 text-slate-300 mx-auto mb-3" />
            <p className="text-sm text-slate-400">No posts yet — connect a platform and we&apos;ll get started!</p>
            <Link to="/platforms" className="inline-block mt-3 text-sm font-semibold text-amber-600 hover:text-amber-700">Connect a platform</Link>
          </div>
        ) : (
          <div className="divide-y divide-cream-300">
            {recentPosts.map((post) => (
              <div key={post.id} className="flex items-start gap-4 px-5 py-4 hover:bg-cream-200/50 transition-colors">
                <PlatformIcon
                  platform={post.platform === 'google_business_profile' ? 'google' : post.platform === 'twitter' ? 'x' : post.platform}
                  size="sm" container="soft" className="mt-0.5 flex-shrink-0"
                />
                <div className="flex-1 min-w-0">
                  <p className="text-sm text-slate-700 line-clamp-2 leading-relaxed">{post.content}</p>
                  <div className="flex items-center gap-2 mt-2">
                    <StatusBadge status={post.status} />
                    <span className="text-xs text-slate-400">
                      {post.scheduled_at ? new Date(post.scheduled_at).toLocaleDateString('en-GB') : ''}
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* ── Floating idea button — mobile only ── */}
      <button
        onClick={() => setIdeaModalOpen(true)}
        className="fixed bottom-20 right-4 sm:hidden z-30 flex items-center gap-2 bg-amber-500 text-white text-sm font-bold px-5 py-3.5 rounded-2xl shadow-xl hover:bg-amber-700 active:scale-[0.97] transition-all"
        style={{ boxShadow: '0 8px 24px rgb(224 123 48 / 0.4)' }}
      >
        <Plus className="w-5 h-5" /> Post idea
      </button>

      {ideaModalOpen && <IdeaModal onClose={() => setIdeaModalOpen(false)} />}
    </div>
  )
}
