import { useState } from 'react'
import { Link } from 'react-router-dom'
import { CheckCircle2, XCircle, RefreshCw, Lock, Plus, Share2 } from 'lucide-react'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'

// Mock platform data
const INITIAL_PLATFORMS = [
  {
    id: 'facebook',
    label: 'Facebook',
    status: 'connected',
    lastPost: '2 hours ago',
    account: 'Acme Plumbing Ltd',
    plan: 'starter'
  },
  {
    id: 'instagram',
    label: 'Instagram',
    status: 'connected',
    lastPost: '1 day ago',
    account: '@acmeplumbing',
    plan: 'starter'
  },
  {
    id: 'linkedin',
    label: 'LinkedIn',
    status: 'expired',
    lastPost: '5 days ago',
    account: 'Acme Plumbing Ltd',
    plan: 'growth'
  },
  {
    id: 'x',
    label: 'X (Twitter)',
    status: 'disconnected',
    lastPost: null,
    account: null,
    plan: 'growth'
  },
  {
    id: 'tiktok',
    label: 'TikTok',
    status: 'locked',
    lastPost: null,
    account: null,
    plan: 'pro'
  },
  {
    id: 'google',
    label: 'Google Business Profile',
    status: 'connected',
    lastPost: '3 hours ago',
    account: 'Acme Plumbing — Mansfield',
    plan: 'free',
    alwaysFree: true
  }
]

// User plan — would come from auth store in production
const USER_PLAN = 'growth'
const PLAN_ORDER = { free: 0, starter: 1, growth: 2, pro: 3 }

function StatusBadge({ status }) {
  const map = {
    connected: { label: 'Connected', cls: 'bg-green-100 text-green-700', Icon: CheckCircle2 },
    expired: { label: 'Token expired', cls: 'bg-amber-100 text-amber-700', Icon: RefreshCw },
    disconnected: { label: 'Not connected', cls: 'bg-slate-100 text-slate-500', Icon: XCircle },
    locked: { label: 'Upgrade to unlock', cls: 'bg-purple-100 text-purple-700', Icon: Lock }
  }
  const { label, cls, Icon } = map[status] ?? map.disconnected
  return (
    <span className={`badge text-xs ${cls}`}>
      <Icon className="w-3 h-3" />
      {label}
    </span>
  )
}

function PlatformCard({ platform, onConnect, onDisconnect, onReconnect }) {
  const [loading, setLoading] = useState(false)

  const isLocked = platform.plan !== 'free' && PLAN_ORDER[platform.plan] > PLAN_ORDER[USER_PLAN]

  const handleAction = async (action) => {
    setLoading(true)
    await new Promise((r) => setTimeout(r, 1200))
    action()
    setLoading(false)
  }

  return (
    <div className={`bg-white rounded-2xl p-5 sm:p-6 border transition-all duration-200 ${isLocked ? 'border-slate-200 opacity-75' : 'border-cream-300 hover:border-cream-400'}`}
      style={{ boxShadow: isLocked ? 'none' : '0 2px 8px rgb(30 45 74 / 0.06)' }}>

      {/* Header */}
      <div className="flex items-start gap-4 mb-4">
        <div className="relative flex-shrink-0">
          <PlatformIcon platform={platform.id} size="xl" container="soft" />
          {platform.status === 'connected' && (
            <span className="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-green-500 border-2 border-white flex items-center justify-center">
              <CheckCircle2 className="w-2.5 h-2.5 text-white" />
            </span>
          )}
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap mb-1">
            <h3 className="font-display font-bold text-base text-navy-800">{platform.label}</h3>
            {platform.alwaysFree && (
              <span className="badge-honey text-2xs">Always free</span>
            )}
          </div>
          <StatusBadge status={isLocked ? 'locked' : platform.status} />
        </div>
      </div>

      {/* Account details */}
      {platform.account && !isLocked && (
        <div className="bg-cream-200 rounded-xl px-3 py-2 mb-4">
          <p className="text-xs text-slate-500 mb-0.5">Connected account</p>
          <p className="text-sm font-semibold text-navy-800">{platform.account}</p>
        </div>
      )}

      {/* Last post */}
      {platform.lastPost && !isLocked && (
        <p className="text-xs text-slate-400 mb-4">Last post: {platform.lastPost}</p>
      )}

      {/* Action button */}
      {isLocked ? (
        <Link
          to="/billing"
          className="w-full flex items-center justify-center gap-2 bg-purple-50 text-purple-700 font-semibold text-sm py-2.5 rounded-xl border border-purple-200 hover:bg-purple-100 transition-all"
        >
          <Lock className="w-4 h-4" />
          Upgrade to unlock
        </Link>
      ) : platform.status === 'connected' ? (
        <button
          onClick={() => handleAction(() => onDisconnect(platform.id))}
          disabled={loading}
          className="w-full flex items-center justify-center gap-2 bg-slate-50 text-slate-600 font-semibold text-sm py-2.5 rounded-xl border border-slate-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all disabled:opacity-50"
        >
          {loading ? <span className="w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" /> : <XCircle className="w-4 h-4" />}
          {loading ? 'Disconnecting...' : 'Disconnect'}
        </button>
      ) : platform.status === 'expired' ? (
        <button
          onClick={() => handleAction(() => onReconnect(platform.id))}
          disabled={loading}
          className="w-full flex items-center justify-center gap-2 bg-amber-50 text-amber-700 font-semibold text-sm py-2.5 rounded-xl border border-amber-200 hover:bg-amber-100 transition-all disabled:opacity-50"
        >
          {loading ? <span className="w-4 h-4 border-2 border-amber-300 border-t-amber-600 rounded-full animate-spin" /> : <RefreshCw className="w-4 h-4" />}
          {loading ? 'Reconnecting...' : 'Reconnect'}
        </button>
      ) : (
        <button
          onClick={() => handleAction(() => onConnect(platform.id))}
          disabled={loading}
          className="w-full flex items-center justify-center gap-2 bg-navy-800 text-white font-semibold text-sm py-2.5 rounded-xl hover:bg-navy-700 active:scale-[0.98] transition-all disabled:opacity-50"
        >
          {loading ? <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : <Plus className="w-4 h-4" />}
          {loading ? 'Connecting...' : 'Connect'}
        </button>
      )}
    </div>
  )
}

