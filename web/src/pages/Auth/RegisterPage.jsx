import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Eye, EyeOff, ArrowRight, AlertCircle, Check, Shield, ChevronDown } from 'lucide-react'
import Logo from '../../components/ui/Logo.jsx'
import useAuthStore from '../../stores/authStore.js'
import { Seo } from '../../lib/head.jsx'

function GoogleIcon() {
  return (
    <svg width="20" height="20" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
      <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
      <path d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
      <path d="M3.964 10.707A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.707V4.961H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.039l3.007-2.332z" fill="#FBBC05"/>
      <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.961L3.964 7.293C4.672 5.166 6.656 3.58 9 3.58z" fill="#EA4335"/>
    </svg>
  )
}

const schema = z.object({
  name: z.string().min(2, 'Please enter your name').max(100),
  email: z.string().email('Please enter a valid email address'),
  password: z
    .string()
    .min(8, 'Password must be at least 8 characters')
    .regex(/[A-Z]/, 'Must include at least one capital letter')
    .regex(/[0-9]/, 'Must include at least one number'),
  password_confirmation: z.string(),
  terms_accepted: z.boolean().refine((v) => v === true, {
    message: 'You must accept the Terms of Service and Privacy Policy to continue',
  }),
  access_code: z.string().max(60).optional(),
}).refine((d) => d.password === d.password_confirmation, {
  message: 'Passwords do not match',
  path: ['password_confirmation']
})

function PasswordStrength({ password = '' }) {
  const checks = [
    { label: '8+ characters', pass: password.length >= 8 },
    { label: 'Capital letter', pass: /[A-Z]/.test(password) },
    { label: 'Number', pass: /[0-9]/.test(password) }
  ]
  const score = checks.filter((c) => c.pass).length

  if (!password) return null

  return (
    <div className="mt-2 space-y-1.5">
      <div className="flex gap-1">
        {[1, 2, 3].map((i) => (
          <div
            key={i}
            className={`h-1 flex-1 rounded-full transition-all duration-300 ${
              i <= score
                ? score === 1 ? 'bg-danger' : score === 2 ? 'bg-warn' : 'bg-success'
                : 'bg-slate-200'
            }`}
          />
        ))}
      </div>
      <div className="flex gap-3 flex-wrap">
        {checks.map((c) => (
          <span key={c.label} className={`text-xs flex items-center gap-1 ${c.pass ? 'text-green-600' : 'text-slate-500'}`}>
            <Check className={`w-3 h-3 ${c.pass ? 'opacity-100' : 'opacity-30'}`} />
            {c.label}
          </span>
        ))}
      </div>
    </div>
  )
}

