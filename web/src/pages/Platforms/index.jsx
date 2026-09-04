import { useState, useEffect, useCallback } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { CheckCircle2, XCircle, RefreshCw, Lock, Plus, Share2, AlertCircle, CheckCheck } from 'lucide-react'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'
import { platformsApi } from '../../lib/api.js'

// Platform definitions — display config only, no mock status
// comingSoon = not yet available regardless of plan
// plan = 'pro' means requires Pro plan or TikTok add-on
const PLATFORM_DEFS = [
  { id: 'facebook',  backendId: 'facebook',               label: 'Facebook',                plan: 'base'       },
  { id: 'instagram', backendId: 'instagram',              label: 'Instagram',               plan: 'base'       },
  { id: 'linkedin',  backendId: 'linkedin',               label: 'LinkedIn',                plan: 'base'       },
  { id: 'x',        backendId: 'twitter',                 label: 'X (Twitter)',             plan: 'base'       },
  { id: 'tiktok',   backendId: 'tiktok',                  label: 'TikTok',                  plan: 'pro'        },
  { id: 'google',   backendId: 'google_business_profile', label: 'Google Business Profile', plan: 'base'       },
]

const PLAN_ORDER = { base: 0, starter: 0, growth: 0, pro: 3 }

function StatusBadge({ status }) {
  const map = {
    connected:    { label: 'Connected',        cls: 'bg-green-100 text-green-700',   Icon: CheckCircle2 },
    expired:      { label: 'Token expired',    cls: 'bg-amber-100 text-amber-700',   Icon: RefreshCw    },
    disconnected: { label: 'Not connected',    cls: 'bg-slate-100 text-slate-500',   Icon: XCircle      },
    locked:       { label: 'Upgrade to unlock',cls: 'bg-purple-100 text-purple-700', Icon: Lock         },
    coming_soon:  { label: 'Coming soon',      cls: 'bg-slate-100 text-slate-500',   Icon: Lock         },
  }
  const { label, cls, Icon } = map[status] ?? map.disconnected
  return (
    <span className={`badge text-xs ${cls}`}>
      <Icon className="w-3 h-3" />
      {label}
    </span>
  )
}

