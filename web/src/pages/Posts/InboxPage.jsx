import { useState, useRef } from 'react'
import { Check, X, Edit2, ChevronDown, ChevronUp, CheckCircle2, Inbox } from 'lucide-react'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'

// ── Mock data ─────────────────────────────────────────────────────────────────
const MOCK_PENDING = [
  {
    id: 1,
    platform: 'facebook',
    content: 'What a brilliant week it\'s been! Our team has been working incredibly hard to make sure every single customer leaves with a smile. We\'re so proud of what we do here, and it\'s all down to the amazing people we get to work with. Whether you\'ve been a customer for years or you\'re thinking of getting in touch for the first time, we\'d love to hear from you. What can we help you with today?',
    scheduledAt: 'Today at 2:30 PM',
    status: 'pending'
  },
  {
    id: 2,
    platform: 'instagram',
    content: 'Sundays are made for this. Quality work, happy customers, and a team that genuinely cares. Swipe to see what we\'ve been up to behind the scenes this week. Proud doesn\'t even cover it. #localBusiness #smallBusiness #Mansfield #community',
    scheduledAt: 'Today at 4:00 PM',
    status: 'pending'
  },
  {
    id: 3,
    platform: 'google',
    content: 'Thank you so much to everyone who has taken the time to leave us a review recently. We read every single one, and your kind words genuinely make our day. If you\'ve visited us recently and haven\'t left a review yet, we\'d be truly grateful — it means the world to a small business like ours.',
    scheduledAt: 'Tomorrow at 9:00 AM',
    status: 'pending'
  },
  {
    id: 4,
    platform: 'linkedin',
    content: 'Reflecting on Q1 and feeling genuinely grateful. Our team has grown, our processes have improved, and most importantly, our customers keep coming back. That\'s the real measure of success. Looking forward to sharing some exciting news in the coming weeks.',
    scheduledAt: 'Tomorrow at 11:00 AM',
    status: 'pending'
  }
]

const MOCK_ALL = [
  ...MOCK_PENDING,
  {
    id: 5,
    platform: 'facebook',
    content: 'Happy Monday! Here\'s to a great week ahead for everyone.',
    scheduledAt: 'Yesterday at 9:00 AM',
    status: 'published'
  },
  {
    id: 6,
    platform: 'x',
    content: 'Quick update: we\'re open as usual this bank holiday. Come see us!',
    scheduledAt: '2 days ago',
    status: 'published'
  }
]

