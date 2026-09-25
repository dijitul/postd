import { ANNUAL_MONTHS_FREE } from '../../lib/plans.js'

// Monthly / annual switch, used by the landing page pricing and the Billing page.
export default function IntervalToggle({ interval, onChange, dark = false }) {
  const base = 'px-4 py-2 rounded-full text-sm font-semibold transition-all duration-200'
  const on = dark ? 'bg-white text-navy-800 shadow' : 'bg-navy-800 text-white shadow'
  const off = dark ? 'text-white/70 hover:text-white' : 'text-slate-500 hover:text-navy-800'

  return (
    <div
      role="group"
      aria-label="Billing period"
      className={`inline-flex items-center gap-1 p-1 rounded-full ${dark ? 'bg-white/10' : 'bg-cream-200 border border-cream-300'}`}
    >
      <button type="button" aria-pressed={interval === 'monthly'} onClick={() => onChange('monthly')} className={`${base} ${interval === 'monthly' ? on : off}`}>
        Monthly
      </button>
      <button type="button" aria-pressed={interval === 'annual'} onClick={() => onChange('annual')} className={`${base} ${interval === 'annual' ? on : off}`}>
        Annual
        <span className={`ml-1.5 text-xs font-bold ${interval === 'annual' ? 'text-amber-400' : 'text-amber-600'}`}>
          {ANNUAL_MONTHS_FREE} months free
        </span>
      </button>
    </div>
  )
}
