import { useState, useEffect, useRef } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Building2, Check, ChevronDown, Plus, Loader2, Lock } from 'lucide-react'
import { businessesApi } from '../../lib/api.js'
import useAuthStore from '../../stores/authStore.js'

// Plans that include more than one location. Anyone else only sees the
// switcher if they already own several (for example after a downgrade).
const MULTI_LOCATION_PLANS = ['agency', 'pro']

/**
 * Header dropdown for moving between locations and adding another.
 *
 * Switching reloads the page: every screen reads "the current business" from
 * the API, and a clean load is the simplest way to be sure none of them is
 * still showing the previous location's posts.
 */
export default function LocationSwitcher() {
  const { user, fetchUser } = useAuthStore()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(false)
  const [switchingTo, setSwitchingTo] = useState(null)
  const [error, setError] = useState(null)
  const ref = useRef(null)

  const visible = (user?.business_count ?? 0) > 1 || MULTI_LOCATION_PLANS.includes(user?.active_plan)

  useEffect(() => {
    if (!open) return
    const onClick = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false) }
    const onKey = (e) => { if (e.key === 'Escape') setOpen(false) }
    document.addEventListener('mousedown', onClick)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onClick)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  useEffect(() => {
    if (!open || data) return
    setLoading(true)
    businessesApi.getAll()
      .then((res) => setData(res.data))
      .catch(() => setError('Could not load your locations.'))
      .finally(() => setLoading(false))
  }, [open, data])

  if (!visible) return null

  const handleSwitch = async (id) => {
    setSwitchingTo(id)
    setError(null)
    try {
      await businessesApi.switchTo(id)
      await fetchUser()
      window.location.assign('/dashboard')
    } catch {
      setError('Could not switch location. Please try again.')
      setSwitchingTo(null)
    }
  }

  const addBlocked = data?.add_location
  // An extra Agency location can still be added; onboarding shows the price
  // and asks first. Only a plan without room sends people to Billing.
  const needsUpgrade = addBlocked && addBlocked.error === 'location_limit'
  const currentName = user?.business?.name ?? 'Your business'

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="menu"
        aria-expanded={open}
        className="flex items-center gap-2 max-w-[10rem] sm:max-w-[16rem] px-3 py-2 rounded-xl text-sm font-semibold text-navy-800 hover:bg-cream-300 transition-all"
      >
        <Building2 className="w-4 h-4 text-amber-500 flex-shrink-0" />
        <span className="truncate">{currentName}</span>
        <ChevronDown className={`w-4 h-4 text-slate-400 flex-shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div role="menu" className="absolute right-0 lg:left-0 lg:right-auto mt-2 w-72 max-w-[calc(100vw-2rem)] bg-white rounded-2xl border border-cream-300 shadow-xl p-2 z-50">
          <p className="px-3 pt-1 pb-2 text-xs font-bold text-slate-500 uppercase tracking-wide">
            Locations{data ? ` (${data.businesses.length} of ${data.location_limit})` : ''}
          </p>

          {loading && (
            <div className="px-3 py-3 text-sm text-slate-400 flex items-center gap-2">
              <Loader2 className="w-4 h-4 animate-spin" /> Loading...
            </div>
          )}

          {data?.businesses.map((b) => (
            <button
              key={b.id}
              role="menuitem"
              onClick={() => (b.is_current ? setOpen(false) : handleSwitch(b.id))}
              disabled={switchingTo != null}
              className="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left hover:bg-cream-200 transition-all disabled:opacity-60"
            >
              <span className="flex-1 min-w-0">
                <span className="block text-sm font-semibold text-navy-800 truncate">{b.name}</span>
                <span className="block text-xs text-slate-500 truncate">
                  {b.onboarding_complete ? (b.city || 'Set up') : 'Setup not finished'}
                </span>
              </span>
              {switchingTo === b.id
                ? <Loader2 className="w-4 h-4 animate-spin text-slate-400" />
                : b.is_current && <Check className="w-4 h-4 text-amber-500" />}
            </button>
          ))}

          {error && <p role="alert" className="px-3 py-2 text-xs text-red-600">{error}</p>}

          {data && (
            <div className="border-t border-cream-300 mt-2 pt-2">
              {needsUpgrade ? (
                <div className="px-3 py-2">
                  <p className="text-xs text-slate-600 mb-2 flex items-start gap-1.5">
                    <Lock className="w-3.5 h-3.5 mt-0.5 flex-shrink-0 text-slate-400" />
                    {addBlocked.message}
                  </p>
                  <Link to="/billing" onClick={() => setOpen(false)} className="text-xs font-semibold text-amber-600 underline">
                    See plans
                  </Link>
                </div>
              ) : (
                <button
                  role="menuitem"
                  onClick={() => { setOpen(false); navigate('/onboarding?new=1') }}
                  className="w-full flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold text-navy-800 hover:bg-cream-200 transition-all"
                >
                  <Plus className="w-4 h-4 text-amber-500" />
                  Add a location
                </button>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
