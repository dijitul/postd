import axios from 'axios'

const API_BASE_URL = (import.meta.env.VITE_API_URL || 'https://api.postd.uk') + '/api'

export const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  },
  withCredentials: true,
  timeout: 30000
})

// Request interceptor — inject auth token from localStorage
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('postd_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor — handle 401 globally
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('postd_token')
      localStorage.removeItem('postd_user')
      // Let the auth store handle the redirect
      window.dispatchEvent(new CustomEvent('postd:auth:expired'))
    }
    return Promise.reject(error)
  }
)

// ─── Auth endpoints ───────────────────────────────────────────────────────────
export const authApi = {
  login: (data) => api.post('/auth/login', data),
  register: (data) => api.post('/auth/register', data),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/user'),
  forgotPassword: (email) => api.post('/auth/forgot-password', { email }),
  resetPassword: (data) => api.post('/auth/reset-password', data)
}

// ─── Onboarding endpoints ─────────────────────────────────────────────────────
export const onboardingApi = {
  updateBusiness: (data) => api.post('/onboarding/business', data),
  scrapeWebsite: (url) => api.post('/onboarding/scrape', { url }),
  setGoogleReviews: (url) => api.post('/onboarding/google-reviews', { url }),
  connectPlatform: (platform) => api.post(`/onboarding/platforms/${platform}/connect`),
  complete: () => api.post('/onboarding/complete')
}

// ─── Posts endpoints ──────────────────────────────────────────────────────────
export const postsApi = {
  getPending: (params) => api.get('/posts/inbox', { params }),
  getAll: (params) => api.get('/posts', { params }),
  approve: (id) => api.post(`/posts/${id}/approve`),
  reject: (id) => api.post(`/posts/${id}/reject`),
  update: (id, data) => api.put(`/posts/${id}`, data),
  generate: (data) => api.post('/posts/generate', data),
  submitIdea: (data) => api.post('/posts/idea', data),
  retry: (id) => api.post(`/posts/${id}/retry`),
}

// ─── Platforms endpoints ──────────────────────────────────────────────────────
export const platformsApi = {
  getAll: () => api.get('/social/connections'),
  connect: (platform) => api.post(`/social/connect/${platform}`),
  disconnect: (connectionId) => api.delete(`/social/connections/${connectionId}`),
  refreshToken: (connectionId) => api.post(`/social/connections/${connectionId}/refresh`),
}

// ─── Billing endpoints ────────────────────────────────────────────────────────
export const billingApi = {
  getSubscription: () => api.get('/billing/subscription'),
  getPlans: () => api.get('/billing/plans'),
  createCheckout: (plan) => api.post('/billing/checkout', { plan }),
  createPortal: () => api.post('/billing/portal'),
  getInvoices: () => api.get('/billing/invoices')
}

// ─── Settings endpoints ───────────────────────────────────────────────────────
export const settingsApi = {
  get: () => api.get('/settings'),
  update: (data) => api.put('/settings', data),
  updateBusiness: (data) => api.put('/onboarding/business', data),
  updateNotifications: (data) => api.put('/settings/notifications', data),
  deleteAccount: () => api.delete('/settings/account')
}

// ─── Admin endpoints ──────────────────────────────────────────────────────────
export const adminApi = {
  getMetrics: () => api.get('/admin/stats'),
  getAtRisk: () => api.get('/admin/at-risk'),
  getBusinesses: (params) => api.get('/admin/businesses', { params }),
  getBusiness: (id) => api.get(`/admin/businesses/${id}`),
  getSystemHealth: () => api.get('/admin/health')
}

export default api
