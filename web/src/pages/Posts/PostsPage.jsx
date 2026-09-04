import { useState, useEffect, useRef, useCallback, useMemo } from 'react'
import {
  Check, X, Edit2, ChevronDown, ChevronUp, CheckCircle2,
  RefreshCw, RotateCcw, AlertCircle, FileText, ExternalLink
} from 'lucide-react'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'
import { postsApi, POSTS_CHANGED_EVENT } from '../../lib/api.js'

// ── Lifecycle tabs ─────────────────────────────────────────────────────────────
// One page, one nav entry. The only thing that differs between these views is
// where a post sits in its lifecycle, so they belong in tabs rather than in
// competing sidebar destinations.
const TABS = [
  { key: 'review',    label: 'Needs review', statuses: ['pending'] },
  { key: 'scheduled', label: 'Scheduled',    statuses: ['approved', 'scheduled', 'dispatching'] },
  { key: 'published', label: 'Published',    statuses: ['posted'] },
  { key: 'failed',    label: 'Failed',       statuses: ['failed'] },
  { key: 'all',       label: 'All',          statuses: null },
]

const STATUS_LABELS = {
  pending:     { label: 'Needs review', cls: 'bg-amber-100 text-amber-700' },
  approved:    { label: 'Approved',     cls: 'bg-blue-100 text-blue-700' },
  scheduled:   { label: 'Scheduled',    cls: 'bg-navy-100 text-navy-700' },
  dispatching: { label: 'Publishing',   cls: 'bg-navy-100 text-navy-700' },
  posted:      { label: 'Published',    cls: 'bg-green-100 text-green-700' },
  rejected:    { label: 'Rejected',     cls: 'bg-slate-100 text-slate-600' },
  failed:      { label: 'Failed',       cls: 'bg-red-100 text-red-700' },
}

