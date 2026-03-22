import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import {
  Building2, Globe, Star, Share2, Sparkles,
  ArrowRight, ArrowLeft, Check, AlertCircle,
  ChevronDown, Clipboard
} from 'lucide-react'
import Logo from '../../components/ui/Logo.jsx'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'
import useAuthStore from '../../stores/authStore.js'

// ── Progress bar ──────────────────────────────────────────────────────────────
function ProgressBar({ step, total }) {
  return (
    <div className="w-full">
      <div className="flex items-center justify-between mb-2">
        <span className="text-xs font-semibold text-slate-500">Step {step} of {total}</span>
        <span className="text-xs font-bold text-amber-600">{Math.round((step / total) * 100)}%</span>
      </div>
      <div className="h-1.5 bg-slate-200 rounded-full overflow-hidden">
        <div
          className="h-full bg-amber-500 rounded-full transition-all duration-500 ease-out"
          style={{ width: `${(step / total) * 100}%` }}
        />
      </div>
    </div>
  )
}

// ── Step 1 — Business basics ──────────────────────────────────────────────────
const step1Schema = z.object({
  business_name: z.string().min(2, 'Please enter your business name'),
  industry: z.string().min(1, 'Please choose your industry'),
  tone: z.enum(['professional', 'friendly', 'casual'])
})

const INDUSTRIES = [
  'Restaurant / Cafe',
  'Retail',
  'Trades (Builder, Plumber, Electrician etc.)',
  'Professional Services',
  'Health & Beauty',
  'Automotive',
  'Fitness & Wellbeing',
  'Property',
  'Education',
  'Other'
]

const TONES = [
  { value: 'professional', label: 'Professional', desc: 'Expert and authoritative' },
  { value: 'friendly', label: 'Friendly', desc: 'Warm and approachable' },
  { value: 'casual', label: 'Casual', desc: 'Relaxed and conversational' }
]

function Step1({ onNext, defaultValues }) {
  const { register, handleSubmit, watch, setValue, formState: { errors } } = useForm({
    resolver: zodResolver(step1Schema),
    defaultValues: { tone: 'friendly', ...defaultValues }
  })
  const selectedTone = watch('tone')

  return (
    <form onSubmit={handleSubmit(onNext)} className="space-y-6">
      <div>
        <label htmlFor="business_name" className="label">Business name</label>
        <input
          id="business_name"
          type="text"
          placeholder="Acme Plumbing Ltd"
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
        <label htmlFor="industry" className="label">Industry</label>
        <div className="relative">
          <select
            id="industry"
            className={`input appearance-none pr-10 ${errors.industry ? 'border-red-400' : ''}`}
            {...register('industry')}
          >
            <option value="">Choose your industry...</option>
            {INDUSTRIES.map((ind) => (
              <option key={ind} value={ind}>{ind}</option>
            ))}
          </select>
          <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
        </div>
        {errors.industry && (
          <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
            <AlertCircle className="w-3 h-3" /> {errors.industry.message}
          </p>
        )}
      </div>

      <div>
        <label className="label">Posting tone</label>
        <p className="text-xs text-slate-500 mb-3">How should your posts come across to your customers?</p>
        <div className="grid grid-cols-3 gap-3">
          {TONES.map((t) => (
            <button
              key={t.value}
              type="button"
              onClick={() => setValue('tone', t.value)}
              className={`flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 text-center transition-all duration-200 ${
                selectedTone === t.value
                  ? 'border-amber-500 bg-amber-50 text-amber-700'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
              }`}
            >
              <span className="text-sm font-bold">{t.label}</span>
              <span className="text-xs opacity-70 leading-tight">{t.desc}</span>
            </button>
          ))}
        </div>
      </div>

      <button type="submit" className="w-full flex items-center justify-center gap-2 bg-amber-500 text-white font-bold py-3.5 rounded-xl hover:bg-amber-700 active:scale-[0.98] transition-all duration-200" style={{ boxShadow: '0 4px 16px rgb(224 123 48 / 0.3)' }}>
        Continue <ArrowRight className="w-4 h-4" />
      </button>
    </form>
  )
}

// ── Step 2 — Website ──────────────────────────────────────────────────────────
const step2Schema = z.object({
  website_url: z.string().url('Please enter a valid website URL (include https://)').optional().or(z.literal(''))
})

