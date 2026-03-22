import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard, FileText, Share2, CreditCard, Settings,
  LogOut, Menu, X, Bell, ChevronRight, Inbox
} from 'lucide-react'
import { clsx } from 'clsx'
import Logo from '../ui/Logo.jsx'
import useAuthStore from '../../stores/authStore.js'

const NAV_ITEMS = [
  { to: '/dashboard', icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/posts/inbox', icon: Inbox, label: 'Inbox', badge: true },
  { to: '/posts', icon: FileText, label: 'All Posts' },
  { to: '/platforms', icon: Share2, label: 'Platforms' },
  { to: '/billing', icon: CreditCard, label: 'Billing' },
  { to: '/settings', icon: Settings, label: 'Settings' }
]

function NavItem({ to, icon: Icon, label, badge, pendingCount, onClick }) {
  const location = useLocation()
  const isActive = location.pathname === to || (to !== '/dashboard' && location.pathname.startsWith(to))

  return (
    <Link
      to={to}
      onClick={onClick}
      className={clsx(
        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all duration-200',
        isActive
          ? 'bg-amber-500 text-white shadow-md'
          : 'text-navy-700 hover:bg-cream-300 hover:text-navy-800'
      )}
    >
      <Icon className="w-5 h-5 flex-shrink-0" />
      <span className="flex-1">{label}</span>
      {badge && pendingCount > 0 && (
        <span className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-500 text-white text-xs font-bold">
          {pendingCount > 9 ? '9+' : pendingCount}
        </span>
      )}
    </Link>
  )
}

export function AppLayout({ children }) {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const { user, logout } = useAuthStore()
  const navigate = useNavigate()
  const location = useLocation()

  // Mock pending count — replace with real query
  const pendingCount = 3

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  const businessName = user?.business?.name ?? user?.name ?? 'Your Business'

  return (
    <div className="min-h-screen bg-cream-200 flex">
      {/* ── Sidebar — desktop ── */}
      <aside className="hidden lg:flex flex-col w-60 bg-white border-r border-cream-300 fixed inset-y-0 left-0 z-30">
        {/* Logo */}
        <div className="flex items-center h-16 px-5 border-b border-cream-300 flex-shrink-0">
          <Logo size="sm" />
        </div>

        {/* Nav */}
        <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          {NAV_ITEMS.map((item) => (
            <NavItem key={item.to} {...item} pendingCount={pendingCount} />
          ))}
        </nav>

        {/* User section */}
        <div className="border-t border-cream-300 p-3">
          <div className="flex items-center gap-3 px-3 py-2 rounded-xl">
            <div className="w-8 h-8 rounded-full bg-amber-500 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
              {businessName.charAt(0).toUpperCase()}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-xs font-semibold text-navy-800 truncate">{businessName}</p>
              <p className="text-xs text-slate-500 truncate">{user?.email}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="mt-1 w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-500 hover:bg-cream-300 hover:text-red-600 transition-all duration-200"
          >
            <LogOut className="w-4 h-4" />
            <span>Sign out</span>
          </button>
        </div>
      </aside>

      {/* ── Mobile sidebar overlay ── */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-navy-800/40 z-40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* ── Mobile sidebar drawer ── */}
      <aside
        className={clsx(
          'fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-2xl flex flex-col transition-transform duration-300 lg:hidden',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        )}
      >
        <div className="flex items-center justify-between h-16 px-5 border-b border-cream-300">
          <Logo size="sm" />
          <button
            onClick={() => setSidebarOpen(false)}
            className="p-2 rounded-xl text-slate-500 hover:bg-cream-300"
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

        <div className="border-t border-cream-300 p-3">
          <div className="flex items-center gap-3 px-3 py-2 rounded-xl">
            <div className="w-8 h-8 rounded-full bg-amber-500 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
              {businessName.charAt(0).toUpperCase()}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-xs font-semibold text-navy-800 truncate">{businessName}</p>
              <p className="text-xs text-slate-500 truncate">{user?.email}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="mt-1 w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-500 hover:bg-cream-300 hover:text-red-600 transition-all duration-200"
          >
            <LogOut className="w-4 h-4" />
            <span>Sign out</span>
          </button>
        </div>
      </aside>

      {/* ── Main content ── */}
      <div className="flex-1 lg:ml-60 flex flex-col min-h-screen">
        {/* Top bar — mobile */}
        <header className="sticky top-0 z-20 bg-white/90 backdrop-blur-sm border-b border-cream-300 h-16 flex items-center justify-between px-4 lg:px-6">
          <button
            onClick={() => setSidebarOpen(true)}
            className="p-2 rounded-xl text-slate-500 hover:bg-cream-300 lg:hidden"
            aria-label="Open menu"
          >
            <Menu className="w-5 h-5" />
          </button>

          <div className="lg:hidden">
            <Logo size="sm" />
          </div>

          <div className="hidden lg:flex items-center gap-2">
            {/* Breadcrumb placeholder */}
          </div>

          <div className="flex items-center gap-2">
            <Link
              to="/posts/inbox"
              className="relative p-2 rounded-xl text-slate-500 hover:bg-cream-300 transition-all"
              aria-label="Post inbox"
            >
              <Bell className="w-5 h-5" />
              {pendingCount > 0 && (
                <span className="absolute top-1 right-1 w-2 h-2 rounded-full bg-red-500" />
              )}
            </Link>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 p-4 sm:p-6 lg:p-8 pb-24 lg:pb-8">
          {children}
        </main>

        {/* ── Bottom nav — mobile only ── */}
        <nav className="fixed bottom-0 left-0 right-0 bg-white border-t border-cream-300 lg:hidden z-20 pb-safe">
          <div className="flex items-center justify-around px-2 py-2">
            {NAV_ITEMS.slice(0, 5).map(({ to, icon: Icon, label, badge }) => {
              const isActive = location.pathname === to || (to !== '/dashboard' && location.pathname.startsWith(to))
              return (
                <Link
                  key={to}
                  to={to}
                  className={clsx(
                    'relative flex flex-col items-center gap-0.5 px-3 py-2 rounded-xl transition-all duration-200 min-w-0',
                    isActive ? 'text-amber-500' : 'text-slate-400 hover:text-navy-800'
                  )}
                >
                  <Icon className={clsx('w-5 h-5', isActive && 'scale-110')} />
                  <span className="text-2xs font-medium truncate">{label}</span>
                  {badge && pendingCount > 0 && (
                    <span className="absolute top-1 right-1 w-2 h-2 rounded-full bg-red-500" />
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