const KNOWN_PLATFORMS = ['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'google']

// Normalise backend platform IDs to frontend display IDs
function normalisePlatform(platform) {
  if (platform === 'google_business_profile') return 'google'
  if (platform === 'twitter') return 'x'
  return platform
}

function normalisePost(post) {
  return { ...post, platform: normalisePlatform(post.platform) }
}

function platformLabel(p) {
  if (p === 'all') return 'All platforms'
  if (p === 'x') return 'X (Twitter)'
  if (p === 'google') return 'Google Business'
  return p.charAt(0).toUpperCase() + p.slice(1)
}

// ── Edit modal ─────────────────────────────────────────────────────────────────
function EditModal({ post, onSave, onClose, saving }) {
  const [content, setContent] = useState(post.content)

  return (
    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-navy-800/50 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
      <div className="relative w-full max-w-lg bg-white rounded-3xl p-6 sm:p-7 border border-cream-300 animate-fade-in-up" style={{ boxShadow: '0 24px 48px rgb(30 45 74 / 0.2)' }}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-3">
            <PlatformIcon platform={post.platform} size="sm" container="soft" />
            <h2 className="font-display font-bold text-lg text-navy-800">Edit post</h2>
          </div>
          <button onClick={onClose} className="p-2 rounded-xl text-slate-400 hover:bg-slate-100 transition-colors">
            <X className="w-4 h-4" />
          </button>
        </div>
        <textarea
          value={content}
          onChange={(e) => setContent(e.target.value)}
          className="input resize-none h-40 mb-4"
          autoFocus
        />
        <div className="flex items-center gap-3">
          <button
            onClick={() => onSave(post.id, content)}
            disabled={saving || !content.trim()}
            className="flex-1 flex items-center justify-center gap-2 bg-amber-500 text-white font-bold py-3 rounded-xl hover:bg-amber-700 active:scale-[0.98] transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {saving
              ? <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              : <Check className="w-4 h-4" />
            }
            {saving ? 'Saving...' : 'Save and approve'}
          </button>
          <button
            onClick={onClose}
            disabled={saving}
            className="flex-1 flex items-center justify-center gap-2 border border-slate-200 text-slate-600 font-semibold py-3 rounded-xl hover:bg-slate-50 transition-all disabled:opacity-50"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Post card ──────────────────────────────────────────────────────────────────
function PostCard({ post, onApprove, onReject, onEdit, onRetry, actioning }) {
  const [expanded, setExpanded] = useState(false)
  const [swiping, setSwiping] = useState(null)
  const touchStart = useRef(null)

  // Actions are driven by the post's own status, not by which tab you are on,
  // so the same post always offers the same things wherever you find it.
  const canReview = post.status === 'pending'
  const canRetry = post.status === 'failed'
  // Editing is not the same thing as reviewing. The API accepts edits to pending,
  // approved and scheduled posts alike, but the button lived inside the review
  // block, so an approved post could not be corrected without rejecting it first.
  const canEdit = ['pending', 'approved', 'scheduled'].includes(post.status)

  const PREVIEW_LENGTH = 120
  const isLong = post.content.length > PREVIEW_LENGTH
  const displayContent = expanded || !isLong
    ? post.content
    : post.content.slice(0, PREVIEW_LENGTH) + '...'

  const handleTouchStart = (e) => {
    touchStart.current = e.touches[0].clientX
  }

  const handleTouchEnd = (e) => {
    if (!touchStart.current || !canReview || actioning) return
    const diff = e.changedTouches[0].clientX - touchStart.current
    if (diff > 80) {
      setSwiping('right')
      setTimeout(() => { onApprove(post.id); setSwiping(null) }, 300)
    } else if (diff < -80) {
      setSwiping('left')
      setTimeout(() => { onReject(post.id); setSwiping(null) }, 300)
    }
    touchStart.current = null
  }

  const status = STATUS_LABELS[post.status] ?? { label: post.status, cls: 'bg-slate-100 text-slate-600' }

  const dateStr = post.scheduled_at
    ? new Date(post.scheduled_at).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
    : ''
  const dateLabel = post.status === 'posted' ? 'Published' : 'Scheduled'

  return (
    <div
      className={`bg-white rounded-2xl border border-cream-300 overflow-hidden transition-all duration-300 ${
        swiping === 'right' ? 'translate-x-24 opacity-0' :
        swiping === 'left'  ? '-translate-x-24 opacity-0' : ''
      }`}
      style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.06)' }}
      onTouchStart={handleTouchStart}
      onTouchEnd={handleTouchEnd}
    >
      {canReview && (
        <div className="flex items-center justify-between px-4 pt-3 pb-0 opacity-40 sm:hidden">
          <span className="text-xs text-green-600 font-semibold">Swipe right to approve</span>
          <span className="text-xs text-red-500 font-semibold">Swipe left to reject</span>
        </div>
      )}

      <div className="p-5">
        <div className="flex items-center gap-3 mb-3">
          <PlatformIcon platform={post.platform} size="sm" container="soft" />
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="text-sm font-bold text-navy-800">{platformLabel(post.platform)}</span>
              <span className={`badge text-2xs ${status.cls}`}>{status.label}</span>
            </div>
            {dateStr && <p className="text-xs text-slate-400 mt-0.5">{dateLabel}: {dateStr}</p>}
          </div>
        </div>

        <p className="text-sm text-slate-700 leading-relaxed whitespace-pre-wrap">{displayContent}</p>
        {isLong && (
          <button
            onClick={() => setExpanded(!expanded)}
            className="flex items-center gap-1 text-xs font-semibold text-amber-600 hover:text-amber-700 mt-2 transition-colors"
          >
            {expanded
              ? <><ChevronUp className="w-3.5 h-3.5" /> Show less</>
              : <><ChevronDown className="w-3.5 h-3.5" /> Show more</>
            }
          </button>
        )}

        {post.status === 'posted' && post.platform_post_url && (
          <a
            href={post.platform_post_url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-600 hover:text-amber-700 mt-3 transition-colors"
          >
            <ExternalLink className="w-3.5 h-3.5" />
            View on {platformLabel(post.platform)}
          </a>
        )}

        {(canReview || canEdit) && (
          <div className="flex items-center gap-2 mt-4 pt-4 border-t border-cream-300">
            {canReview && (
              <button
                onClick={() => onApprove(post.id)}
                disabled={actioning}
                className="flex-1 flex items-center justify-center gap-1.5 bg-green-50 text-green-700 font-bold text-sm py-2.5 rounded-xl hover:bg-green-100 active:scale-[0.97] transition-all border border-green-200 disabled:opacity-50"
              >
                <Check className="w-4 h-4" />
                Approve
              </button>
            )}
            {canEdit && (
              <button
                onClick={() => onEdit(post)}
                disabled={actioning}
                className="flex-1 flex items-center justify-center gap-1.5 bg-slate-50 text-slate-600 font-bold text-sm py-2.5 rounded-xl hover:bg-slate-100 active:scale-[0.97] transition-all border border-slate-200 disabled:opacity-50"
              >
                <Edit2 className="w-4 h-4" />
                Edit
              </button>
            )}
            {canReview && (
              <button
                onClick={() => onReject(post.id)}
                disabled={actioning}
                className="flex-1 flex items-center justify-center gap-1.5 bg-red-50 text-red-600 font-bold text-sm py-2.5 rounded-xl hover:bg-red-100 active:scale-[0.97] transition-all border border-red-200 disabled:opacity-50"
              >
                <X className="w-4 h-4" />
                Reject
              </button>
            )}
          </div>
        )}

        {canRetry && (
          <div className="mt-4 pt-4 border-t border-cream-300 space-y-3">
            {post.failure_reason && (
              <div className="flex items-start gap-2 bg-red-50 border border-red-200 rounded-xl px-3 py-2.5">
                <AlertCircle className="w-3.5 h-3.5 text-red-500 flex-shrink-0 mt-0.5" />
                <p className="text-xs text-red-700 leading-relaxed">{post.failure_reason}</p>
              </div>
            )}
            <button
              onClick={() => onRetry(post.id)}
              disabled={actioning}
              className="w-full flex items-center justify-center gap-1.5 bg-amber-50 text-amber-700 font-bold text-sm py-2.5 rounded-xl hover:bg-amber-100 active:scale-[0.97] transition-all border border-amber-200 disabled:opacity-50"
            >
              {actioning
                ? <span className="w-4 h-4 border-2 border-amber-300 border-t-amber-600 rounded-full animate-spin" />
                : <RotateCcw className="w-4 h-4" />
              }
              {actioning ? 'Retrying...' : 'Retry post'}
            </button>
          </div>
        )}
      </div>
    </div>
  )
}

// ── Empty states ───────────────────────────────────────────────────────────────
function EmptyState({ tab, filtered }) {
  if (filtered) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-center">
        <div className="w-16 h-16 bg-cream-300 rounded-3xl flex items-center justify-center mb-4">
          <FileText className="w-8 h-8 text-slate-400" />
        </div>
        <p className="text-slate-500 text-sm">No posts match your filters.</p>
      </div>
    )
  }

  const copy = {
    review:    { title: "You're all caught up!", body: "No posts waiting for approval right now. We'll let you know as soon as new ones are ready." },
    scheduled: { title: 'Nothing scheduled',     body: 'Approved posts waiting to go out will appear here.' },
    published: { title: 'Nothing published yet', body: 'Once a post goes live, it will show up here with a link to it.' },
    failed:    { title: 'No failures',           body: 'Posts that could not be published will appear here so you can retry them.' },
    all:       { title: 'No posts yet',          body: "Connect a platform and we'll start writing posts for you." },
  }[tab] ?? { title: 'Nothing here', body: '' }

  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <div className="w-20 h-20 bg-amber-50 rounded-3xl flex items-center justify-center mb-5">
        <CheckCircle2 className="w-10 h-10 text-amber-500" />
      </div>
      <h3 className="font-display font-bold text-lg text-navy-800 mb-2">{copy.title}</h3>
      <p className="text-slate-500 text-sm max-w-xs leading-relaxed">{copy.body}</p>
    </div>
  )
}

