import { Routes, Route, Navigate } from 'react-router-dom'
import { Suspense, lazy, useEffect } from 'react'
import useAuthStore from './stores/authStore.js'
import useHydrated from './lib/useHydrated.js'
import AppLayout from './components/layout/AppLayout.jsx'
import CookieBanner from './components/ui/CookieBanner.jsx'

// ── Pages — lazy loaded for better performance ──────────────────────────────
const MarketingPage   = lazy(() => import('./pages/Marketing/index.jsx'))
const LoginPage        = lazy(() => import('./pages/Auth/LoginPage.jsx'))
const RegisterPage     = lazy(() => import('./pages/Auth/RegisterPage.jsx'))
const AuthCallbackPage = lazy(() => import('./pages/Auth/AuthCallbackPage.jsx'))
const OnboardingPage  = lazy(() => import('./pages/Onboarding/index.jsx'))
const Dashboard       = lazy(() => import('./pages/Dashboard/index.jsx'))
const PostsPage       = lazy(() => import('./pages/Posts/PostsPage.jsx'))
const PlatformsPage   = lazy(() => import('./pages/Platforms/index.jsx'))
const BillingPage     = lazy(() => import('./pages/Billing/index.jsx'))
const SettingsPage    = lazy(() => import('./pages/Settings/index.jsx'))
const AdminPage       = lazy(() => import('./pages/Admin/index.jsx'))
const TermsPage       = lazy(() => import('./pages/Legal/TermsPage.jsx'))
const PrivacyPage     = lazy(() => import('./pages/Legal/PrivacyPage.jsx'))
const GuidesIndexPage = lazy(() => import('./pages/Guides/GuidesIndexPage.jsx'))
const GuidePage       = lazy(() => import('./pages/Guides/GuidePage.jsx'))
const NotFoundPage    = lazy(() => import('./pages/NotFoundPage.jsx'))

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
// Guarded so the build-time prerender (no window, no localStorage) can import
// this file. App routes are never prerendered, but public routes share it.
function storedToken() {
  try {
    return typeof localStorage === 'undefined' ? null : localStorage.getItem('postd_token')
  } catch {
    return null
  }
}

// Public pages set their own title through <Seo />. Signed-in pages share one,
// so a title such as "Log in | postd.uk" does not linger after signing in.
function useAppTitle() {
  useEffect(() => {
    document.title = 'postd.uk'
  }, [])
}

function ProtectedRoute({ children }) {
  useAppTitle()
  const { isAuthenticated, token } = useAuthStore()
  const hasToken = isAuthenticated || Boolean(token || storedToken())
  if (!hasToken) return <Navigate to="/login" replace />
  return children
}

function AdminRoute({ children }) {
  useAppTitle()
  const { isAuthenticated, user, token } = useAuthStore()
  const hasToken = isAuthenticated || Boolean(token || storedToken())
  if (!hasToken) return <Navigate to="/login" replace />
  if (user && !user.is_admin) return <Navigate to="/dashboard" replace />
  return children
}

function GuestRoute({ children }) {
  const { isAuthenticated } = useAuthStore()
  // /login and /register are prerendered signed out. Wait for hydration to
  // finish before redirecting, so the first client render matches the HTML.
  const hydrated = useHydrated()
  if (hydrated && isAuthenticated) return <Navigate to="/dashboard" replace />
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
      <CookieBanner />
      <Routes>
        {/* Public */}
        <Route path="/" element={<MarketingPage />} />
        <Route path="/terms" element={<TermsPage />} />
        <Route path="/privacy" element={<PrivacyPage />} />
        <Route path="/guides" element={<GuidesIndexPage />} />
        <Route path="/guides/:slug" element={<GuidePage />} />

        {/* Auth — guests only */}
        <Route path="/login" element={<GuestRoute><LoginPage /></GuestRoute>} />
        <Route path="/register" element={<GuestRoute><RegisterPage /></GuestRoute>} />

        {/* OAuth callback — public, handles token from Google auth */}
        <Route path="/auth/callback" element={<AuthCallbackPage />} />

        {/* Onboarding — auth required, own layout */}
        <Route
          path="/onboarding"
          element={<ProtectedRoute><OnboardingPage /></ProtectedRoute>}
        />

        {/* App — auth required, sidebar layout */}
        <Route path="/dashboard" element={<ProtectedLayout><Dashboard /></ProtectedLayout>} />
        <Route path="/posts" element={<ProtectedLayout><PostsPage /></ProtectedLayout>} />
        {/* The inbox is now a tab on /posts — keep the old path working for
            bookmarks and any links already sent out in emails. */}
        <Route path="/posts/inbox" element={<Navigate to="/posts" replace />} />
        <Route path="/platforms" element={<ProtectedLayout><PlatformsPage /></ProtectedLayout>} />
        <Route path="/billing" element={<ProtectedLayout><BillingPage /></ProtectedLayout>} />
        <Route path="/settings" element={<ProtectedLayout><SettingsPage /></ProtectedLayout>} />

        {/* Admin */}
        <Route
          path="/admin"
          element={<AdminRoute><AdminPage /></AdminRoute>}
        />

        {/* Fallback: a real "not found" page. nginx serves the prerendered
            copy with a 404 status, so unknown URLs are not indexed as
            duplicates of the home page. */}
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </Suspense>
  )
}
