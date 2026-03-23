import { useState, useEffect, useRef, useCallback } from 'react'
import { Check, X, Edit2, ChevronDown, ChevronUp, CheckCircle2, Inbox, RefreshCw, RotateCcw, AlertCircle } from 'lucide-react'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'
import { postsApi } from '../../lib/api.js'

// Normalise backend platform IDs to frontend display IDs
function normalisePlatform(platform) {
  if (platform === 'google_business_profile') return 'google'
  if (platform === 'twitter') return 'x'
  return platform
}

function normalisePost(post) {
  return { ...post, platform: normalisePlatform(post.platform) }
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
function PostCard({ post, onApprove, onReject, onEdit, onRetry, showActions = true, actioning }) {
  const [expanded, setExpanded] = useState(false)
  const [swiping, setSwiping] = useState(null)
  const touchStart = useRef(null)

  const PREVIEW_LENGTH = 120
  const isLong = post.content.length > PREVIEW_LENGTH
  const displayContent = expanded || !isLong
    ? post.content
    : post.content.slice(0, PREVIEW_LENGTH) + '...'

  const handleTouchStart = (e) => {
    touchStart.current = e.touches[0].clientX
  }

  const handleTouchEnd = (e) => {
    if (!touchStart.current || !showActions || actioning) return
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

  const statusColour = {
    pending:   'bg-amber-100 text-amber-700',
    published: 'bg-green-100 text-green-700',
    posted:    'bg-green-100 text-green-700',
    approved:  'bg-blue-100 text-blue-700',
    rejected:  'bg-red-100 text-red-700',
    failed:    'bg-red-100 text-red-700',
    scheduled: 'bg-navy-100 text-navy-700',
  }

  const platformLabel = post.platform === 'x' ? 'X (Twitter)'
    : post.platform === 'google' ? 'Google Business'
    : post.platform.charAt(0).toUpperCase() + post.platform.slice(1)

  const dateStr = post.scheduled_at
    ? new Date(post.scheduled_at).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
    : ''

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
      {showActions && (
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
              <span className="text-sm font-bold text-navy-800">{platformLabel}</span>
              <span className={`badge text-2xs ${statusColour[post.status] ?? 'bg-slate-100 text-slate-600'}`}>
                {post.status.charAt(0).toUpperCase() + post.status.slice(1)}
              </span>
            </div>
            {dateStr && <p className="text-xs text-slate-400 mt-0.5">Scheduled: {dateStr}</p>}
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

        {showActions && post.status === 'pending' && (
          <div className="flex items-center gap-2 mt-4 pt-4 border-t border-cream-300">
            <button
              onClick={() => onApprove(post.id)}
              disabled={actioning}
              className="flex-1 flex items-center justify-center gap-1.5 bg-green-50 text-green-700 font-bold text-sm py-2.5 rounded-xl hover:bg-green-100 active:scale-[0.97] transition-all border border-green-200 disabled:opacity-50"
            >
              <Check className="w-4 h-4" />
              Approve
            </button>
            <button
              onClick={() => onEdit(post)}
              disabled={actioning}
              className="flex-1 flex items-center justify-center gap-1.5 bg-slate-50 text-slate-600 font-bold text-sm py-2.5 rounded-xl hover:bg-slate-100 active:scale-[0.97] transition-all border border-slate-200 disabled:opacity-50"
            >
              <Edit2 className="w-4 h-4" />
              Edit
            </button>
            <button
              onClick={() => onReject(post.id)}
              disabled={actioning}
              className="flex-1 flex items-center justify-center gap-1.5 bg-red-50 text-red-600 font-bold text-sm py-2.5 rounded-xl hover:bg-red-100 active:scale-[0.97] transition-all border border-red-200 disabled:opacity-50"
            >
              <X className="w-4 h-4" />
              Reject
            </button>
          </div>
        )}

        {post.status === 'failed' && (
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

// ── Empty state ────────────────────────────────────────────────────────────────
function EmptyInbox() {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <div className="w-20 h-20 bg-amber-50 rounded-3xl flex items-center justify-center mb-5">
        <CheckCircle2 className="w-10 h-10 text-amber-500" />
      </div>
      <h3 className="font-display font-bold text-lg text-navy-800 mb-2">You&apos;re all caught up!</h3>
      <p className="text-slate-500 text-sm max-w-xs leading-relaxed">
        No posts waiting for approval right now. We&apos;ll let you know as soon as new ones are ready.
      </p>
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
export default function InboxPage() {
  const [activeTab, setActiveTab] = useState('pending')
  const [pendingPosts, setPendingPosts] = useState([])
  const [allPosts, setAllPosts] = useState([])
  const [loadingPending, setLoadingPending] = useState(true)
  const [loadingAll, setLoadingAll] = useState(false)
  const [editingPost, setEditingPost] = useState(null)
  const [savingEdit, setSavingEdit] = useState(false)
  const [actioningId, setActioningId] = useState(null)
  const [error, setError] = useState(null)

  const loadPending = useCallback(async () => {
    setLoadingPending(true)
    setError(null)
    try {
      const res = await postsApi.getPending({ per_page: 50 })
      const raw = res.data?.data ?? res.data?.posts ?? []
      setPendingPosts(raw.map(normalisePost))
    } catch (e) {
      console.error('Failed to load inbox', e)
      setError('Failed to load posts. Please try again.')
    } finally {
      setLoadingPending(false)
    }
  }, [])

  const loadAll = useCallback(async () => {
    setLoadingAll(true)
    try {
      const res = await postsApi.getAll({ per_page: 50 })
      const raw = res.data?.data ?? res.data?.posts ?? []
      setAllPosts(raw.map(normalisePost))
    } catch (e) {
      console.error('Failed to load all posts', e)
    } finally {
      setLoadingAll(false)
    }
  }, [])

  useEffect(() => {
    loadPending()
  }, [loadPending])

  useEffect(() => {
    if (activeTab === 'all' && allPosts.length === 0 && !loadingAll) {
      loadAll()
    }
  }, [activeTab, allPosts.length, loadingAll, loadAll])

  const handleApprove = async (id) => {
    setActioningId(id)
    try {
      await postsApi.approve(id)
      setPendingPosts((prev) => prev.filter((p) => p.id !== id))
    } catch (e) {
      console.error('Approve failed', e)
    } finally {
      setActioningId(null)
    }
  }

  const handleReject = async (id) => {
    setActioningId(id)
    try {
      await postsApi.reject(id)
      setPendingPosts((prev) => prev.filter((p) => p.id !== id))
    } catch (e) {
      console.error('Reject failed', e)
    } finally {
      setActioningId(null)
    }
  }

  const handleRetry = async (id) => {
    setActioningId(id)
    try {
      await postsApi.retry(id)
      // Refresh the all-posts list to show updated status
      setAllPosts((prev) => prev.map((p) => p.id === id ? { ...p, status: 'scheduled', failure_reason: null } : p))
    } catch (e) {
      console.error('Retry failed', e)
    } finally {
      setActioningId(null)
    }
  }

  const handleSaveEdit = async (id, content) => {
    setSavingEdit(true)
    try {
      await postsApi.update(id, { content })
      await postsApi.approve(id)
      setPendingPosts((prev) => prev.filter((p) => p.id !== id))
      setEditingPost(null)
    } catch (e) {
      console.error('Save edit failed', e)
    } finally {
      setSavingEdit(false)
    }
  }

  const displayedPosts = activeTab === 'pending' ? pendingPosts : allPosts
  const loadingCurrent = activeTab === 'pending' ? loadingPending : loadingAll

  return (
    <div className="max-w-2xl mx-auto animate-fade-in-up">

      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between mb-1">
          <div className="flex items-center gap-3">
            <Inbox className="w-5 h-5 text-amber-500" />
            <h1 className="font-display font-black text-2xl text-navy-800">Post Inbox</h1>
          </div>
          <button
            onClick={loadPending}
            disabled={loadingPending}
            className="p-2 rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors disabled:opacity-40"
            title="Refresh"
          >
            <RefreshCw className={`w-4 h-4 ${loadingPending ? 'animate-spin' : ''}`} />
          </button>
        </div>
        <p className="text-slate-500 text-sm">Review and approve posts before they go live.</p>
      </div>

      {/* Error */}
      {error && (
        <div className="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}

      {/* Swipe hint — mobile */}
      {!loadingPending && pendingPosts.length > 0 && activeTab === 'pending' && (
        <div className="sm:hidden mb-4 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5 flex items-center gap-2 text-xs text-amber-700">
          <span>Swipe right to approve, left to reject</span>
        </div>
      )}

      {/* Tab bar */}
      <div className="flex items-center gap-1 bg-cream-300 p-1 rounded-xl mb-6">
        <button
          onClick={() => setActiveTab('pending')}
          className={`flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all duration-200 ${
            activeTab === 'pending'
              ? 'bg-white text-navy-800 shadow-sm'
              : 'text-slate-500 hover:text-navy-700'
          }`}
        >
          Pending
          {!loadingPending && pendingPosts.length > 0 && (
            <span className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500 text-white text-2xs font-bold">
              {pendingPosts.length}
            </span>
          )}
        </button>
        <button
          onClick={() => setActiveTab('all')}
          className={`flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all duration-200 ${
            activeTab === 'all'
              ? 'bg-white text-navy-800 shadow-sm'
              : 'text-slate-500 hover:text-navy-700'
          }`}
        >
          All posts
        </button>
      </div>

      {/* Post list */}
      {loadingCurrent ? (
        <div className="space-y-4">
          {[...Array(3)].map((_, i) => <SkeletonCard key={i} />)}
        </div>
      ) : displayedPosts.length === 0 ? (
        <EmptyInbox />
      ) : (
        <div className="space-y-4">
          {displayedPosts.map((post) => (
            <PostCard
              key={post.id}
              post={post}
              showActions={activeTab === 'pending' || post.status === 'failed'}
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