function Step2({ onNext, onSkip, defaultValues }) {
  const { register, handleSubmit, setValue, formState: { errors } } = useForm({
    resolver: zodResolver(step2Schema),
    defaultValues
  })

  const handlePaste = async () => {
    try {
      const text = await navigator.clipboard.readText()
      setValue('website_url', text)
    } catch (_) {
      // Clipboard permission denied
    }
  }

  return (
    <form onSubmit={handleSubmit(onNext)} className="space-y-6">
      <div>
        <label htmlFor="website_url" className="label">Your website URL</label>
        <div className="relative">
          <input
            id="website_url"
            type="url"
            placeholder="https://www.yourbusiness.co.uk"
            className={`input pr-24 ${errors.website_url ? 'border-red-400' : ''}`}
            {...register('website_url')}
          />
          <button
            type="button"
            onClick={handlePaste}
            className="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center gap-1.5 text-xs font-semibold text-amber-600 hover:text-amber-700 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition-colors"
          >
            <Clipboard className="w-3.5 h-3.5" />
            Paste
          </button>
        </div>
        {errors.website_url && (
          <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
            <AlertCircle className="w-3 h-3" /> {errors.website_url.message}
          </p>
        )}
        <p className="mt-2.5 text-xs text-slate-500 leading-relaxed">
          We&apos;ll scan your website to learn about your services, your USPs, your opening hours, and your team — all the raw material we need to write brilliant posts.
        </p>
      </div>

      <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <div className="flex items-start gap-3">
          <Globe className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
          <div>
            <p className="text-xs font-semibold text-amber-800 mb-1">What we look for</p>
            <ul className="text-xs text-amber-700 space-y-0.5">
              <li>Services and products you offer</li>
              <li>Your unique selling points</li>
              <li>Opening hours and location</li>
              <li>Team and company story</li>
            </ul>
          </div>
        </div>
      </div>

      <div className="flex flex-col gap-3">
        <button type="submit" className="w-full flex items-center justify-center gap-2 bg-amber-500 text-white font-bold py-3.5 rounded-xl hover:bg-amber-700 active:scale-[0.98] transition-all duration-200" style={{ boxShadow: '0 4px 16px rgb(224 123 48 / 0.3)' }}>
          Continue <ArrowRight className="w-4 h-4" />
        </button>
        <button type="button" onClick={onSkip} className="w-full text-sm text-slate-400 hover:text-slate-600 py-2 transition-colors">
          Skip for now
        </button>
      </div>
    </form>
  )
}

// ── Step 3 — Google Reviews ───────────────────────────────────────────────────
const step3Schema = z.object({
  google_reviews_url: z.string().url('Please enter a valid Google Reviews URL').optional().or(z.literal(''))
})

