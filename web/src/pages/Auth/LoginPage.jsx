import { useState } from 'react'
import { Link, useNavigate, useLocation } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Eye, EyeOff, ArrowRight, AlertCircle } from 'lucide-react'
import Logo from '../../components/ui/Logo.jsx'
import useAuthStore from '../../stores/authStore.js'

const schema = z.object({
  email: z.string().email('Please enter a valid email address'),
  password: z.string().min(1, 'Password is required')
})

export default function LoginPage() {
  const [showPassword, setShowPassword] = useState(false)
  const { login, isLoading, error, clearError } = useAuthStore()
  const navigate = useNavigate()
  const location = useLocation()
  const from = location.state?.from ?? '/dashboard'

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
    <div className="min-h-screen bg-cream-200 honeycomb-bg flex flex-col items-center justify-center px-4 py-12">

      {/* Card */}
      <div className="w-full max-w-md">

        {/* Logo */}
        <div className="text-center mb-8">
          <Logo size="lg" className="justify-center" />
          <h1 className="font-display font-bold text-2xl text-navy-800 mt-6 mb-2">
            Welcome back
          </h1>
          <p className="text-slate-500 text-sm">
            Log in to your postd.uk account
          </p>
        </div>

        <div className="bg-white rounded-3xl p-7 sm:p-8 border border-cream-300" style={{ boxShadow: '0 8px 32px rgb(30 45 74 / 0.08)' }}>

          {/* Global error */}
          {error && (
            <div className="flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-6">
              <AlertCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
              <p className="text-sm text-red-700">{error}</p>
            </div>
          )}

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
                <Link to="/forgot-password" className="text-xs text-amber-600 hover:text-amber-700 font-medium transition-colors">
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
                  className="absolute right-3 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-slate-400 hover:text-slate-600 transition-colors"
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
              className="w-full flex items-center justify-center gap-2.5 bg-amber-500 text-white font-bold py-3.5 px-6 rounded-xl hover:bg-amber-700 active:scale-[0.98] transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed mt-2"
              style={{ boxShadow: '0 4px 16px rgb(224 123 48 / 0.3)' }}
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
          <Link to="/register" className="font-semibold text-amber-600 hover:text-amber-700 transition-colors">
            Start a free trial
          </Link>
        </p>

        {/* Back home */}
        <div className="text-center mt-4">
          <Link to="/" className="text-xs text-slate-400 hover:text-slate-600 transition-colors">
            Back to postd.uk
          </Link>
        </div>
      </div>
    </div>
  )
}
