import { useState } from 'react'
import { Link, useNavigate, useLocation, useSearchParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Eye, EyeOff, ArrowRight, AlertCircle } from 'lucide-react'
import Logo from '../../components/ui/Logo.jsx'
import useAuthStore from '../../stores/authStore.js'
import { Seo } from '../../lib/head.jsx'

function GoogleIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
      <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
      <path d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
      <path d="M3.964 10.707A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.707V4.961H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.039l3.007-2.332z" fill="#FBBC05"/>
      <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.961L3.964 7.293C4.672 5.166 6.656 3.58 9 3.58z" fill="#EA4335"/>
    </svg>
  )
}

const schema = z.object({
  email: z.string().email('Please enter a valid email address'),
  password: z.string().min(1, 'Password is required')
})

export default function LoginPage() {
  const [showPassword, setShowPassword] = useState(false)
  const { login, loginWithGoogle, isLoading, error, clearError } = useAuthStore()
  const navigate = useNavigate()
  const location = useLocation()
  const [searchParams] = useSearchParams()
  const from = location.state?.from ?? '/dashboard'
  const oauthError = searchParams.get('error')

  const { register, handleSubmit, formState: { errors } } = useForm({
    resolver: zodResolver(schema)
  })

  const onSubmit = async (data) => {
    clearError()
    const result = await login(data)
    if (result.success) {
      navigate(from, { replace: true })
    }
  }

  return (
    <div className="min-h-screen bg-cloud flex flex-col items-center justify-center px-4 py-12">
      <Seo
        title="Log in | postd.uk"
        description="Log in to postd.uk to review, approve and schedule the social media posts written for your business."
        path="/login"
        robots="noindex, follow"
      />

      {/* Card */}
      <div className="w-full max-w-md">

        {/* Logo */}
        <div className="text-center mb-8">
          <Logo size="lg" className="justify-center" />
          <h1 className="font-display font-extrabold text-[1.75rem] tracking-tight text-navy-900 mt-6 mb-2">
            Welcome back
          </h1>
          <p className="text-slate-500 text-sm">
            Log in to your postd.uk account
          </p>
        </div>

        <div className="card-marketing p-6 sm:p-8">

          {/* OAuth error */}
          {oauthError && (
            <div className="flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-6">
              <AlertCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
              <p className="text-sm text-red-700">Something went wrong with Google sign-in. Please try again.</p>
            </div>
          )}

          {/* Global error */}
          {error && (
            <div className="flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-6">
              <AlertCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
              <p className="text-sm text-red-700">{error}</p>
            </div>
          )}

          {/* Google sign-in — primary CTA */}
          <button
            type="button"
            onClick={loginWithGoogle}
            className="min-h-[44px] font-display w-full flex items-center justify-center gap-3 bg-white border-[1.5px] border-line-strong text-navy-900 font-semibold py-3.5 px-6 rounded-full hover:border-line-strong hover:bg-cloud active:scale-[0.98] transition-all duration-200 mb-4"
          >
            <GoogleIcon />
            Continue with Google
          </button>

          {/* Divider */}
          <div className="flex items-center gap-3 mb-4">
            <div className="flex-1 h-px bg-line" />
            <span className="text-xs text-slate-500 font-medium">or sign in with email</span>
            <div className="flex-1 h-px bg-line" />
          </div>

          <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-5">

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
                  <AlertCircle className="w-3 h-3" />
                  {errors.email.message}
                </p>
              )}
            </div>

            {/* Password */}
            <div>
              <div className="flex items-center justify-between mb-1.5">
                <label htmlFor="password" className="label mb-0">Password</label>
                <Link to="/forgot-password" className="text-xs text-cobalt-700 hover:text-cobalt-700 font-medium transition-colors">
                  Forgot password?
                </Link>
              </div>
              <div className="relative">
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  placeholder="Your password"
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
              {errors.password && (
                <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" />
                  {errors.password.message}
                </p>
              )}
            </div>

            {/* Submit */}
            <button
              type="submit"
              disabled={isLoading}
              className="min-h-[44px] font-display w-full flex items-center justify-center gap-2.5 bg-cobalt-600 text-white font-semibold py-3.5 px-6 rounded-full hover:bg-cobalt-700 active:scale-[0.98] transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed mt-2"
            >
              {isLoading ? (
                <>
                  <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                  Signing in...
                </>
              ) : (
                <>
                  Log in
                  <ArrowRight className="w-4 h-4" />
                </>
              )}
            </button>
          </form>
        </div>

        {/* Register link */}
        <p className="text-center text-sm text-slate-500 mt-6">
          Don&apos;t have an account?{' '}
          <Link to="/register" className="font-semibold text-cobalt-700 hover:text-cobalt-700 transition-colors">
            Start a free trial
          </Link>
        </p>

        {/* Back home */}
        <div className="text-center mt-4">
          <Link to="/" className="text-xs text-slate-500 hover:text-slate-600 transition-colors">
            Back to postd.uk
          </Link>
        </div>
      </div>
    </div>
  )
}
