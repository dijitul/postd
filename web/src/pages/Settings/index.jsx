import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import {
  Settings, User, Bell, Shield, Trash2, Check, AlertCircle,
  ChevronRight, Share2, RefreshCw, CheckCircle2, XCircle, Lock
} from 'lucide-react'
import useAuthStore from '../../stores/authStore.js'
import { settingsApi, platformsApi } from '../../lib/api.js'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'

// ── Constants ─────────────────────────────────────────────────────────────────

const INDUSTRIES = [
  'Restaurant / Cafe', 'Retail', 'Trades (Builder, Plumber, Electrician etc.)',
  'Professional Services', 'Health & Beauty', 'Automotive', 'Fitness & Wellbeing',
  'Property', 'Education', 'Other'
]

const TONES = ['professional', 'friendly', 'casual']

const PLATFORM_DEFS = [
  { id: 'facebook',  backendId: 'facebook',               label: 'Facebook' },
  { id: 'instagram', backendId: 'instagram',              label: 'Instagram' },
  { id: 'linkedin',  backendId: 'linkedin',               label: 'LinkedIn' },
  { id: 'x',        backendId: 'twitter',                 label: 'X (Twitter)' },
  { id: 'tiktok',   backendId: 'tiktok',                  label: 'TikTok' },
  { id: 'google',   backendId: 'google_business_profile', label: 'Google Business Profile' },
]

const businessSchema = z.object({
  business_name:      z.string().min(2, 'Business name is required'),
  industry:           z.string().min(1, 'Please choose your industry'),
  website_url:        z.string().url('Please enter a valid URL (include https://)').optional().or(z.literal('')),
  google_reviews_url: z.string().url('Please enter a valid URL').optional().or(z.literal('')),
  tone:               z.enum(['professional', 'friendly', 'casual'])
})

// ── Shared UI ─────────────────────────────────────────────────────────────────

function SectionCard({ title, icon: Icon, children }) {
  return (
    <div className="bg-white rounded-2xl border border-cream-300 overflow-hidden" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.05)' }}>
      <div className="flex items-center gap-3 px-5 py-4 border-b border-cream-300 bg-cream-200/40">
        <Icon className="w-4 h-4 text-amber-500" />
        <h2 className="font-display font-bold text-base text-navy-800">{title}</h2>
      </div>
      <div className="p-5 sm:p-6">{children}</div>
    </div>
  )
}