export default function RegisterPage() {
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirm, setShowConfirm] = useState(false)
  const [showEmailForm, setShowEmailForm] = useState(false)
  const [showCode, setShowCode] = useState(false)
  const { register: registerUser, loginWithGoogle, isLoading, error, clearError } = useAuthStore()
  const navigate = useNavigate()

  const { register, handleSubmit, watch, formState: { errors } } = useForm({
    resolver: zodResolver(schema)
  })

  const password = watch('password', '')

  const onSubmit = async (data) => {
    clearError()
    const result = await registerUser(data)
    if (result.success) {
      navigate('/onboarding', { replace: true })
    }
  }

  return (
    <div className="min-h-screen bg-cloud flex flex-col items-center justify-center px-4 py-12">
      <Seo
        title="Start Your Free Trial | postd.uk"
        description="Create a postd.uk account with Google and let it write and publish your social media posts to Google Business Profile, Facebook, LinkedIn and X."
        path="/register"
      />

      <div className="w-full max-w-md">

        {/* Logo */}
        <div className="text-center mb-8">
          <Logo size="lg" className="justify-center" />
          <div className="mt-6">
            <div className="inline-flex items-center gap-2 bg-success-soft text-success-text text-xs font-semibold px-4 py-2 rounded-full mb-4">
              <Check className="w-3.5 h-3.5" />
              14-day free trial, no card required
            </div>
            <h1 className="font-display font-extrabold text-[1.75rem] tracking-tight text-navy-900 mb-2">
              Create your account
            </h1>
            <p className="text-slate-500 text-sm">
              Join hundreds of UK businesses posting on autopilot
            </p>
          </div>
        </div>

        <div className="card-marketing p-6 sm:p-8">

          {error && (
            <div className="flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-6">
              <AlertCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
              <p className="text-sm text-red-700">{error}</p>
            </div>
          )}

          {/* Google sign-up — primary CTA */}
          <button
            type="button"
            onClick={loginWithGoogle}
            className="min-h-[44px] font-display w-full flex items-center justify-center gap-3 bg-white border-[1.5px] border-line-strong text-navy-900 font-semibold py-4 px-6 rounded-full hover:border-cobalt-300 hover:bg-cobalt-50 active:scale-[0.98] transition-all duration-200 mb-3"
          >
            <GoogleIcon />
            <span>Sign up with Google</span>
          </button>

          <p className="text-center text-xs text-slate-500 mb-4">
            Your Google Business Profile will be connected automatically
          </p>

          {/* Toggle email form */}
          {!showEmailForm ? (
            <button
              type="button"
              onClick={() => setShowEmailForm(true)}
              className="w-full flex items-center justify-center gap-2 min-h-[44px] text-sm font-medium text-slate-500 hover:text-navy-900 transition-colors py-2"
            >
              <ChevronDown className="w-4 h-4" />
              Sign up with email instead
            </button>
          ) : (
            <>
              {/* Divider */}
              <div className="flex items-center gap-3 mb-5">
                <div className="flex-1 h-px bg-line" />
                <span className="text-xs text-slate-500 font-medium">or use email</span>
                <div className="flex-1 h-px bg-line" />
              </div>

          <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-5">

            {/* Name */}
            <div>
              <label htmlFor="name" className="label">Your name</label>
              <input
                id="name"
                type="text"
                autoComplete="name"
                placeholder="Jane Smith"
                className={`input ${errors.name ? 'border-red-400' : ''}`}
                {...register('name')}
              />
              {errors.name && (
                <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" /> {errors.name.message}
                </p>
              )}
            </div>

            {/* Email */}
            <div>
              <label htmlFor="email" className="label">Email address</label>
              <input
                id="email"
                type="email"
                autoComplete="email"
                placeholder="you@yourbusiness.co.uk"
                className={`input ${errors.email ? 'border-red-400' : ''}`}
                {...register('email')}
              />
              {errors.email && (
                <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" /> {errors.email.message}
                </p>
              )}
            </div>

            {/* Password */}
            <div>
              <label htmlFor="password" className="label">Password</label>
              <div className="relative">
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  placeholder="Create a strong password"
                  className={`input pr-12 ${errors.password ? 'border-red-400' : ''}`}
                  {...register('password')}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-1 top-1/2 -translate-y-1/2 w-11 h-11 inline-flex items-center justify-center rounded-full text-slate-500 hover:text-slate-600 transition-colors"
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
              <PasswordStrength password={password} />
              {errors.password && (
                <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" /> {errors.password.message}
                </p>
              )}
            </div>

            {/* Confirm password */}
            <div>
              <label htmlFor="password_confirmation" className="label">Confirm password</label>
              <div className="relative">
                <input
                  id="password_confirmation"
                  type={showConfirm ? 'text' : 'password'}
                  autoComplete="new-password"
                  placeholder="Repeat your password"
                  className={`input pr-12 ${errors.password_confirmation ? 'border-red-400' : ''}`}
                  {...register('password_confirmation')}
                />
                <button
                  type="button"
                  onClick={() => setShowConfirm(!showConfirm)}
                  className="absolute right-1 top-1/2 -translate-y-1/2 w-11 h-11 inline-flex items-center justify-center rounded-full text-slate-500 hover:text-slate-600 transition-colors"
                  aria-label={showConfirm ? 'Hide' : 'Show'}
                >
                  {showConfirm ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
              {errors.password_confirmation && (
                <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" /> {errors.password_confirmation.message}
                </p>
              )}
            </div>

            {/* Access code (dijitul clients). Optional, and hidden until asked for. */}
            <div>
              {showCode ? (
                <>
                  <label htmlFor="access_code" className="label">Access code</label>
                  <input
                    id="access_code"
                    type="text"
                    autoComplete="off"
                    autoCapitalize="characters"
                    className="input"
                    {...register('access_code')}
                  />
                  <p className="mt-1.5 text-xs text-slate-500">Given to you by dijitul. A valid code means there is nothing to pay.</p>
                </>
              ) : (
                <button
                  type="button"
                  onClick={() => setShowCode(true)}
                  className="text-sm font-semibold text-cobalt-700 underline min-h-11"
                >
                  Have an access code?
                </button>
              )}
            </div>

            {/* Terms acceptance */}
            <div>
              <label className="flex items-start gap-3 cursor-pointer group">
                <input
                  type="checkbox"
                  className="mt-0.5 w-4 h-4 rounded border-slate-300 text-cobalt-600 focus:ring-cobalt-600 flex-shrink-0 cursor-pointer"
                  {...register('terms_accepted')}
                />
                <span className="text-sm text-slate-500 leading-relaxed">
                  I agree to the{' '}
                  <Link to="/terms" target="_blank" className="underline text-slate-600 hover:text-cobalt-700 transition-colors">
                    Terms of Service
                  </Link>
                  {' '}and{' '}
                  <Link to="/privacy" target="_blank" className="underline text-slate-600 hover:text-cobalt-700 transition-colors">
                    Privacy Policy
                  </Link>
                </span>
              </label>
              {errors.terms_accepted && (
                <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" /> {errors.terms_accepted.message}
                </p>
              )}
            </div>

            {/* Submit */}
            <button
              type="submit"
              disabled={isLoading}
              className="min-h-[44px] font-display w-full flex items-center justify-center gap-2.5 bg-cobalt-600 text-white font-semibold py-4 px-6 rounded-full hover:bg-cobalt-700 active:scale-[0.98] transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed mt-2"
            >
              {isLoading ? (
                <>
                  <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                  Creating your account...
                </>
              ) : (
                <>
                  Start your free 14-day trial
                  <ArrowRight className="w-4 h-4" />
                </>
              )}
            </button>
          </form>

          {/* No card required note */}
          <div className="flex items-center justify-center gap-2 mt-4 text-xs text-slate-500">
            <Shield className="w-3.5 h-3.5" />
            No credit card required. Upgrade when you&apos;re ready.
          </div>
            </>
          )}
        </div>

        {/* Legal */}
        <p className="text-center text-xs text-slate-500 mt-4 px-4 leading-relaxed">
          By creating an account you agree to our{' '}
          <Link to="/terms" className="underline hover:text-slate-600 transition-colors">Terms of Service</Link>
          {' '}and{' '}
          <Link to="/privacy" className="underline hover:text-slate-600 transition-colors">Privacy Policy</Link>.
          We are registered in England and Wales. All data stored in EU/UK regions.
        </p>

        {/* Login link */}
        <p className="text-center text-sm text-slate-500 mt-5">
          Already have an account?{' '}
          <Link to="/login" className="font-semibold text-cobalt-700 hover:text-cobalt-700 transition-colors">
            Log in
          </Link>
        </p>
      </div>
    </div>
  )
}
