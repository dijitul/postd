import { useEffect } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import useAuthStore from '../stores/authStore.js'

/**
 * Core auth hook — wraps the Zustand store with redirect logic.
 * Use this in protected pages and navigation components.
 */
export function useAuth({ requireAuth = false, requireAdmin = false, redirectTo } = {}) {
  const navigate = useNavigate()
  const location = useLocation()

  const {
    user,
    token,
    isAuthenticated,
    isLoading,
    error,
    login,
    logout,
    register,
    fetchUser,
    clearError,
    updateUser
  } = useAuthStore()

  // Sync auth state on mount
  useEffect(() => {
    if (token && !user) {
      fetchUser()
    }
  }, [token, user, fetchUser])

  // Redirect unauthenticated users away from protected routes
  useEffect(() => {
    if (requireAuth && !isLoading && !isAuthenticated) {
      navigate('/login', {
        replace: true,
        state: { from: location.pathname }
      })
    }
  }, [requireAuth, isLoading, isAuthenticated, navigate, location.pathname])

  // Redirect non-admin users away from admin routes
  useEffect(() => {
    if (requireAdmin && isAuthenticated && user && !user.is_admin) {
      navigate('/dashboard', { replace: true })
    }
  }, [requireAdmin, isAuthenticated, user, navigate])

  // Redirect authenticated users away from auth pages
  useEffect(() => {
    if (redirectTo && isAuthenticated) {
      navigate(redirectTo, { replace: true })
    }
  }, [redirectTo, isAuthenticated, navigate])

  const isAdmin = user?.is_admin ?? false
  const hasCompletedOnboarding = user?.onboarding_completed_at != null
  const businessName = user?.business?.name ?? user?.name ?? ''
  const plan = user?.subscription?.plan ?? 'trial'
  const isOnTrial = user?.trial_ends_at != null && new Date(user.trial_ends_at) > new Date()

  return {
    user,
    token,
    isAuthenticated,
    isLoading,
    error,
    isAdmin,
    businessName,
    plan,
    isOnTrial,
    hasCompletedOnboarding,
    login,
    logout,
    register,
    fetchUser,
    clearError,
    updateUser
  }
}

/**
 * Guard hook for protected pages. Renders nothing until auth is confirmed.
 */
export function useRequireAuth() {
  return useAuth({ requireAuth: true })
}

/**
 * Guard hook for admin pages.
 */
export function useRequireAdmin() {
  return useAuth({ requireAuth: true, requireAdmin: true })
}

export default useAuth
