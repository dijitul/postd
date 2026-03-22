import { Routes, Route, Navigate } from 'react-router-dom'
import { Suspense, lazy } from 'react'
import useAuthStore from './stores/authStore.js'
import AppLayout from './components/layout/AppLayout.jsx'

// ── Pages — lazy loaded for better performance ──────────────────────────────
const MarketingPage   = lazy(() => import('./pages/Marketing/index.jsx'))
const LoginPage       = lazy(() => import('./pages/Auth/LoginPage.jsx'))
const RegisterPage    = lazy(() => import('./pages/Auth/RegisterPage.jsx'))
const OnboardingPage  = lazy(() => import('./pages/Onboarding/index.jsx'))
const Dashboard       = lazy(() => import('./pages/Dashboard/index.jsx'))
const PostsPage       = lazy(() => import('./pages/Posts/PostsPage.jsx'))
const InboxPage       = lazy(() => import('./pages/Posts/InboxPage.jsx'))
const PlatformsPage   = lazy(() => import('./pages/Platforms/index.jsx'))
const BillingPage     = lazy(() => import('./pages/Billing/index.jsx'))
const SettingsPage    = lazy(() => import('./pages/Settings/index.jsx'))
const AdminPage       = lazy(() => import('./pages/Admin/index.jsx'))

// ── Loading fallback ─────────────────────────────────────────────────────────
function PageLoader() {
  return (
    <div className="min-h-screen bg-cream-200 flex items-center justify-center">
      <div className="flex flex-col items-center gap-4">
        <div className="w-12 h-12 rounded-2xl bg-amber-500 flex items-center justify-center animate-pulse">
          <svg viewBox="0 0 64 64" className="w-8 h-8" fill="none">
            <rect x="8" y="16" width="32" height="22" rx="6" fill="white" />
            <path d="M20 38 L17 46 L27 38 Z" fill="white" />
            <path d="M26 20 L20 30 L24.5 30 L22 37 L28 27 L23.5 27 Z" fill="#E07B30" />
          </svg>
        </div>
        <p className="text-sm font-semibold text-slate-400 font-display">Loading...</p>
      </div>
    </div>
  )
}

// ── Route guards ─────────────────────────────────────────────────────────────
function ProtectedRoute({ children }) {
  const { isAuthenticated, token } = useAuthStore()
  const hasToken = isAuthenticated || Boolean(token || localStorage.getItem('postd_token'))
  if (!hasToken) return <Navigate to="/login" replace />
  return children
}

function AdminRoute({ children }) {
  const { isAuthenticated, user, token } = useAuthStore()
  const hasToken = isAuthenticated || Boolean(token || localStorage.getItem('postd_token'))
  if (!hasToken) return <Navigate to="/login" replace />
  if (user && !user.is_admin) return <Navigate to="/dashboard" replace />
  return children
}

function GuestRoute({ children }) {
  const { isAuthenticated } = useAuthStore()
  if (isAuthenticated) return <Navigate to="/dashboard" replace />
  return children
}

// ── Protected layout wrapper ──────────────────────────────────────────────────
function ProtectedLayout({ children }) {
  return (
    <ProtectedRoute>
      <AppLayout>{children}</AppLayout>
    </ProtectedRoute>
  )
}

export default function App() {
  return (
    <Suspense fallback={<PageLoader />}>
      <Routes>
        {/* Public */}
        <Route path="/" element={<MarketingPage />} />

        {/* Auth — guests only */}
        <Route path="/login" element={<GuestRoute><LoginPage /></GuestRoute>} />
        <Route path="/register" element={<GuestRoute><RegisterPage /></GuestRoute>} />

        {/* Onboarding — auth required, own layout */}
        <Route
          path="/onboarding"
          element={<ProtectedRoute><OnboardingPage /></ProtectedRoute>}
        />

        {/* App — auth required, sidebar layout */}
        <Route path="/dashboard" element={<ProtectedLayout><Dashboard /></ProtectedLayout>} />
        <Route path="/posts" element={<ProtectedLayout><PostsPage /></ProtectedLayout>} />
        <Route path="/posts/inbox" element={<ProtectedLayout><InboxPage /></ProtectedLayout>} />
        <Route path="/platforms" element={<ProtectedLayout><PlatformsPage /></ProtectedLayout>} />
        <Route path="/billing" element={<ProtectedLayout><BillingPage /></ProtectedLayout>} />
        <Route path="/settings" element={<ProtectedLayout><SettingsPage /></ProtectedLayout>} />

        {/* Admin */}
        <Route
          path="/admin"
          element={<AdminRoute><AdminPage /></AdminRoute>}
        />

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Suspense>
  )
}
