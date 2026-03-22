import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Settings, User, Bell, Shield, Trash2, Check, AlertCircle, ChevronRight } from 'lucide-react'
import useAuthStore from '../../stores/authStore.js'

const businessSchema = z.object({
  business_name: z.string().min(2, 'Business name required'),
  industry: z.string().min(1, 'Industry required'),
  website_url: z.string().url().optional().or(z.literal('')),
  google_reviews_url: z.string().url().optional().or(z.literal('')),
  tone: z.enum(['professional', 'friendly', 'casual'])
})

const TONES = ['professional', 'friendly', 'casual']

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

function SaveButton({ loading, saved }) {
  return (
    <button
      type="submit"
      disabled={loading}
      className="flex items-center gap-2 bg-amber-500 text-white font-bold text-sm px-5 py-2.5 rounded-xl hover:bg-amber-700 active:scale-[0.97] transition-all disabled:opacity-60"
    >
      {loading ? (
        <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
      ) : saved ? (
        <><Check className="w-4 h-4" /> Saved!</>
      ) : (
        'Save changes'
      )}
    </button>
  )
}

export default function SettingsPage() {
  const { user } = useAuthStore()
  const [bizSaved, setBizSaved] = useState(false)
  const [bizLoading, setBizLoading] = useState(false)
  const [autoApprove, setAutoApprove] = useState(false)
  const [notifications, setNotifications] = useState({ email: true, postApproval: true, weeklyDigest: true })

  const { register, handleSubmit, watch, setValue, formState: { errors } } = useForm({
    resolver: zodResolver(businessSchema),
    defaultValues: {
      business_name: user?.business?.name ?? '',
      industry: user?.business?.industry ?? '',
      website_url: user?.business?.website_url ?? '',
      google_reviews_url: user?.business?.google_reviews_url ?? '',
      tone: user?.business?.tone ?? 'friendly'
    }
  })

  const selectedTone = watch('tone')

  const onBizSubmit = async (data) => {
    setBizLoading(true)
    await new Promise((r) => setTimeout(r, 800))
    setBizLoading(false)
    setBizSaved(true)
    setTimeout(() => setBizSaved(false), 2500)
  }

  return (
    <div className="max-w-2xl mx-auto animate-fade-in-up space-y-6">

      {/* Header */}
      <div className="flex items-center gap-3">
        <Settings className="w-5 h-5 text-amber-500" />
        <h1 className="font-display font-black text-2xl text-navy-800">Settings</h1>
      </div>

      {/* Business settings */}
      <SectionCard title="Business details" icon={User}>
        <form onSubmit={handleSubmit(onBizSubmit)} className="space-y-5">

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
            <input type="text" className="input" {...register('industry')} />
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
            <label className="label">Google Reviews URL</label>
            <input
              type="url"
              placeholder="https://g.page/r/your-business/review"
              className="input"
              {...register('google_reviews_url')}
            />
          </div>

          <div>
            <label className="label">Posting tone</label>
            <div className="grid grid-cols-3 gap-3">
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
            <SaveButton loading={bizLoading} saved={bizSaved} />
          </div>
        </form>
      </SectionCard>

      {/* Post settings */}
      <SectionCard title="Posting preferences" icon={Settings}>
        <div className="space-y-5">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="text-sm font-semibold text-navy-800 mb-0.5">Auto-approve posts</p>
              <p className="text-xs text-slate-500 leading-relaxed">
                Posts will be published automatically without needing your approval first.
                You can turn this on once you trust the AI&apos;s output.
              </p>
            </div>
            <button
              onClick={() => setAutoApprove(!autoApprove)}
              className={`relative w-12 h-6 rounded-full flex-shrink-0 transition-all duration-200 ${autoApprove ? 'bg-amber-500' : 'bg-slate-300'}`}
              aria-pressed={autoApprove}
              role="switch"
            >
              <span className={`absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all duration-200 ${autoApprove ? 'left-7' : 'left-1'}`} />
            </button>
          </div>

          {autoApprove && (
            <div className="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-xl p-3.5">
              <AlertCircle className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-amber-700">
                Posts will go live without your review. You can still edit or delete posts from the All Posts page.
              </p>
            </div>
          )}
        </div>
      </SectionCard>

      {/* Notifications */}
      <SectionCard title="Notifications" icon={Bell}>
        <div className="space-y-4">
          {[
            { key: 'email', label: 'Email notifications', desc: 'Receive email updates about your account' },
            { key: 'postApproval', label: 'Post approval reminders', desc: 'Get notified when posts are waiting for your approval' },
            { key: 'weeklyDigest', label: 'Weekly digest', desc: 'A summary of your posting activity every Monday' }
          ].map(({ key, label, desc }) => (
            <div key={key} className="flex items-start justify-between gap-4">
              <div>
                <p className="text-sm font-semibold text-navy-800 mb-0.5">{label}</p>
                <p className="text-xs text-slate-500">{desc}</p>
              </div>
              <button
                onClick={() => setNotifications((n) => ({ ...n, [key]: !n[key] }))}
                className={`relative w-12 h-6 rounded-full flex-shrink-0 transition-all duration-200 ${notifications[key] ? 'bg-amber-500' : 'bg-slate-300'}`}
                aria-pressed={notifications[key]}
                role="switch"
              >
                <span className={`absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all duration-200 ${notifications[key] ? 'left-7' : 'left-1'}`} />
              </button>
            </div>
          ))}
        </div>
      </SectionCard>

      {/* Security */}
      <SectionCard title="Security" icon={Shield}>
        <div className="space-y-3">
          <button className="w-full flex items-center justify-between px-4 py-3 rounded-xl hover:bg-cream-200 transition-colors group">
            <span className="text-sm font-semibold text-navy-800">Change password</span>
            <ChevronRight className="w-4 h-4 text-slate-400 group-hover:text-amber-500 transition-colors" />
          </button>
          <button className="w-full flex items-center justify-between px-4 py-3 rounded-xl hover:bg-cream-200 transition-colors group">
            <span className="text-sm font-semibold text-navy-800">Two-factor authentication</span>
            <div className="flex items-center gap-2">
              <span className="badge bg-slate-100 text-slate-500 text-xs">Not enabled</span>
              <ChevronRight className="w-4 h-4 text-slate-400 group-hover:text-amber-500 transition-colors" />
            </div>
          </button>
          <button className="w-full flex items-center justify-between px-4 py-3 rounded-xl hover:bg-cream-200 transition-colors group">
            <span className="text-sm font-semibold text-navy-800">Active sessions</span>
            <ChevronRight className="w-4 h-4 text-slate-400 group-hover:text-amber-500 transition-colors" />
          </button>
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
              All your posts, platform connections, and business data will be removed.
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