// ── Edit modal ────────────────────────────────────────────────────────────────
function EditModal({ post, onSave, onClose }) {
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
            className="flex-1 flex items-center justify-center gap-2 bg-amber-500 text-white font-bold py-3 rounded-xl hover:bg-amber-700 active:scale-[0.98] transition-all"
          >
            <Check className="w-4 h-4" />
            Save and approve
          </button>
          <button onClick={onClose} className="flex-1 flex items-center justify-center gap-2 border border-slate-200 text-slate-600 font-semibold py-3 rounded-xl hover:bg-slate-50 transition-all">
            Cancel
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Post card ─────────────────────────────────────────────────────────────────
function PostCard({ post, onApprove, onReject, onEdit, showActions = true }) {
  const [expanded, setExpanded] = useState(false)
  const [swiping, setSwiping] = useState(null) // 'left' | 'right'
  const touchStart = useRef(null)
  const cardRef = useRef(null)

  const PREVIEW_LENGTH = 120
  const isLong = post.content.length > PREVIEW_LENGTH
  const displayContent = expanded || !isLong
    ? post.content
    : post.content.slice(0, PREVIEW_LENGTH) + '...'

  // Swipe gesture handling
  const handleTouchStart = (e) => {
    touchStart.current = e.touches[0].clientX
  }

  const handleTouchEnd = (e) => {
    if (!touchStart.current || !showActions) return
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
    pending: 'bg-amber-100 text-amber-700',
    published: 'bg-green-100 text-green-700',
    approved: 'bg-blue-100 text-blue-700',
    rejected: 'bg-red-100 text-red-700'
  }

  return (
    <div
      ref={cardRef}
      className={`bg-white rounded-2xl border border-cream-300 overflow-hidden transition-all duration-300 ${
        swiping === 'right' ? 'translate-x-24 opacity-0' :
        swiping === 'left' ? '-translate-x-24 opacity-0' : ''
      }`}
      style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.06)' }}
      onTouchStart={handleTouchStart}
      onTouchEnd={handleTouchEnd}
    >
      {/* Swipe hints */}
      {showActions && (
        <div className="flex items-center justify-between px-4 pt-3 pb-0 opacity-0 sm:hidden">
          <span className="text-xs text-green-600 font-semibold">Swipe right to approve</span>
          <span className="text-xs text-red-500 font-semibold">Swipe left to reject</span>
        </div>
      )}

      <div className="p-5">
        {/* Header */}
        <div className="flex items-center gap-3 mb-3">
          <PlatformIcon platform={post.platform} size="sm" container="soft" />
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="text-sm font-bold text-navy-800 capitalize">
                {post.platform === 'x' ? 'X (Twitter)' : post.platform === 'google' ? 'Google Business' : post.platform.charAt(0).toUpperCase() + post.platform.slice(1)}
              </span>
              <span className={`badge text-2xs ${statusColour[post.status] ?? 'bg-slate-100 text-slate-600'}`}>
                {post.status.charAt(0).toUpperCase() + post.status.slice(1)}
              </span>
            </div>
            <p className="text-xs text-slate-400 mt-0.5">Scheduled: {post.scheduledAt}</p>
          </div>
        </div>

        {/* Content */}
        <p className="text-sm text-slate-700 leading-relaxed whitespace-pre-wrap">{displayContent}</p>
        {isLong && (
          <button
            onClick={() => setExpanded(!expanded)}
            className="flex items-center gap-1 text-xs font-semibold text-amber-600 hover:text-amber-700 mt-2 transition-colors"
          >
            {expanded ? <><ChevronUp className="w-3.5 h-3.5" /> Show less</> : <><ChevronDown className="w-3.5 h-3.5" /> Show more</>}
          </button>
        )}

        {/* Actions */}
        {showActions && post.status === 'pending' && (
          <div className="flex items-center gap-2 mt-4 pt-4 border-t border-cream-300">
            <button
              onClick={() => onApprove(post.id)}
              className="flex-1 flex items-center justify-center gap-1.5 bg-green-50 text-green-700 font-bold text-sm py-2.5 rounded-xl hover:bg-green-100 active:scale-[0.97] transition-all border border-green-200"
            >
              <Check className="w-4 h-4" />
              Approve
            </button>
            <button
              onClick={() => onEdit(post)}
              className="flex-1 flex items-center justify-center gap-1.5 bg-slate-50 text-slate-600 font-bold text-sm py-2.5 rounded-xl hover:bg-slate-100 active:scale-[0.97] transition-all border border-slate-200"
            >
              <Edit2 className="w-4 h-4" />
              Edit
            </button>
            <button
              onClick={() => onReject(post.id)}
              className="flex-1 flex items-center justify-center gap-1.5 bg-red-50 text-red-600 font-bold text-sm py-2.5 rounded-xl hover:bg-red-100 active:scale-[0.97] transition-all border border-red-200"
            >
              <X className="w-4 h-4" />
              Reject
            </button>
          </div>
        )}
      </div>
    </div>
  )
}

// ── Empty state ───────────────────────────────────────────────────────────────
function EmptyInbox() {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <div className="w-20 h-20 bg-amber-50 rounded-3xl flex items-center justify-center mb-5 animate-bounce-subtle">
        <CheckCircle2 className="w-10 h-10 text-amber-500" />
      </div>
      <h3 className="font-display font-bold text-lg text-navy-800 mb-2">You&apos;re all caught up!</h3>
      <p className="text-slate-500 text-sm max-w-xs leading-relaxed">
        No posts waiting for approval right now. We&apos;ll let you know as soon as new ones are ready.
      </p>
    </div>
  )
}

// ── Main page ─────────────────────────────────────────────────────────────────
export default function InboxPage() {
  const [activeTab, setActiveTab] = useState('pending')
  const [posts, setPosts] = useState(MOCK_PENDING)
  const [allPosts] = useState(MOCK_ALL)
  const [editingPost, setEditingPost] = useState(null)

  const handleApprove = (id) => {
    setPosts((prev) => prev.filter((p) => p.id !== id))
  }

  const handleReject = (id) => {
    setPosts((prev) => prev.filter((p) => p.id !== id))
  }

  const handleSaveEdit = (id, content) => {
    setPosts((prev) => prev.filter((p) => p.id !== id))
    setEditingPost(null)
  }

  const displayedPosts = activeTab === 'pending' ? posts : allPosts

  return (
    <div className="max-w-2xl mx-auto animate-fade-in-up">

      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center gap-3 mb-1">
          <Inbox className="w-5 h-5 text-amber-500" />
          <h1 className="font-display font-black text-2xl text-navy-800">Post Inbox</h1>
        </div>
        <p className="text-slate-500 text-sm">Review and approve posts before they go live.</p>
      </div>

      {/* Swipe hint — mobile */}
      {posts.length > 0 && (
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
          {posts.length > 0 && (
            <span className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500 text-white text-2xs font-bold">
              {posts.length}
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
      {displayedPosts.length === 0 ? (
        <EmptyInbox />
      ) : (
        <div className="space-y-4">
          {displayedPosts.map((post) => (
            <PostCard
              key={post.id}
              post={post}
              showActions={activeTab === 'pending'}
              onApprove={handleApprove}
              onReject={handleReject}
              onEdit={setEditingPost}
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
        />
      )}
    </div>
  )
}