function Step3({ onNext, onSkip, defaultValues }) {
  const [showHelp, setShowHelp] = useState(false)
  const { register, handleSubmit, setValue, formState: { errors } } = useForm({
    resolver: zodResolver(step3Schema),
    defaultValues
  })

  const handlePaste = async () => {
    try {
      const text = await navigator.clipboard.readText()
      setValue('google_reviews_url', text)
    } catch (_) {}
  }

  return (
    <form onSubmit={handleSubmit(onNext)} className="space-y-6">
      <div>
        <label htmlFor="google_reviews_url" className="label">Your Google Reviews link</label>
        <div className="relative">
          <input
            id="google_reviews_url"
            type="url"
            placeholder="https://g.page/r/your-business/review"
            className={`input pr-24 ${errors.google_reviews_url ? 'border-red-400' : ''}`}
            {...register('google_reviews_url')}
          />
          <button
            type="button"
            onClick={handlePaste}
            className="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center gap-1.5 text-xs font-semibold text-amber-600 hover:text-amber-700 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition-colors"
          >
            <Clipboard className="w-3.5 h-3.5" />
            Paste
          </button>
        </div>
        {errors.google_reviews_url && (
          <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
            <AlertCircle className="w-3 h-3" /> {errors.google_reviews_url.message}
          </p>
        )}
        <p className="mt-2.5 text-xs text-slate-500 leading-relaxed">
          This is the link your customers use to leave you a Google review. We use it to spot new 5-star reviews and turn them into social posts automatically.
        </p>
      </div>

      {/* How to find this accordion */}
      <div className="border border-amber-200 bg-amber-50 rounded-xl overflow-hidden">
        <button
          type="button"
          onClick={() => setShowHelp(!showHelp)}
          className="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-amber-800 hover:bg-amber-100 transition-colors"
        >
          <span>📍 How do I find this link?</span>
          <ChevronDown className={`w-4 h-4 text-amber-500 transition-transform duration-200 ${showHelp ? 'rotate-180' : ''}`} />
        </button>
        {showHelp && (
          <div className="px-4 pb-5 bg-amber-50 border-t border-amber-200">
            <ol className="text-sm text-amber-900 space-y-3 mt-4 leading-relaxed">
              <li className="flex gap-2"><span className="font-bold text-amber-600 flex-shrink-0">1.</span><span>On your phone or computer, open <strong><a href="https://maps.google.com" target="_blank" rel="noopener noreferrer" className="underline">Google Maps</a></strong></span></li>
              <li className="flex gap-2"><span className="font-bold text-amber-600 flex-shrink-0">2.</span><span>Search for <strong>your business name</strong> — click on your listing when it appears</span></li>
              <li className="flex gap-2"><span className="font-bold text-amber-600 flex-shrink-0">3.</span><span>Scroll down until you see the <strong>Reviews</strong> section</span></li>
              <li className="flex gap-2"><span className="font-bold text-amber-600 flex-shrink-0">4.</span><span>Click the button that says <strong>&quot;Write a review&quot;</strong></span></li>
              <li className="flex gap-2"><span className="font-bold text-amber-600 flex-shrink-0">5.</span><span><strong>Copy the web address</strong> from the top of your browser and paste it into the box above</span></li>
            </ol>
            <p className="mt-4 text-xs text-amber-700 bg-amber-100 rounded-lg px-3 py-2">
              💡 The link will look something like: <span className="font-mono">https://g.page/r/ABC123.../review</span>
            </p>
              <li>Paste it here</li>
            </ol>
          </div>
        )}
      </div>

      <div className="flex flex-col gap-3">
        <button type="submit" className="w-full flex items-center justify-center gap-2 bg-amber-500 text-white font-bold py-3.5 rounded-xl hover:bg-amber-700 active:scale-[0.98] transition-all duration-200" style={{ boxShadow: '0 4px 16px rgb(224 123 48 / 0.3)' }}>
          Continue <ArrowRight className="w-4 h-4" />
        </button>
        <button type="button" onClick={onSkip} className="w-full text-sm text-slate-400 hover:text-slate-600 py-2 transition-colors">
          Skip for now
        </button>
      </div>
    </form>
  )
}

// ── Step 4 — Connect platforms ────────────────────────────────────────────────
const PLATFORMS = [
  { id: 'facebook', label: 'Facebook', plan: 'starter' },
  { id: 'instagram', label: 'Instagram', plan: 'starter' },
  { id: 'linkedin', label: 'LinkedIn', plan: 'growth' },
  { id: 'x', label: 'X (Twitter)', plan: 'growth' },
  { id: 'tiktok', label: 'TikTok', plan: 'pro' },
  { id: 'google', label: 'Google Business', plan: 'free', alwaysFree: true }
]