// ── Skeleton loader ────────────────────────────────────────────────────────────
function SkeletonCard() {
  return (
    <div className="bg-white rounded-2xl border border-cream-300 p-5" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.06)' }}>
      <div className="flex items-center gap-3 mb-3">
        <div className="w-8 h-8 bg-cream-300 rounded-lg animate-pulse flex-shrink-0" />
        <div className="flex-1 space-y-1.5">
          <div className="h-3 bg-cream-300 rounded animate-pulse w-1/3" />
          <div className="h-2.5 bg-cream-300 rounded animate-pulse w-1/4" />
        </div>
      </div>
      <div className="space-y-2">
        <div className="h-3 bg-cream-300 rounded animate-pulse" />
        <div className="h-3 bg-cream-300 rounded animate-pulse w-5/6" />
        <div className="h-3 bg-cream-300 rounded animate-pulse w-3/4" />
      </div>
    </div>
  )
}

// ── Main page ──────────────────────────────────────────────────────────────────
export default function PostsPage() {
  const [posts, setPosts] = useState([])
  const [loading, setLoading] = useState(true)
  const [activeTab, setActiveTab] = useState(null) // resolved after first load
  const [platformFilter, setPlatformFilter] = useState('all')
  const [editingPost, setEditingPost] = useState(null)
  const [savingEdit, setSavingEdit] = useState(false)
  const [actioningId, setActioningId] = useState(null)
  const [error, setError] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await postsApi.getAll({ per_page: 100 })
      const raw = res.data?.data ?? res.data?.posts ?? []
      setPosts(raw.map(normalisePost))
    } catch (e) {
      console.error('Failed to load posts', e)
      setError('Failed to load posts. Please try again.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load() }, [load])

  const countFor = useCallback(
    (tab) => tab.statuses === null
      ? posts.length
      : posts.filter((p) => tab.statuses.includes(p.status)).length,
    [posts]
  )

  // Open on the week ahead. This used to land on whatever needed attention, which
  // meant the page you saw changed day to day and the schedule, the thing you
  // actually come here to sense check, was never the first thing shown.
  //
  // Falling back matters on a fresh account: until posts are approved they are all
  // pending, so opening straight onto Scheduled would show an empty page to
  // someone who has a full week sitting in review.
  useEffect(() => {
    if (loading || activeTab !== null) return
    const firstWithPosts = ['scheduled', 'review', 'failed', 'all']
      .map((key) => TABS.find((t) => t.key === key))
      .find((t) => countFor(t) > 0)
    setActiveTab(firstWithPosts?.key ?? 'scheduled')
  }, [loading, activeTab, posts, countFor])

  const tab = TABS.find((t) => t.key === activeTab) ?? TABS[TABS.length - 1]

  const usedPlatforms = useMemo(
    () => [...new Set(posts.map((p) => p.platform))].filter((p) => KNOWN_PLATFORMS.includes(p)),
    [posts]
  )

  // Upcoming posts read best soonest first, so the next thing to go out is at the
  // top and the far end of the week is at the bottom. History reads the other way
  // round: the most recent thing published belongs at the top, not the oldest.
  const showsFuture = ['review', 'scheduled'].includes(tab.key)

  const visible = posts
    .filter((p) => {
      const statusMatch = tab.statuses === null || tab.statuses.includes(p.status)
      const platformMatch = platformFilter === 'all' || p.platform === platformFilter
      return statusMatch && platformMatch
    })
    .sort((a, b) => {
      // Undated posts sink to the bottom either way rather than sorting as 1970.
      const at = a.scheduled_at ? new Date(a.scheduled_at).getTime() : null
      const bt = b.scheduled_at ? new Date(b.scheduled_at).getTime() : null
      if (at === null && bt === null) return 0
      if (at === null) return 1
      if (bt === null) return -1
      return showsFuture ? at - bt : bt - at
    })

  const inTabBeforePlatformFilter = tab.statuses === null
    ? posts.length
    : posts.filter((p) => tab.statuses.includes(p.status)).length

  // Let the sidebar badge know the pending count has moved.
  const announceChange = () => window.dispatchEvent(new Event(POSTS_CHANGED_EVENT))

  const applyStatus = (id, changes) => {
    setPosts((prev) => prev.map((p) => (p.id === id ? { ...p, ...changes } : p)))
    announceChange()
  }

  const handleApprove = async (id) => {
    setActioningId(id)
    try {
      await postsApi.approve(id)
      applyStatus(id, { status: 'scheduled' })
    } catch (e) {
      console.error('Approve failed', e)
      setError('Could not approve that post. Please try again.')
    } finally {
      setActioningId(null)
    }
  }

  const handleReject = async (id) => {
    setActioningId(id)
    try {
      await postsApi.reject(id)
      applyStatus(id, { status: 'rejected' })
    } catch (e) {
      console.error('Reject failed', e)
      setError('Could not reject that post. Please try again.')
    } finally {
      setActioningId(null)
    }
  }

  const handleRetry = async (id) => {
    setActioningId(id)
    try {
      await postsApi.retry(id)
      applyStatus(id, { status: 'scheduled', failure_reason: null })
    } catch (e) {
      console.error('Retry failed', e)
      setError('Could not retry that post. Please try again.')
    } finally {
      setActioningId(null)
    }
  }

  const handleSaveEdit = async (id, content) => {
    setSavingEdit(true)
    try {
      await postsApi.update(id, { content })
      await postsApi.approve(id)
      applyStatus(id, { status: 'scheduled', content })
      setEditingPost(null)
    } catch (e) {
      console.error('Save edit failed', e)
      setError('Could not save that post. Please try again.')
    } finally {
      setSavingEdit(false)
    }
  }

  return (
    <div className="max-w-2xl mx-auto animate-fade-in-up">

      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between mb-1">
          <div className="flex items-center gap-3">
            <FileText className="w-5 h-5 text-amber-500" />
            <h1 className="font-display font-black text-2xl text-navy-800">Posts</h1>
          </div>
          <button
            onClick={load}
            disabled={loading}
            className="p-2 rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors disabled:opacity-40"
            title="Refresh"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
        <p className="text-slate-500 text-sm">Review what we have written, and see what has gone out.</p>
      </div>

      {/* Error */}
      {error && (
        <div className="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}

      {/* Lifecycle tabs */}
      <div className="flex items-center gap-1 bg-cream-300 p-1 rounded-xl mb-4 overflow-x-auto">
        {TABS.map((t) => {
          const count = countFor(t)
          const isActive = t.key === tab.key
          return (
            <button
              key={t.key}
              onClick={() => setActiveTab(t.key)}
              className={`flex-1 flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-lg text-sm font-semibold whitespace-nowrap transition-all duration-200 ${
                isActive ? 'bg-white text-navy-800 shadow-sm' : 'text-slate-500 hover:text-navy-700'
              }`}
            >
              {t.label}
              {!loading && count > 0 && t.key !== 'all' && (
                <span className={`inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-2xs font-bold ${
                  t.key === 'failed' ? 'bg-red-500 text-white'
                    : t.key === 'review' ? 'bg-amber-500 text-white'
                    : 'bg-slate-200 text-slate-600'
                }`}>
                  {count}
                </span>
              )}
            </button>
          )
        })}
      </div>

      {/* Platform filter — only worth showing once more than one is connected */}
      {usedPlatforms.length > 1 && (
        <div className="flex items-center gap-2 mb-6 overflow-x-auto pb-1">
          {['all', ...usedPlatforms].map((p) => (
            <button
              key={p}
              onClick={() => setPlatformFilter(p)}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all ${
                platformFilter === p
                  ? 'bg-navy-800 text-white'
                  : 'bg-white text-slate-600 border border-cream-300 hover:bg-cream-300'
              }`}
            >
              {p !== 'all' && <PlatformIcon platform={p} size="xs" />}
              {platformLabel(p)}
            </button>
          ))}
        </div>
      )}

      {/* Swipe hint — mobile, only where swiping does anything */}
      {!loading && tab.key === 'review' && visible.length > 0 && (
        <div className="sm:hidden mb-4 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5 flex items-center gap-2 text-xs text-amber-700">
          <span>Swipe right to approve, left to reject</span>
        </div>
      )}

      {/* Post list */}
      {loading ? (
        <div className="space-y-4">
          {[...Array(3)].map((_, i) => <SkeletonCard key={i} />)}
        </div>
      ) : visible.length === 0 ? (
        <EmptyState tab={tab.key} filtered={inTabBeforePlatformFilter > 0} />
      ) : (
        <div className="space-y-4">
          {visible.map((post) => (
            <PostCard
              key={post.id}
              post={post}
              onApprove={handleApprove}
              onReject={handleReject}
              onEdit={setEditingPost}
              onRetry={handleRetry}
              actioning={actioningId === post.id}
            />
          ))}
        </div>
      )}

      {/* Edit modal */}
      {editingPost && (
        <EditModal
          post={editingPost}
          onSave={handleSaveEdit}
          onClose={() => setEditingPost(null)}
          saving={savingEdit}
        />
      )}
    </div>
  )
}
