import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { authApi } from '../lib/api.js'

const useAuthStore = create(
  persist(
    (set, get) => ({
      user: null,
      token: null,
      isAuthenticated: false,
      isLoading: false,
      error: null,

      login: async (credentials) => {
        set({ isLoading: true, error: null })
        try {
          const { data } = await authApi.login(credentials)
          const { user, token } = data.data ?? data
          localStorage.setItem('postd_token', token)
          set({ user, token, isAuthenticated: true, isLoading: false })
          return { success: true, user }
        } catch (err) {
          const message = err.response?.data?.message || 'Login failed. Please check your details.'
          set({ error: message, isLoading: false })
          return { success: false, error: message }
        }
      },

      register: async (formData) => {
        set({ isLoading: true, error: null })
        try {
          const { data } = await authApi.register(formData)
          const { user, token } = data.data ?? data
          localStorage.setItem('postd_token', token)
          set({ user, token, isAuthenticated: true, isLoading: false })
          return { success: true, user }
        } catch (err) {
          const message = err.response?.data?.message || 'Registration failed. Please try again.'
          set({ error: message, isLoading: false })
          return { success: false, error: message }
        }
      },

      logout: async () => {
        try {
          await authApi.logout()
        } catch (_) {
          // Swallow logout errors — always clear local state
        } finally {
          localStorage.removeItem('postd_token')
          localStorage.removeItem('postd_user')
          set({ user: null, token: null, isAuthenticated: false, error: null })
        }
      },

      fetchUser: async () => {
        const token = get().token || localStorage.getItem('postd_token')
        if (!token) return
        set({ isLoading: true })
        try {
          const { data } = await authApi.me()
          const user = data.data ?? data
          set({ user, isAuthenticated: true, isLoading: false })
        } catch (err) {
          set({ isLoading: false })
          // Only clear auth on a genuine 401 — network errors or 500s should not log the user out
          if (err.response?.status === 401) {
            localStorage.removeItem('postd_token')
            set({ user: null, token: null, isAuthenticated: false })
          }
        }
      },

      setError: (error) => set({ error }),
      clearError: () => set({ error: null }),

      updateUser: (updates) =>
        set((state) => ({ user: state.user ? { ...state.user, ...updates } : null }))
    }),
    {
      name: 'postd-auth',
      partialize: (state) => ({ token: state.token, user: state.user, isAuthenticated: state.isAuthenticated })
    }
  )
)

export default useAuthStore
