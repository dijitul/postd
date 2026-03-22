import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Filter, ArrowRight, FileText } from 'lucide-react'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'

const ALL_POSTS = [
  { id: 1, platform: 'facebook', content: 'What a brilliant week it\'s been! Our team has been working incredibly hard...', status: 'published', scheduledAt: 'Today 2:30 PM' },
  { id: 2, platform: 'instagram', content: 'Sundays are made for this. Quality work, happy customers...', status: 'pending', scheduledAt: 'Today 4:00 PM' },
  { id: 3, platform: 'google', content: 'Thank you so much to everyone who has taken the time to leave us a review...', status: 'pending', scheduledAt: 'Tomorrow 9:00 AM' },
  { id: 4, platform: 'linkedin', content: 'Reflecting on Q1 and feeling genuinely grateful. Our team has grown...', status: 'approved', scheduledAt: 'Tomorrow 11:00 AM' },
  { id: 5, platform: 'facebook', content: 'Happy Monday! Here\'s to a great week ahead for everyone.', status: 'published', scheduledAt: 'Yesterday 9:00 AM' },
  { id: 6, platform: 'x', content: 'Quick update: we\'re open as usual this bank holiday. Come see us!', status: 'published', scheduledAt: '2 days ago' },
  { id: 7, platform: 'tiktok', content: '[Video script] Hook: "This is what we do when no one\'s watching..."', status: 'rejected', scheduledAt: '3 days ago' },
  { id: 8, platform: 'instagram', content: 'Behind the scenes at our workshop this afternoon. So much going on!', status: 'published', scheduledAt: '4 days ago' }
]

const STATUS_MAP = {
  published: { label: 'Published', cls: 'bg-green-100 text-green-700' },
  pending: { label: 'Pending', cls: 'bg-amber-100 text-amber-700' },
  approved: { label: 'Approved', cls: 'bg-blue-100 text-blue-700' },
  rejected: { label: 'Rejected', cls: 'bg-red-100 text-red-700' }
}

export default function PostsPage() {
  const [statusFilter, setStatusFilter] = useState('all')
  const [platformFilter, setPlatformFilter] = useState('all')

  const platforms = ['all', ...new Set(ALL_POSTS.map((p) => p.platform))]
  const statuses = ['all', 'pending', 'approved', 'published', 'rejected']

  const filtered = ALL_POSTS.filter((p) => {
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

      {/* Filters */}
      <div className="flex flex-wrap gap-3">
        <div className="flex items-center gap-2">
          <Filter className="w-4 h-4 text-slate-400" />
          <span className="text-xs font-semibold text-slate-500">Filter:</span>
        </div>
        <div className="flex gap-2 flex-wrap">
          {statuses.map((s) => (
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
            {p === 'all' ? 'All platforms' : p === 'x' ? 'X' : p === 'google' ? 'GBP' : p.charAt(0).toUpperCase() + p.slice(1)}
          </button>
        ))}
      </div>

      {/* List */}
      <div className="space-y-3">
        {filtered.length === 0 ? (
          <div className="text-center py-16 text-slate-400 text-sm">No posts match your filters.</div>
        ) : filtered.map((post) => {
          const s = STATUS_MAP[post.status] ?? { label: post.status, cls: 'bg-slate-100 text-slate-600' }
          return (
            <div key={post.id} className="bg-white rounded-2xl p-4 sm:p-5 border border-cream-300 flex items-start gap-4" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.04)' }}>
              <PlatformIcon platform={post.platform} size="sm" container="soft" className="flex-shrink-0 mt-0.5" />
              <div className="flex-1 min-w-0">
                <p className="text-sm text-slate-700 line-clamp-2 leading-relaxed mb-2">{post.content}</p>
                <div className="flex items-center gap-2 flex-wrap">
                  <span className={`badge text-2xs ${s.cls}`}>{s.label}</span>
                  <span className="text-xs text-slate-400">{post.scheduledAt}</span>
                </div>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
