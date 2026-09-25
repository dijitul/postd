import { useState } from 'react'
import { Link } from 'react-router-dom'
import { AlertCircle, CalendarDays } from 'lucide-react'
import { settingsApi } from '../../lib/api.js'
import PlatformIcon from '../../components/ui/PlatformIcon.jsx'

// Settings field for each platform's weekly cadence, and the icon id.
const ROWS = [
  { field: 'posts_per_week_gbp',      platform: 'google_business_profile', icon: 'google',   label: 'Google Business Profile' },
  { field: 'posts_per_week_facebook', platform: 'facebook',                icon: 'facebook', label: 'Facebook' },
  { field: 'posts_per_week_linkedin', platform: 'linkedin',                icon: 'linkedin', label: 'LinkedIn' },
  { field: 'posts_per_week_twitter',  platform: 'twitter',                 icon: 'x',        label: 'X (Twitter)' },
]

// The most any plan offers. Options above this plan's cap still show, marked
// as locked, so people can see what an upgrade would give them.
const MAX_OFFERED = 14
const MAX_GBP = 7

function optionsFor(platform) {
  const max = platform === 'google_business_profile' ? MAX_GBP : MAX_OFFERED
  return Array.from({ length: max + 1 }, (_, i) => i)
}

/**
 * Posts a week per platform, held to what the plan allows.
 *
 * Saves on change, like the other preferences on this page. A value above the
 * plan is not selectable, and the backend refuses it too with the same wording.
 */
export default function PostingFrequency({ values, caps, planName, onSaved }) {
  const [saving, setSaving] = useState(null)
  const [error, setError] = useState(null)
  const [saved, setSaved] = useState(null)

  if (!caps) return null

  // No plan: posting is paused, so there is no cadence to choose yet.
  if (Object.values(caps).every((cap) => cap === 0)) {
    return (
      <p className="text-sm text-slate-600">
        Posting is paused until you choose a plan.{' '}
        <Link to="/billing" className="font-semibold text-amber-600 underline">See plans</Link>
      </p>
    )
  }

  const handleChange = async (row, value) => {
    setError(null)
    setSaved(null)
    setSaving(row.field)
    try {
      await settingsApi.update({ [row.field]: value })
      onSaved({ [row.field]: value })
      setSaved(row.field)
      setTimeout(() => setSaved(null), 3000)
    } catch (e) {
      setError(e.response?.data?.message ?? 'Could not save that. Please try again.')
    } finally {
      setSaving(null)
    }
  }

  const anyLocked = ROWS.some((r) => (caps[r.platform] ?? 0) < (r.platform === 'google_business_profile' ? MAX_GBP : MAX_OFFERED))

  return (
    <div className="space-y-4">
      <p className="text-xs text-slate-500 leading-relaxed flex items-start gap-2">
        <CalendarDays className="w-4 h-4 text-amber-500 flex-shrink-0" />
        How many posts each platform gets in a week. Changes apply from the next week we write.
      </p>

      {ROWS.map((row) => {
        const cap = caps[row.platform] ?? 0
        // A saved value above the plan (kept from a bigger plan) shows as the
        // cap, which is what is actually being written.
        const current = Math.min(values?.[row.field] ?? 3, cap)

        return (
          <div key={row.field} className="flex items-center gap-3">
            <PlatformIcon platform={row.icon} size="sm" />
            <label htmlFor={row.field} className="flex-1 min-w-0 text-sm font-semibold text-navy-800 truncate">
              {row.label}
            </label>
            {cap === 0 ? (
              <Link to="/billing" className="text-xs font-semibold text-amber-600 underline whitespace-nowrap">
                Not on {planName}
              </Link>
            ) : (
              <select
                id={row.field}
                value={current}
                disabled={saving === row.field}
                onChange={(e) => handleChange(row, Number(e.target.value))}
                className="text-sm font-semibold text-navy-800 bg-white border border-cream-400 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:opacity-50"
              >
                {optionsFor(row.platform).map((n) => (
                  <option key={n} value={n} disabled={n > cap}>
                    {n === 0 ? 'Off' : `${n} a week`}{n > cap ? ' (upgrade)' : ''}
                  </option>
                ))}
              </select>
            )}
            <span className="w-10 text-xs font-semibold text-green-600" aria-live="polite">
              {saved === row.field ? 'Saved' : ''}
            </span>
          </div>
        )
      })}

      {anyLocked && (
        <p className="text-xs text-slate-500">
          {planName} includes up to {Math.max(...Object.values(caps))} posts a week on each platform.{' '}
          <Link to="/billing" className="font-semibold text-amber-600 underline">See what other plans include</Link>
        </p>
      )}

      {error && (
        <div role="alert" className="flex items-start gap-2.5 bg-red-50 border border-red-200 rounded-xl p-3.5">
          <AlertCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
          <p className="text-xs text-red-700">
            {error} <Link to="/billing" className="font-semibold underline">See plans</Link>
          </p>
        </div>
      )}
    </div>
  )
}
