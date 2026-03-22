import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { Filter, ArrowRight, FileText } from 'lucide-react'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'
import { postsApi } from '../../lib/api.js'

const STATUS_MAP = {
  published: { label: 'Published', cls: 'bg-green-100 text-green-700' },
  pending:   { label: 'Pending',   cls: 'bg-amber-100 text-amber-700' },
  approved:  { label: 'Approved',  cls: 'bg-blue-100 text-blue-700'  },
  rejected:  { label: 'Rejected',  cls: 'bg-red-100 text-red-700'   },
  scheduled: { label: 'Scheduled', cls: 'bg-navy-100 text-navy-700'  },
}

// Normalise backend platform IDs to frontend display IDs
function normalisePlatform(platform) {
  if (platform === 'google_business_profile') return 'google'
  if (platform === 'twitter') return 'x'
  return platform
}

function platformLabel(p) {
  if (p === 'all') return 'All platforms'
  if (p === 'x') return 'X'
  if (p === 'google') return 'GBP'
  return p.charAt(0).toUpperCase() + p.slice(1)
}

const STATUSES = ['all', 'pending', 'approved', 'published', 'rejected', 'scheduled']
const KNOWN_PLATFORMS = ['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'google']

export default function PostsPage() {
  const [posts, setPosts] = useState([])
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState('all')
  const [platformFilter, setPlatformFilter] = useState('all')

  useEffect(() => {
    const load = async () => {
      try {
        const res = await postsApi.getAll({ per_page: 50 })
        const raw = res.data?.data ?? res.data?.posts ?? []
        setPosts(raw.map((p) => ({ ...p, platform: normalisePlatform(p.platform) })))
      } catch (e) {
        console.error('Failed to load posts', e)
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [])

  // Build platform list from actual posts, keeping only known ones
  const usedPlatforms = [...new Set(posts.map((p) => p.platform))].filter((p) => KNOWN_PLATFORMS.includes(p))
  const platforms = ['all', ...usedPlatforms]

  const filtered = posts.filter((p) => {
    const statusMatch = statusFilter === 'all' || p.status === statusFilter
    const platformMatch = platformFilter === 'all' || p.platform === platformFilter
    return statusMatch && platformMatch
  })

  return (
    <div className="max-w-3xl mx-auto animate-fade-in-up space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <FileText className="w-5 h-5 text-amber-500" />
          <h1 className="font-display font-black text-2xl text-navy-800">All Posts</h1>
        </div>
        <Link to="/posts/inbox" className="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-600 hover:text-amber-700 transition-colors">
          Inbox <ArrowRight className="w-4 h-4" />
        </Link>
      </div>

      {/* Status filters */}
      <div className="flex flex-wrap gap-3">
        <div className="flex items-center gap-2">
          <Filter className="w-4 h-4 text-slate-400" />
          <span className="text-xs font-semibold text-slate-500">Filter:</span>
        </div>
        <div className="flex gap-2 flex-wrap">
          {STATUSES.map((s) => (
            <button
              key={s}
              onClick={() => setStatusFilter(s)}
              className={`text-xs font-semibold px-3 py-1.5 rounded-full transition-all ${
                statusFilter === s
                  ? 'bg-amber-500 text-white'
                  : 'bg-white text-slate-600 border border-slate-200 hover:border-slate-300'
              }`}
            >
              {s === 'all' ? 'All statuses' : s.charAt(0).toUpperCase() + s.slice(1)}
            </button>
          ))}
        </div>
      </div>

      {/* Platform filter */}
      {!loading && platforms.length > 1 && (
        <div className="flex gap-2 flex-wrap">
          {platforms.map((p) => (
            <button
              key={p}
              onClick={() => setPlatformFilter(p)}
              className={`flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full transition-all ${
                platformFilter === p
                  ? 'bg-navy-800 text-white'
                  : 'bg-white text-slate-600 border border-slate-200 hover:border-slate-300'
              }`}
            >
              {p !== 'all' && <PlatformIcon platform={p} size="xs" branded />}
              {platformLabel(p)}
            </button>
          ))}
        </div>
      )}

      {/* List */}
      {loading ? (
        <div className="space-y-3">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="bg-white rounded-2xl p-4 sm:p-5 border border-cream-300 flex items-start gap-4" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.04)' }}>
              <div className="w-8 h-8 bg-cream-300 rounded-lg animate-pulse flex-shrink-0 mt-0.5" />
              <div className="flex-1 space-y-2">
                <div className="h-3 bg-cream-300 rounded animate-pulse w-3/4" />
                <div className="h-3 bg-cream-300 rounded animate-pulse w-1/2" />
                <div className="h-4 bg-cream-300 rounded-full animate-pulse w-20 mt-2" />
              </div>
            </div>
          ))}
        </div>
      ) : filtered.length === 0 ? (
        <div className="text-center py-16 text-slate-400 text-sm">
          {posts.length === 0 ? 'No posts yet — connect a platform and we\'ll get started!' : 'No posts match your filters.'}
        </div>
      ) : (
        <div className="space-y-3">
          {filtered.map((post) => {
            const s = STATUS_MAP[post.status] ?? { label: post.status, cls: 'bg-slate-100 text-slate-600' }
            const dateStr = post.scheduled_at
              ? new Date(post.scheduled_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
              : post.created_at
              ? new Date(post.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
              : ''
            return (
              <div key={post.id} className="bg-white rounded-2xl p-4 sm:p-5 border border-cream-300 flex items-start gap-4" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.04)' }}>
                <PlatformIcon platform={post.platform} size="sm" container="soft" className="flex-shrink-0 mt-0.5" />
                <div className="flex-1 min-w-0">
                  <p className="text-sm text-slate-700 line-clamp-2 leading-relaxed mb-2">{post.content}</p>
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className={`badge text-2xs ${s.cls}`}>{s.label}</span>
                    {dateStr && <span className="text-xs text-slate-400">{dateStr}</span>}
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
