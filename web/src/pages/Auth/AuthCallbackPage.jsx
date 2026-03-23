import { useEffect } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import useAuthStore from '../../stores/authStore.js'
import Logo from '../../components/ui/Logo.jsx'

export default function AuthCallbackPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { loginWithToken } = useAuthStore()

  useEffect(() => {
    const token    = params.get('token')
    const redirect = params.get('redirect') ?? 'dashboard'
    const error    = params.get('error')

    if (error) {
      navigate('/login?error=' + error, { replace: true })
      return
    }

    if (!token) {
      navigate('/login?error=no_token', { replace: true })
      return
    }

    loginWithToken(token).then(() => {
      navigate('/' + redirect, { replace: true })
    })
  }, [])

  return (
    <div className="min-h-screen bg-cream-200 honeycomb-bg flex flex-col items-center justify-center">
      <Logo size="lg" className="justify-center mb-8" />
      <div className="flex items-center gap-3 text-slate-500">
        <span className="w-5 h-5 border-2 border-amber-400 border-t-transparent rounded-full animate-spin" />
        <span className="text-sm font-medium">Signing you in...</span>
      </div>
    </div>
  )
}