export default function PlatformsPage() {
  const [platforms, setPlatforms] = useState(INITIAL_PLATFORMS)

  const handleConnect = (id) => {
    setPlatforms((prev) =>
      prev.map((p) => p.id === id ? { ...p, status: 'connected', account: 'Your Account' } : p)
    )
  }

  const handleDisconnect = (id) => {
    setPlatforms((prev) =>
      prev.map((p) => p.id === id ? { ...p, status: 'disconnected', account: null, lastPost: null } : p)
    )
  }

  const handleReconnect = (id) => {
    setPlatforms((prev) =>
      prev.map((p) => p.id === id ? { ...p, status: 'connected' } : p)
    )
  }

  const connectedCount = platforms.filter((p) => p.status === 'connected').length

  return (
    <div className="max-w-4xl mx-auto animate-fade-in-up space-y-6">

      {/* Header */}
      <div className="flex items-start justify-between">
        <div className="flex items-center gap-3">
          <Share2 className="w-5 h-5 text-amber-500" />
          <div>
            <h1 className="font-display font-black text-2xl text-navy-800">Platforms</h1>
            <p className="text-slate-500 text-sm mt-0.5">{connectedCount} of {platforms.length} platforms connected</p>
          </div>
        </div>
      </div>

      {/* Expired token alert */}
      {platforms.some((p) => p.status === 'expired') && (
        <div className="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-2xl p-4">
          <RefreshCw className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-bold text-amber-800">One or more platforms need reconnecting</p>
            <p className="text-xs text-amber-600 mt-0.5">Your access token has expired. Reconnect to resume posting.</p>
          </div>
        </div>
      )}

      {/* Platform grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {platforms.map((platform) => (
          <PlatformCard
            key={platform.id}
            platform={platform}
            onConnect={handleConnect}
            onDisconnect={handleDisconnect}
            onReconnect={handleReconnect}
          />
        ))}
      </div>

      {/* GBP note */}
      <div className="bg-navy-800 rounded-2xl p-5 text-white">
        <p className="font-display font-bold text-sm mb-1">Google Business Profile is always free</p>
        <p className="text-white/60 text-xs leading-relaxed">
          Unlike most competitors, we include Google Business Profile posting on every plan including your free trial.
          It&apos;s one of the most powerful ways to get found locally.
        </p>
      </div>
    </div>
  )
}