function Step4({ onNext, onSkip, userPlan = 'growth' }) {
  const [connected, setConnected] = useState(new Set())
  const [connecting, setConnecting] = useState(null)

  const planOrder = { free: 0, starter: 1, growth: 2, pro: 3 }
  const userPlanLevel = planOrder[userPlan] ?? 2

  const isLocked = (plan) => {
    if (plan === 'free') return false
    return planOrder[plan] > userPlanLevel
  }

  const handleConnect = async (platformId) => {
    if (connected.has(platformId)) {
      setConnected((prev) => { const n = new Set(prev); n.delete(platformId); return n })
      return
    }
    setConnecting(platformId)
    // Simulate OAuth delay
    await new Promise((r) => setTimeout(r, 1200))
    setConnected((prev) => new Set([...prev, platformId]))
    setConnecting(null)
  }

  return (
    <div className="space-y-6">
      <p className="text-sm text-slate-500 leading-relaxed">
        Connect at least one platform to get started. You can always add more later.
      </p>

      <div className="space-y-3">
        {PLATFORMS.map((platform) => {
          const locked = isLocked(platform.plan)
          const isConnected = connected.has(platform.id)
          const isConnecting = connecting === platform.id

          return (
            <div
              key={platform.id}
              className={`flex items-center gap-4 p-4 rounded-2xl border transition-all duration-200 ${
                locked
                  ? 'bg-slate-50 border-slate-200 opacity-60'
                  : isConnected
                  ? 'bg-green-50 border-green-200'
                  : 'bg-white border-slate-200 hover:border-slate-300'
              }`}
            >
              <PlatformIcon platform={platform.id} size="md" />
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-semibold text-navy-800">{platform.label}</span>
                  {platform.alwaysFree && (
                    <span className="badge-honey text-2xs">Free</span>
                  )}
                  {locked && (
                    <span className="text-xs text-slate-400 font-medium">Upgrade to unlock</span>
                  )}
                </div>
                {isConnected && (
                  <p className="text-xs text-green-600 font-medium mt-0.5">Connected</p>
                )}
              </div>
              {locked ? (
                <button className="text-xs font-semibold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200">
                  Upgrade
                </button>
              ) : (
                <button
                  onClick={() => handleConnect(platform.id)}
                  disabled={isConnecting}
                  className={`flex items-center gap-1.5 text-xs font-bold px-4 py-2 rounded-xl transition-all duration-200 disabled:opacity-60 ${
                    isConnected
                      ? 'bg-green-100 text-green-700 hover:bg-red-50 hover:text-red-600'
                      : 'bg-navy-800 text-white hover:bg-navy-700'
                  }`}
                >
                  {isConnecting ? (
                    <span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                  ) : isConnected ? (
                    <><Check className="w-3.5 h-3.5" /> Connected</>
                  ) : 'Connect'}
                </button>
              )}
            </div>
          )
        })}
      </div>

      {connected.size === 0 && (
        <div className="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-xl p-3.5">
          <AlertCircle className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
          <p className="text-xs text-amber-700">We recommend connecting at least one platform to get the most out of your trial.</p>
        </div>
      )}

      <div className="flex flex-col gap-3">
        <button
          onClick={onNext}
          className="w-full flex items-center justify-center gap-2 bg-amber-500 text-white font-bold py-3.5 rounded-xl hover:bg-amber-700 active:scale-[0.98] transition-all duration-200"
          style={{ boxShadow: '0 4px 16px rgb(224 123 48 / 0.3)' }}
        >
          Continue <ArrowRight className="w-4 h-4" />
        </button>
        <button type="button" onClick={onSkip} className="w-full text-sm text-slate-400 hover:text-slate-600 py-2 transition-colors">
          I&apos;ll connect platforms later
        </button>
      </div>
    </div>
  )
}

// ── Step 5 — All done ─────────────────────────────────────────────────────────
function Step5({ businessName, onGoToDashboard }) {
  const [showButton, setShowButton] = useState(false)
  const [progress, setProgress] = useState(0)

  useEffect(() => {
    const timer = setTimeout(() => setShowButton(true), 3000)
    const interval = setInterval(() => {
      setProgress((p) => {
        if (p >= 100) { clearInterval(interval); return 100 }
        return p + 2
      })
    }, 60)
    return () => { clearTimeout(timer); clearInterval(interval) }
  }, [])

  return (
    <div className="text-center space-y-6 py-4">
      {/* Honeycomb celebration */}
      <div className="relative w-24 h-24 mx-auto">
        <div className="w-24 h-24 rounded-3xl bg-amber-500 flex items-center justify-center mx-auto animate-bounce-subtle"
          style={{ boxShadow: '0 12px 32px rgb(224 123 48 / 0.4)' }}>
          <Sparkles className="w-12 h-12 text-white" />
        </div>
        {/* Orbiting dots */}
        {[0, 72, 144, 216, 288].map((deg, i) => (
          <div
            key={deg}
            className="absolute w-3 h-3 rounded-full bg-honey-400"
            style={{
              top: '50%',
              left: '50%',
              transform: `rotate(${deg}deg) translateX(52px) translateY(-50%)`,
              animationDelay: `${i * 200}ms`,
              animation: 'pulse 1.5s ease-in-out infinite'
            }}
          />
        ))}
      </div>

      <div>
        <h2 className="font-display font-black text-2xl text-navy-800 mb-2">
          You&apos;re all set, {businessName || 'there'}!
        </h2>
        <p className="text-slate-600 text-sm leading-relaxed max-w-xs mx-auto">
          We&apos;re scanning your website and generating your first posts. This usually takes less than a minute.
        </p>
      </div>

      {/* Progress */}
      {progress < 100 ? (
        <div className="space-y-3">
          <div className="h-2 bg-slate-100 rounded-full overflow-hidden max-w-xs mx-auto">
            <div
              className="h-full bg-amber-500 rounded-full transition-all duration-300"
              style={{ width: `${progress}%` }}
            />
          </div>
          <div className="flex items-center justify-center gap-2 text-xs text-slate-500">
            <span className="w-3 h-3 border-2 border-amber-500/30 border-t-amber-500 rounded-full animate-spin" />
            {progress < 40 ? 'Scanning your website...' : progress < 70 ? 'Reading your reviews...' : 'Crafting your first posts...'}
          </div>
        </div>
      ) : (
        <div className="flex items-center justify-center gap-2 text-sm font-semibold text-green-600 bg-green-50 py-2.5 px-4 rounded-full">
          <Check className="w-4 h-4" />
          Your first posts are ready!
        </div>
      )}

      {showButton && (
        <button
          onClick={onGoToDashboard}
          className="w-full flex items-center justify-center gap-2 bg-amber-500 text-white font-bold py-3.5 rounded-xl hover:bg-amber-700 active:scale-[0.98] transition-all duration-200 animate-fade-in-up"
          style={{ boxShadow: '0 4px 16px rgb(224 123 48 / 0.3)' }}
        >
          Go to dashboard <ArrowRight className="w-4 h-4" />
        </button>
      )}
    </div>
  )
}

