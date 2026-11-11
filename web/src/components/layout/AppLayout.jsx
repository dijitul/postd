import { useState, useEffect } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard, FileText, Share2, Images, CreditCard, Settings,
  LogOut, Menu, X, Bell, ChevronRight
} from 'lucide-react'
import { clsx } from 'clsx'
import Logo from '../ui/Logo.jsx'
import LocationSwitcher from './LocationSwitcher.jsx'
import useAuthStore from '../../stores/authStore.js'
import { postsApi, POSTS_CHANGED_EVENT } from '../../lib/api.js'

let userRefreshedThisLoad = false

const NAV_ITEMS = [
  { to: '/dashboard', icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/posts', icon: FileText, label: 'Posts', badge: true },
  { to: '/platforms', icon: Share2, label: 'Platforms' },
  { to: '/photos', icon: Images, label: 'Photos' },
  // Billing is visited a few times a year, so it gives up its place in the
  // phone's bottom bar (which fits five) and stays in the slide-out menu.
  { to: '/billing', icon: CreditCard, label: 'Billing', bottomBar: false },
  { to: '/settings', icon: Settings, label: 'Settings' }
]

function NavItem({ to, icon: Icon, label, badge, pendingCount, onClick }) {
  const location = useLocation()
  const isActive = location.pathname === to || (to !== '/dashboard' && location.pathname.startsWith(to))

  return (
    <Link
      to={to}
      onClick={onClick}
      aria-current={isActive ? 'page' : undefined}
      className={clsx(
        'relative flex items-center gap-3 min-h-[44px] pl-4 pr-3 py-2.5 rounded-xl text-[15px] font-semibold transition-colors duration-150',
        isActive
          ? 'bg-cobalt-50 text-cobalt-600 before:absolute before:left-0 before:top-2 before:bottom-2 before:w-[3px] before:rounded-r-full before:bg-cobalt-600'
          : 'text-navy-900 hover:bg-cloud'
      )}
    >
      <Icon className="w-5 h-5 flex-shrink-0" />
      <span className="flex-1">{label}</span>
      {badge && pendingCount > 0 && (
        <span className="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-sun-400 text-navy-900 text-xs font-bold">
          {pendingCount > 9 ? '9+' : pendingCount}
        </span>
      )}
    </Link>
  )
}

export function AppLayout({ children }) {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const { user, logout, fetchUser } = useAuthStore()
  const navigate = useNavigate()
  const location = useLocation()

  // The stored user can be days old. Refresh it once per app load so the plan
  // and the current location (which drive the switcher) are up to date. The
  // layout remounts on every route, hence the module-level flag.
  useEffect(() => {
    if (userRefreshedThisLoad) return
    userRefreshedThisLoad = true
    fetchUser()
  }, [fetchUser])

  // Posts awaiting review. Recounted on mount, on navigation, and whenever a
  // page reports that it has approved/rejected/retried something.
  const [pendingCount, setPendingCount] = useState(0)

  useEffect(() => {
    let cancelled = false

    const refresh = async () => {
      try {
        const res = await postsApi.getPending({ per_page: 100 })
        const raw = res.data?.data ?? res.data?.posts ?? []
        // The inbox scope also returns approved posts; only pending ones are
        // actually waiting on the user.
        const waiting = raw.filter((p) => p.status === 'pending').length
        if (! cancelled) setPendingCount(waiting)
      } catch {
        if (! cancelled) setPendingCount(0)
      }
    }

    refresh()
    window.addEventListener(POSTS_CHANGED_EVENT, refresh)
    return () => {
      cancelled = true
      window.removeEventListener(POSTS_CHANGED_EVENT, refresh)
    }
  }, [location.pathname])

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  const businessName = user?.business?.name ?? user?.name ?? 'Your Business'

  return (
    <div className="min-h-screen bg-cloud flex">
      {/* ── Sidebar — desktop ── */}
      <aside className="hidden lg:flex flex-col w-60 bg-white border-r border-line fixed inset-y-0 left-0 z-30">
        {/* Logo */}
        <div className="flex items-center h-16 px-5 border-b border-line flex-shrink-0">
          <Logo size="sm" />
        </div>

        {/* Nav */}
        <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          {NAV_ITEMS.map((item) => (
            <NavItem key={item.to} {...item} pendingCount={pendingCount} />
          ))}
        </nav>

        {/* User section */}
        <div className="border-t border-line p-3">
          <div className="flex items-center gap-3 px-3 py-2 rounded-xl">
            <div className="w-9 h-9 rounded-full bg-cobalt-50 flex items-center justify-center text-cobalt-700 font-display text-sm font-bold flex-shrink-0">
              {businessName.charAt(0).toUpperCase()}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-xs font-semibold text-navy-900 truncate">{businessName}</p>
              <p className="text-xs text-slate-500 truncate">{user?.email}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="mt-1 w-full flex items-center gap-3 min-h-[44px] px-3 py-2 rounded-xl text-sm font-medium text-slate-500 hover:bg-cloud hover:text-danger-text transition-colors duration-150"
          >
            <LogOut className="w-4 h-4" />
            <span>Sign out</span>
          </button>
        </div>
      </aside>

      {/* ── Mobile sidebar overlay ── */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-navy-900/40 z-40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* ── Mobile sidebar drawer ── */}
      <aside
        className={clsx(
          'fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-popover flex flex-col transition-transform duration-300 lg:hidden',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        )}
      >
        <div className="flex items-center justify-between h-16 px-5 border-b border-line">
          <Logo size="sm" />
          <button
            onClick={() => setSidebarOpen(false)}
            className="w-11 h-11 -mr-2 flex items-center justify-center rounded-full text-slate-500 hover:bg-cloud hover:text-navy-900"
            aria-label="Close menu"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          {NAV_ITEMS.map((item) => (
            <NavItem
              key={item.to}
              {...item}
              pendingCount={pendingCount}
              onClick={() => setSidebarOpen(false)}
            />
          ))}
        </nav>

        <div className="border-t border-line p-3">
          <div className="flex items-center gap-3 px-3 py-2 rounded-xl">
            <div className="w-9 h-9 rounded-full bg-cobalt-50 flex items-center justify-center text-cobalt-700 font-display text-sm font-bold flex-shrink-0">
              {businessName.charAt(0).toUpperCase()}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-xs font-semibold text-navy-900 truncate">{businessName}</p>
              <p className="text-xs text-slate-500 truncate">{user?.email}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="mt-1 w-full flex items-center gap-3 min-h-[44px] px-3 py-2 rounded-xl text-sm font-medium text-slate-500 hover:bg-cloud hover:text-danger-text transition-colors duration-150"
          >
            <LogOut className="w-4 h-4" />
            <span>Sign out</span>
          </button>
        </div>
      </aside>

      {/* ── Main content ── */}
      <div className="flex-1 min-w-0 lg:ml-60 flex flex-col min-h-screen">
        {/* Top bar — mobile */}
        <header className="sticky top-0 z-20 bg-white/95 backdrop-blur-sm border-b border-line h-16 flex items-center justify-between px-4 lg:px-6">
          <button
            onClick={() => setSidebarOpen(true)}
            className="w-11 h-11 -ml-2 flex items-center justify-center rounded-full text-navy-900 hover:bg-cloud lg:hidden"
            aria-label="Open menu"
          >
            <Menu className="w-5 h-5" />
          </button>

          <div className="lg:hidden">
            <Logo size="sm" />
          </div>

          <div className="hidden lg:flex items-center gap-2">
            <LocationSwitcher />
          </div>

          <div className="flex items-center gap-2">
            {/* On phones the switcher sits beside the bell, as the left of the
                bar holds the menu button and logo. */}
            <div className="lg:hidden">
              <LocationSwitcher />
            </div>
            <Link
              to="/posts"
              className="relative w-11 h-11 flex items-center justify-center rounded-full text-navy-900 hover:bg-cloud transition-colors"
              aria-label="Posts awaiting review"
            >
              <Bell className="w-5 h-5" />
              {pendingCount > 0 && (
                <span className="absolute top-2 right-2 w-2.5 h-2.5 rounded-full bg-sun-400 ring-2 ring-white" />
              )}
            </Link>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 p-4 sm:p-6 lg:p-8 pb-24 lg:pb-8">
          {children}
        </main>

        {/* ── Bottom nav — mobile only ── */}
        <nav className="fixed bottom-0 left-0 right-0 bg-white border-t border-line lg:hidden z-20 pb-safe">
          <div className="flex items-center justify-around px-2 py-1.5">
            {NAV_ITEMS.filter((item) => item.bottomBar !== false).map(({ to, icon: Icon, label, badge }) => {
              const isActive = location.pathname === to || (to !== '/dashboard' && location.pathname.startsWith(to))
              return (
                <Link
                  key={to}
                  to={to}
                  className={clsx(
                    'relative flex flex-col items-center justify-center gap-0.5 min-h-[48px] min-w-[56px] px-2 py-1.5 rounded-xl transition-colors duration-150',
                    isActive ? 'text-cobalt-600 bg-cobalt-50' : 'text-slate-500 hover:text-navy-900'
                  )}
                >
                  <Icon className="w-5 h-5" />
                  <span className="text-2xs font-semibold truncate">{label}</span>
                  {badge && pendingCount > 0 && (
                    <span className="absolute top-1 right-2 w-2.5 h-2.5 rounded-full bg-sun-400 ring-2 ring-white" />
                  )}
                </Link>
              )
            })}
          </div>
        </nav>
      </div>
    </div>
  )
}

export default AppLayout