function PlatformCard({ def, connection, userPlanLevel, onConnect, onDisconnect, onReconnect, onSelectAccount }) {
  const [loading, setLoading] = useState(false)
  const [savingAccount, setSavingAccount] = useState(false)
  const [accountError, setAccountError] = useState(null)

  const isComingSoon = !!def.comingSoon
  const isLocked = !isComingSoon && def.plan === 'pro' && PLAN_ORDER[def.plan] > userPlanLevel
  // A lapsed access token is not a disconnection — we refresh those automatically.
  // Only a connection with no usable refresh token needs the user to act.
  const needsReconnect = connection?.needs_reconnect ?? connection?.is_expired ?? false
  const isConnected = !isComingSoon && !!connection && connection.is_active && !needsReconnect
  const isExpired = !isComingSoon && !!connection && needsReconnect

  const status = isComingSoon ? 'coming_soon' : isLocked ? 'locked' : isConnected ? 'connected' : isExpired ? 'expired' : 'disconnected'

  const accounts = connection?.accounts ?? []
  const connectedAccount = accounts.find((a) => a.is_selected) ?? accounts[0]

  const handleAccountChange = async (e) => {
    const accountId = e.target.value
    if (!accountId || accountId === connectedAccount?.id) return

    setSavingAccount(true)
    setAccountError(null)
    try {
      await platformsApi.selectAccount(connection.id, accountId)
      onSelectAccount(def.backendId, accountId)
    } catch (err) {
      console.error('Select account failed', err)
      setAccountError('Could not save. Please try again.')
    } finally {
      setSavingAccount(false)
    }
  }

  const handleConnect = async () => {
    setLoading(true)
    try {
      const res = await platformsApi.connect(def.backendId)
      if (res.data?.redirect_url) {
        window.location.href = res.data.redirect_url
      }
    } catch (e) {
      console.error('Connect failed', e)
    } finally {
      setLoading(false)
    }
  }

  const handleDisconnect = async () => {
    setLoading(true)
    try {
      await platformsApi.disconnect(connection.id)
      onDisconnect(def.backendId)
    } catch (e) {
      console.error('Disconnect failed', e)
    } finally {
      setLoading(false)
    }
  }

  const handleReconnect = async () => {
    setLoading(true)
    try {
      // Try token refresh first, fall back to full re-auth
      if (connection?.id) {
        try {
          await platformsApi.refreshToken(connection.id)
          onReconnect(def.backendId)
          setLoading(false)
          return
        } catch {
          // Fall through to full re-auth
        }
      }
      const res = await platformsApi.connect(def.backendId)
      if (res.data?.redirect_url) {
        window.location.href = res.data.redirect_url
      }
    } catch (e) {
      console.error('Reconnect failed', e)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div
      className={`bg-white rounded-2xl p-5 sm:p-6 border transition-all duration-200 ${
        isLocked || isComingSoon ? 'border-slate-200 opacity-70' : 'border-cream-300 hover:border-cream-400'
      }`}
      style={{ boxShadow: isLocked || isComingSoon ? 'none' : '0 2px 8px rgb(30 45 74 / 0.06)' }}
    >
      {/* Header */}
      <div className="flex items-start gap-4 mb-4">
        <div className="relative flex-shrink-0">
          <PlatformIcon platform={def.id} size="xl" container="soft" />
          {isConnected && (
            <span className="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-green-500 border-2 border-white flex items-center justify-center">
              <CheckCircle2 className="w-2.5 h-2.5 text-white" />
            </span>
          )}
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap mb-1">
            <h3 className="font-display font-bold text-base text-navy-800">{def.label}</h3>
          </div>
          <StatusBadge status={status} />
        </div>
      </div>

      {/* Connected account. With more than one page/profile available the user
          picks the posting target here, rather than us guessing for them. */}
      {connectedAccount && !isLocked && (
        <div className="bg-cream-200 rounded-xl px-3 py-2 mb-4">
          {accounts.length > 1 ? (
            <>
              <label
                htmlFor={`account-${def.id}`}
                className="text-xs text-slate-500 mb-1 block"
              >
                Posting to
              </label>
              <select
                id={`account-${def.id}`}
                value={connectedAccount.id}
                onChange={handleAccountChange}
                disabled={savingAccount}
                className="w-full text-sm font-semibold text-navy-800 bg-white border border-cream-400 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:opacity-50"
              >
                {accounts.map((a) => (
                  <option key={a.id} value={a.id}>{a.name}</option>
                ))}
              </select>
              {savingAccount && <p className="text-xs text-slate-400 mt-1">Saving...</p>}
              {accountError && <p className="text-xs text-red-600 mt-1">{accountError}</p>}
            </>
          ) : (
            <>
              <p className="text-xs text-slate-500 mb-0.5">Connected account</p>
              <p className="text-sm font-semibold text-navy-800">{connectedAccount.name}</p>
            </>
          )}
        </div>
      )}

      {/* Last used */}
      {connection?.last_used_at && !isLocked && (
        <p className="text-xs text-slate-400 mb-4">
          Last used: {new Date(connection.last_used_at).toLocaleDateString('en-GB')}
        </p>
      )}

      {/* Action button */}
      {isComingSoon ? (
        <div className="w-full flex items-center justify-center gap-2 bg-slate-50 text-slate-400 font-semibold text-sm py-2.5 rounded-xl border border-slate-200 cursor-not-allowed">
          Coming soon
        </div>
      ) : isLocked ? (
        <Link
          to="/billing"
          className="w-full flex items-center justify-center gap-2 bg-purple-50 text-purple-700 font-semibold text-sm py-2.5 rounded-xl border border-purple-200 hover:bg-purple-100 transition-all"
        >
          <Lock className="w-4 h-4" /> Upgrade to unlock
        </Link>
      ) : isConnected ? (
        <button
          onClick={handleDisconnect}
          disabled={loading}
          className="w-full flex items-center justify-center gap-2 bg-slate-50 text-slate-600 font-semibold text-sm py-2.5 rounded-xl border border-slate-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all disabled:opacity-50"
        >
          {loading ? <span className="w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" /> : <XCircle className="w-4 h-4" />}
          {loading ? 'Disconnecting...' : 'Disconnect'}
        </button>
      ) : isExpired ? (
        <button
          onClick={handleReconnect}
          disabled={loading}
          className="w-full flex items-center justify-center gap-2 bg-amber-50 text-amber-700 font-semibold text-sm py-2.5 rounded-xl border border-amber-200 hover:bg-amber-100 transition-all disabled:opacity-50"
        >
          {loading ? <span className="w-4 h-4 border-2 border-amber-300 border-t-amber-600 rounded-full animate-spin" /> : <RefreshCw className="w-4 h-4" />}
          {loading ? 'Reconnecting...' : 'Reconnect'}
        </button>
      ) : (
        <button
          onClick={handleConnect}
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
  const [connections, setConnections] = useState([])
  const [loading, setLoading] = useState(true)
  const [userPlanLevel, setUserPlanLevel] = useState(PLAN_ORDER.growth)
  const [searchParams, setSearchParams] = useSearchParams()

  const connectedPlatform = searchParams.get('connected')
  const oauthError = searchParams.get('error')

  const loadConnections = useCallback(async () => {
    try {
      const res = await platformsApi.getAll()
      setConnections(res.data?.connections ?? [])
    } catch (e) {
      console.error('Failed to load connections', e)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadConnections()
    // Clear URL params after reading them
    if (connectedPlatform || oauthError) {
      const t = setTimeout(() => setSearchParams({}, { replace: true }), 3000)
      return () => clearTimeout(t)
    }
  }, [loadConnections, connectedPlatform, oauthError, setSearchParams])

  const getConnection = (backendId) =>
    connections.find((c) => c.platform === backendId) ?? null

  const handleDisconnect = (backendId) => {
    setConnections((prev) => prev.filter((c) => c.platform !== backendId))
  }

  const handleReconnect = (backendId) => {
    setConnections((prev) =>
      prev.map((c) => c.platform === backendId ? { ...c, is_expired: false, needs_reconnect: false, is_active: true } : c)
    )
  }

  const handleSelectAccount = (backendId, accountId) => {
    setConnections((prev) =>
      prev.map((c) => c.platform === backendId
        ? { ...c, accounts: c.accounts.map((a) => ({ ...a, is_selected: a.id === accountId })) }
        : c
      )
    )
  }

  const connectedCount = PLATFORM_DEFS.filter((def) => {
    const conn = getConnection(def.backendId)
    return conn && conn.is_active && !(conn.needs_reconnect ?? conn.is_expired)
  }).length

  if (loading) {
    return (
      <div className="max-w-4xl mx-auto space-y-4">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="bg-white rounded-2xl h-32 border border-cream-300 animate-pulse" />
        ))}
      </div>
    )
  }

  return (
    <div className="max-w-4xl mx-auto animate-fade-in-up space-y-6">

      {/* Header */}
      <div className="flex items-start justify-between">
        <div className="flex items-center gap-3">
          <Share2 className="w-5 h-5 text-amber-500" />
          <div>
            <h1 className="font-display font-black text-2xl text-navy-800">Platforms</h1>
            <p className="text-slate-500 text-sm mt-0.5">{connectedCount} of {PLATFORM_DEFS.length} platforms connected</p>
          </div>
        </div>
      </div>

      {/* OAuth success banner */}
      {connectedPlatform && (
        <div className="flex items-center gap-3 bg-green-50 border border-green-200 rounded-2xl p-4">
          <CheckCheck className="w-5 h-5 text-green-600 flex-shrink-0" />
          <div>
            <p className="text-sm font-bold text-green-800">
              {PLATFORM_DEFS.find((d) => d.backendId === connectedPlatform)?.label ?? connectedPlatform} connected successfully!
            </p>
            <p className="text-xs text-green-600 mt-0.5">Your account is now active and ready to post.</p>
          </div>
        </div>
      )}

      {/* OAuth error banner */}
      {oauthError && (
        <div className="flex items-start gap-3 bg-red-50 border border-red-200 rounded-2xl p-4">
          <AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-bold text-red-800">
              {oauthError === 'linkedin_no_pages' ? 'No LinkedIn Company Page found' : 'Connection failed'}
            </p>
            <p className="text-xs text-red-600 mt-0.5">
              {oauthError === 'invalid_state' ? 'The authorisation request expired. Please try again.' :
               oauthError === 'oauth_failed' ? 'The platform rejected the authorisation. Please try again.' :
               oauthError === 'linkedin_no_pages' ? 'We post to LinkedIn Company Pages, and this account does not administer one. Ask to be made an admin of your business Page on LinkedIn, then connect again.' :
               'Something went wrong. Please try connecting again.'}
            </p>
          </div>
        </div>
      )}

      {/* Expired token alert */}
      {connections.some((c) => c.is_expired) && (
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
        {PLATFORM_DEFS.map((def) => (
          <PlatformCard
            key={def.id}
            def={def}
            connection={getConnection(def.backendId)}
            userPlanLevel={userPlanLevel}
            onDisconnect={handleDisconnect}
            onReconnect={handleReconnect}
            onSelectAccount={handleSelectAccount}
          />
        ))}
      </div>
    </div>
  )
}