// ── Step config ───────────────────────────────────────────────────────────────
const STEPS = [
  { icon: Building2, label: 'Business basics', title: 'Tell us about your business', subtitle: 'Just the basics — we do the rest.' },
  { icon: Globe, label: 'Website', title: 'Your website', subtitle: 'We\'ll learn everything we need from it.' },
  { icon: Star, label: 'Google Reviews', title: 'Google Reviews', subtitle: 'New 5-star reviews become instant posts.' },
  { icon: Share2, label: 'Platforms', title: 'Connect your platforms', subtitle: 'One tap per platform. That\'s it.' },
  { icon: Sparkles, label: 'All done!', title: 'All done!', subtitle: 'Your hive is buzzing.' }
]

// ── Main wizard ───────────────────────────────────────────────────────────────
export default function OnboardingPage() {
  const [step, setStep] = useState(1)
  const [formData, setFormData] = useState({})
  const navigate = useNavigate()
  const { user } = useAuthStore()

  const businessName = formData.business_name || user?.business?.name || ''

  const handleNext = (data = {}) => {
    setFormData((prev) => ({ ...prev, ...data }))
    setStep((s) => s + 1)
  }

  const handleBack = () => setStep((s) => Math.max(1, s - 1))

  const currentStep = STEPS[step - 1]

  return (
    <div className="min-h-screen bg-cream-200 flex flex-col items-center justify-center px-4 py-8 sm:py-12">
      <div className="w-full max-w-lg">

        {/* Logo */}
        <div className="flex justify-center mb-8">
          <Logo size="md" asLink={false} />
        </div>

        {/* Card */}
        <div className="bg-white rounded-3xl p-7 sm:p-8 border border-cream-300" style={{ boxShadow: '0 8px 32px rgb(30 45 74 / 0.08)' }}>

          {/* Progress */}
          <div className="mb-8">
            <ProgressBar step={step} total={STEPS.length} />
          </div>

          {/* Step header */}
          {step < 5 && (
            <div className="mb-7">
              <div className="flex items-center gap-3 mb-3">
                {step > 1 && (
                  <button
                    onClick={handleBack}
                    className="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all"
                    aria-label="Go back"
                  >
                    <ArrowLeft className="w-4 h-4" />
                  </button>
                )}
                <div className="w-10 h-10 bg-amber-500/10 rounded-xl flex items-center justify-center">
                  <currentStep.icon className="w-5 h-5 text-amber-600" />
                </div>
              </div>
              <h1 className="font-display font-bold text-xl text-navy-800 mb-1">{currentStep.title}</h1>
              <p className="text-sm text-slate-500">{currentStep.subtitle}</p>
            </div>
          )}

          {/* Step content */}
          {step === 1 && <Step1 onNext={handleNext} defaultValues={formData} />}
          {step === 2 && <Step2 onNext={handleNext} onSkip={() => handleNext()} defaultValues={formData} />}
          {step === 3 && <Step3 onNext={handleNext} onSkip={() => handleNext()} defaultValues={formData} />}
          {step === 4 && <Step4 onNext={() => handleNext()} onSkip={() => handleNext()} />}
          {step === 5 && (
            <Step5
              businessName={businessName}
              onGoToDashboard={() => navigate('/dashboard', { replace: true })}
            />
          )}
        </div>

        {/* Step pills */}
        <div className="flex items-center justify-center gap-2 mt-6">
          {STEPS.map((_, i) => (
            <div
              key={i}
              className={`rounded-full transition-all duration-300 ${
                i + 1 === step
                  ? 'w-6 h-2 bg-amber-500'
                  : i + 1 < step
                  ? 'w-2 h-2 bg-amber-300'
                  : 'w-2 h-2 bg-slate-200'
              }`}
            />
          ))}
        </div>
      </div>
    </div>
  )
}