function Toggle({ value, onChange, label, desc, disabled = false }) {
  return (
    <div className="flex items-start justify-between gap-4">
      <div>
        <p className="text-sm font-semibold text-navy-800 mb-0.5">{label}</p>
        {desc && <p className="text-xs text-slate-500 leading-relaxed">{desc}</p>}
      </div>
      <button
        type="button"
        onClick={() => onChange(!value)}
        disabled={disabled}
        className={`relative w-12 h-6 rounded-full flex-shrink-0 transition-all duration-200 ${value ? 'bg-amber-500' : 'bg-slate-300'} ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
        aria-pressed={value}
        role="switch"
      >
        <span className={`absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all duration-200 ${value ? 'left-7' : 'left-1'}`} />
      </button>
    </div>
  )
}

// ── Platform connection row ───────────────────────────────────────────────────

function PlatformRow({ def, connection, onReconnect }) {
  const [reconnecting, setReconnecting] = useState(false)

  const isConnected = !!connection && connection.is_active && !connection.is_expired
  const isExpired = !!connection && connection.is_expired

  const handleReconnect = async () => {
    setReconnecting(true)
    try {
      if (connection?.id) {
        try {
          await platformsApi.refreshToken(connection.id)
          onReconnect(def.backendId)
          return
        } catch {
          // fall through to full OAuth
        }
      }
      const res = await platformsApi.connect(def.backendId)
      if (res.data?.redirect_url) window.location.href = res.data.redirect_url
    } catch (e) {
      console.error('Reconnect failed', e)
    } finally {
      setReconnecting(false)
    }
  }

  const handleConnect = async () => {
    setReconnecting(true)
    try {
      const res = await platformsApi.connect(def.backendId)
      if (res.data?.redirect_url) window.location.href = res.data.redirect_url
    } catch (e) {
      console.error('Connect failed', e)
    } finally {
      setReconnecting(false)
    }
  }

  return (
    <div className="flex items-center gap-3 py-3 border-b border-cream-300 last:border-0">
      <PlatformIcon platform={def.id} size="sm" container="soft" className="flex-shrink-0" />
      <div className="flex-1 min-w-0">
        <p className="text-sm font-semibold text-navy-800">{def.label}</p>
        {isConnected && (
          <p className="text-xs text-green-600 flex items-center gap-1 mt-0.5">
            <CheckCircle2 className="w-3 h-3" /> Connected
          </p>
        )}
        {isExpired && (
          <p className="text-xs text-amber-600 flex items-center gap-1 mt-0.5">
            <RefreshCw className="w-3 h-3" /> Token expired
          </p>
        )}
        {!connection && (
          <p className="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
            <XCircle className="w-3 h-3" /> Not connected
          </p>
        )}
      </div>

      {isExpired ? (
        <button
          onClick={handleReconnect}
          disabled={reconnecting}
          className="flex items-center gap-1.5 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-lg hover:bg-amber-100 transition-all disabled:opacity-50"
        >
          {reconnecting
            ? <span className="w-3.5 h-3.5 border-2 border-amber-300 border-t-amber-600 rounded-full animate-spin" />
            : <RefreshCw className="w-3.5 h-3.5" />
          }
          Reconnect
        </button>
      ) : isConnected ? (
        <Link
          to="/platforms"
          className="text-xs font-semibold text-slate-500 hover:text-amber-600 transition-colors"
        >
          Manage
        </Link>
      ) : (
        <button
          onClick={handleConnect}
          disabled={reconnecting}
          className="flex items-center gap-1.5 text-xs font-semibold text-white bg-navy-800 px-3 py-1.5 rounded-lg hover:bg-navy-700 transition-all disabled:opacity-50"
        >
          {reconnecting
            ? <span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            : 'Connect'
          }
        </button>
      )}
    </div>
  )
}

// ── Main ─────────────────────────────────────────────────────────────────────

export default function SettingsPage() {
  const { user, fetchUser } = useAuthStore()
  const [pageLoading, setPageLoading] = useState(true)
  const [bizSaving, setBizSaving] = useState(false)
  const [bizSaved, setBizSaved] = useState(false)
  const [bizError, setBizError] = useState(null)
  const [autoApprove, setAutoApprove] = useState(false)
  const [prefsSaving, setPrefsSaving] = useState(false)
  const [prefsSaved, setPrefsSaved] = useState(false)
  const [prefsError, setPrefsError] = useState(null)
  const [notifications, setNotifications] = useState({ email: true, postApproval: true, weeklyDigest: true })
  const [connections, setConnections] = useState([])

  const { register, handleSubmit, watch, setValue, reset, formState: { errors } } = useForm({
    resolver: zodResolver(businessSchema),
    defaultValues: { tone: 'friendly' }
  })

  const selectedTone = watch('tone')

  // Load settings + connections on mount
  useEffect(() => {
    const load = async () => {
      try {
        const [settingsRes, connectionsRes] = await Promise.all([
          settingsApi.get(),
          platformsApi.getAll()
        ])

        const biz = settingsRes.data?.business ?? settingsRes.data
        // The endpoint returns { business, settings }. This previously read
        // `preferences`, which does not exist, so every preference silently
        // fell back to its default no matter what was stored.
        const prefs = settingsRes.data?.settings ?? {}

        reset({
          business_name:      biz?.name ?? '',
          industry:           biz?.industry ?? '',
          website_url:        biz?.website_url ?? '',
          google_reviews_url: biz?.google_reviews_url ?? '',
          tone:               biz?.tone ?? 'friendly',
        })

        setAutoApprove(prefs?.auto_approve_posts ?? false)
        setConnections(connectionsRes.data?.connections ?? [])
      } catch (e) {
        console.error('Settings load failed', e)
      } finally {
        setPageLoading(false)
      }
    }
    load()
  }, [reset])

  const getConnection = (backendId) =>
    connections.find((c) => c.platform === backendId) ?? null

  const handleReconnect = (backendId) => {
    setConnections((prev) =>
      prev.map((c) => c.platform === backendId ? { ...c, is_expired: false, is_active: true } : c)
    )
  }

  // Preferences have no Save button, so the toggle persists on change. Applied
  // optimistically and rolled back if the request fails — silently leaving the
  // switch on while the server still has it off is exactly the sort of thing
  // you only discover when posts start publishing unreviewed.
  const handleAutoApproveChange = async (value) => {
    const previous = autoApprove
    setAutoApprove(value)
    setPrefsError(null)
    setPrefsSaving(true)

    try {
      await settingsApi.update({ auto_approve_posts: value })
      setPrefsSaved(true)
      setTimeout(() => setPrefsSaved(false), 3000)
    } catch (e) {
      setAutoApprove(previous)
      setPrefsError(e.response?.data?.message ?? 'Could not save that setting. Please try again.')
    } finally {
      setPrefsSaving(false)
    }
  }

  const onBizSubmit = async (data) => {
    setBizSaving(true)
    setBizError(null)
    try {
      await settingsApi.updateBusiness({
        name:               data.business_name,
        industry:           data.industry,
        website_url:        data.website_url,
        google_reviews_url: data.google_reviews_url,
        tone:               data.tone,
      })
      setBizSaved(true)
      setTimeout(() => setBizSaved(false), 3000)
      // Refresh auth store so the nav/header reflects the updated name
      if (typeof fetchUser === 'function') fetchUser()
    } catch (e) {
      setBizError(e.response?.data?.message ?? 'Failed to save. Please try again.')
    } finally {
      setBizSaving(false)
    }
  }

  if (pageLoading) {
    return (
      <div className="max-w-2xl mx-auto space-y-6">
        {[...Array(3)].map((_, i) => (
          <div key={i} className="bg-white rounded-2xl h-48 border border-cream-300 animate-pulse" />
        ))}
      </div>
    )
  }

  return (
    <div className="max-w-2xl mx-auto animate-fade-in-up space-y-6">

      {/* Header */}
      <div className="flex items-center gap-3">
        <Settings className="w-5 h-5 text-amber-500" />
        <h1 className="font-display font-black text-2xl text-navy-800">Settings</h1>
      </div>

      {/* Business details */}
      <SectionCard title="Business details" icon={User}>
        <form onSubmit={handleSubmit(onBizSubmit)} className="space-y-5">

          {bizError && (
            <div className="flex items-start gap-2.5 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
              <AlertCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
              <p className="text-sm text-red-700">{bizError}</p>
            </div>
          )}

          <div>
            <label className="label">Business name</label>
            <input
              type="text"
              className={`input ${errors.business_name ? 'border-red-400' : ''}`}
              {...register('business_name')}
            />
            {errors.business_name && (
              <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                <AlertCircle className="w-3 h-3" /> {errors.business_name.message}
              </p>
            )}
          </div>

          <div>
            <label className="label">Industry</label>
            <div className="relative">
              <select
                className={`input appearance-none pr-10 ${errors.industry ? 'border-red-400' : ''}`}
                {...register('industry')}
              >
                <option value="">Choose your industry...</option>
                {INDUSTRIES.map((ind) => (
                  <option key={ind} value={ind}>{ind}</option>
                ))}
              </select>
              <ChevronRight className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none rotate-90" />
            </div>
            {errors.industry && (
              <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                <AlertCircle className="w-3 h-3" /> {errors.industry.message}
              </p>
            )}
          </div>

          <div>
            <label className="label">Website URL</label>
            <input
              type="url"
              placeholder="https://www.yourbusiness.co.uk"
              className="input"
              {...register('website_url')}
            />
          </div>

          <div>
            <label className="label">Google Reviews link</label>
            <input
              type="url"
              placeholder="https://g.page/r/your-business/review"
              className="input"
              {...register('google_reviews_url')}
            />
            <p className="mt-1.5 text-xs text-slate-400">The link customers use to leave you a Google review</p>
          </div>

          <div>
            <label className="label">Posting tone</label>
            <div className="grid grid-cols-3 gap-3 mt-1">
              {TONES.map((t) => (
                <button
                  key={t}
                  type="button"
                  onClick={() => setValue('tone', t)}
                  className={`py-2.5 px-3 rounded-xl border-2 text-sm font-semibold transition-all duration-200 ${
                    selectedTone === t
                      ? 'border-amber-500 bg-amber-50 text-amber-700'
                      : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                  }`}
                >
                  {t.charAt(0).toUpperCase() + t.slice(1)}
                </button>
              ))}
            </div>
          </div>

          <div className="pt-2">
            <button
              type="submit"
              disabled={bizSaving}
              className="flex items-center gap-2 bg-amber-500 text-white font-bold text-sm px-5 py-2.5 rounded-xl hover:bg-amber-700 active:scale-[0.97] transition-all disabled:opacity-60"
            >
              {bizSaving ? (
                <><span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> Saving...</>
              ) : bizSaved ? (
                <><Check className="w-4 h-4" /> Saved!</>
              ) : 'Save changes'}
            </button>
          </div>
        </form>
      </SectionCard>

      {/* Connected platforms */}
      <SectionCard title="Connected platforms" icon={Share2}>
        <div className="-mt-1">
          {PLATFORM_DEFS.map((def) => (
            <PlatformRow
              key={def.id}
              def={def}
              connection={getConnection(def.backendId)}
              onReconnect={handleReconnect}
            />
          ))}
        </div>
        <div className="mt-4 pt-4 border-t border-cream-300">
          <Link
            to="/platforms"
            className="text-sm font-semibold text-amber-600 hover:text-amber-700 transition-colors"
          >
            Manage all platforms →
          </Link>
        </div>
      </SectionCard>

      {/* Posting preferences */}
      <SectionCard title="Posting preferences" icon={Settings}>
        <div className="space-y-5">
          <Toggle
            value={autoApprove}
            onChange={handleAutoApproveChange}
            disabled={prefsSaving}
            label="Auto-approve posts"
            desc="Posts go live without your approval. Turn this on once you trust the AI output."
          />
          {prefsSaved && (
            <p className="text-xs font-semibold text-green-600">Saved.</p>
          )}
          {prefsError && (
            <div className="flex items-start gap-2.5 bg-red-50 border border-red-200 rounded-xl p-3.5">
              <AlertCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-red-700">{prefsError}</p>
            </div>
          )}
          {autoApprove && (
            <div className="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-xl p-3.5">
              <AlertCircle className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-amber-700">
                Posts will go live without review. You can still edit or delete them from the Posts page.
              </p>
            </div>
          )}
        </div>
      </SectionCard>

      {/* Notifications */}
      <SectionCard title="Notifications" icon={Bell}>
        <div className="space-y-4">
          {[
            { key: 'email',        label: 'Email notifications',      desc: 'Receive email updates about your account' },
            { key: 'postApproval', label: 'Post approval reminders',  desc: 'Get notified when posts need your approval' },
            { key: 'weeklyDigest', label: 'Weekly digest',            desc: 'A summary of your posting activity every Monday' }
          ].map(({ key, label, desc }) => (
            <Toggle
              key={key}
              value={notifications[key]}
              onChange={(val) => setNotifications((n) => ({ ...n, [key]: val }))}
              label={label}
              desc={desc}
            />
          ))}
        </div>
      </SectionCard>

      {/* Security */}
      <SectionCard title="Security" icon={Shield}>
        <div className="space-y-1">
          {[
            { label: 'Change password' },
            { label: 'Two-factor authentication', badge: 'Not enabled' },
            { label: 'Active sessions' },
          ].map(({ label, badge }) => (
            <button
              key={label}
              className="w-full flex items-center justify-between px-4 py-3 rounded-xl hover:bg-cream-200 transition-colors group"
            >
              <span className="text-sm font-semibold text-navy-800">{label}</span>
              <div className="flex items-center gap-2">
                {badge && <span className="badge bg-slate-100 text-slate-500">{badge}</span>}
                <ChevronRight className="w-4 h-4 text-slate-400 group-hover:text-amber-500 transition-colors" />
              </div>
            </button>
          ))}
        </div>
      </SectionCard>

      {/* Danger zone */}
      <div className="border border-red-200 rounded-2xl p-5">
        <div className="flex items-start gap-3">
          <Trash2 className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
          <div>
            <h3 className="font-display font-bold text-base text-red-700 mb-1">Delete account</h3>
            <p className="text-sm text-slate-600 mb-3 leading-relaxed">
              Permanently delete your account and all associated data. This cannot be undone.
            </p>
            <button className="text-sm font-semibold text-red-600 hover:text-red-700 underline transition-colors">
              Delete my account
            </button>
          </div>
        </div>
      </div>

    </div>
  )
}
