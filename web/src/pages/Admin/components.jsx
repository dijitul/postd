/**
 * Shared building blocks for the admin dashboard.
 *
 * The admin shell is dark, so it does not reuse the light-surface components
 * from components/ui. These are deliberately small and local to /admin.
 */

// ─── Formatting helpers ───────────────────────────────────────────────────────

export function gbp(value, { decimals = 0 } = {}) {
  const n = Number(value ?? 0)
  return `£${n.toLocaleString('en-GB', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}`
}

export function usd(value, { decimals = 2 } = {}) {
  const n = Number(value ?? 0)
  return `$${n.toLocaleString('en-GB', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}`
}

export function num(value) {
  return Number(value ?? 0).toLocaleString('en-GB')
}

/**
 * Token counts get large fast, so shorten them for headline figures.
 * 1_240_000 becomes "1.24M".
 */
export function compactTokens(value) {
  const n = Number(value ?? 0)
  if (n < 1000) return String(n)
  if (n < 1_000_000) return `${(n / 1000).toFixed(n < 10_000 ? 1 : 0)}K`
  if (n < 1_000_000_000) return `${(n / 1_000_000).toFixed(2)}M`
  return `${(n / 1_000_000_000).toFixed(2)}B`
}

export function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

export function formatDateTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-GB', {
    day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
  })
}

export function timeAgo(iso) {
  if (!iso) return 'never'
  const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000)
  const future = seconds < 0
  const s = Math.abs(seconds)

  const units = [
    [60, 'sec'],
    [3600, 'min', 60],
    [86400, 'hr', 3600],
    [2592000, 'day', 86400],
    [31536000, 'mo', 2592000],
  ]

  if (s < 45) return future ? 'in a moment' : 'just now'

  for (const [limit, label, divisor] of units) {
    if (s < limit) {
      const value = divisor ? Math.round(s / divisor) : s
      const unit = value === 1 ? label : `${label}s`
      return future ? `in ${value} ${unit}` : `${value} ${unit} ago`
    }
  }

  const years = Math.round(s / 31536000)
  const unit = years === 1 ? 'yr' : 'yrs'
  return future ? `in ${years} ${unit}` : `${years} ${unit} ago`
}

export const PLATFORM_LABELS = {
  facebook: 'Facebook',
  instagram: 'Instagram',
  twitter: 'X',
  linkedin: 'LinkedIn',
  tiktok: 'TikTok',
  google_business_profile: 'Google BP',
}

export function platformLabel(platform) {
  return PLATFORM_LABELS[platform] ?? platform
}

// ─── Surfaces ─────────────────────────────────────────────────────────────────

export function Panel({ title, subtitle, action, children, className = '', padded = true }) {
  return (
    <section className={`bg-white/[0.04] rounded-2xl border border-white/10 ${className}`}>
      {(title || action) && (
        <header className="flex items-start justify-between gap-4 px-5 py-4 border-b border-white/10">
          <div>
            <h2 className="font-display font-bold text-sm text-white/90">{title}</h2>
            {subtitle && <p className="text-xs text-white/40 mt-0.5">{subtitle}</p>}
          </div>
          {action}
        </header>
      )}
      <div className={padded ? 'p-5' : ''}>{children}</div>
    </section>
  )
}

export function StatCard({ label, value, sub, icon: Icon, accent = false, tone, loading = false }) {
  const toneClasses = {
    danger: 'text-red-400',
    warning: 'text-amber-400',
    success: 'text-green-400',
  }

  return (
    <div
      className={`rounded-2xl p-4 border ${accent
        ? 'bg-gradient-to-br from-amber-500 to-amber-600 border-amber-400/50'
        : 'bg-white/[0.04] border-white/10'}`}
    >
      <div className="flex items-center gap-2 mb-3">
        {Icon && (
          <div className={`w-7 h-7 rounded-lg flex items-center justify-center ${accent ? 'bg-white/20' : 'bg-white/[0.06]'}`}>
            <Icon className={`w-3.5 h-3.5 ${accent ? 'text-white' : 'text-amber-400'}`} />
          </div>
        )}
        <span className={`text-xs font-semibold ${accent ? 'text-white/80' : 'text-white/50'}`}>{label}</span>
      </div>

      {loading ? (
        <div className="space-y-2">
          <div className={`h-7 w-20 rounded ${accent ? 'bg-white/20' : 'bg-white/10'} animate-pulse`} />
          <div className={`h-3 w-28 rounded ${accent ? 'bg-white/15' : 'bg-white/[0.06]'} animate-pulse`} />
        </div>
      ) : (
        <>
          <div className={`font-display font-black text-2xl leading-none ${accent ? 'text-white' : (toneClasses[tone] ?? 'text-white')}`}>
            {value}
          </div>
          {sub && (
            <div className={`text-xs mt-1.5 ${accent ? 'text-white/70' : 'text-white/40'}`}>{sub}</div>
          )}
        </>
      )}
    </div>
  )
}

export function EmptyState({ icon: Icon, title, hint }) {
  return (
    <div className="py-12 text-center">
      {Icon && <Icon className="w-8 h-8 text-white/15 mx-auto mb-3" />}
      <p className="text-sm text-white/50">{title}</p>
      {hint && <p className="text-xs text-white/30 mt-1">{hint}</p>}
    </div>
  )
}

export function SkeletonRows({ rows = 5, height = 'h-12' }) {
  return (
    <div className="space-y-2">
      {[...Array(rows)].map((_, i) => (
        <div key={i} className={`${height} rounded-xl bg-white/[0.04] border border-white/5 animate-pulse`} />
      ))}
    </div>
  )
}

// ─── Pills ────────────────────────────────────────────────────────────────────

const PILL_TONES = {
  green: 'bg-green-500/15 text-green-400 border-green-500/25',
  amber: 'bg-amber-500/15 text-amber-400 border-amber-500/25',
  red: 'bg-red-500/15 text-red-400 border-red-500/25',
  blue: 'bg-blue-500/15 text-blue-300 border-blue-500/25',
  purple: 'bg-purple-500/15 text-purple-300 border-purple-500/25',
  slate: 'bg-white/[0.06] text-white/50 border-white/10',
}

export function Pill({ tone = 'slate', children, className = '' }) {
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-2xs font-bold uppercase tracking-wide whitespace-nowrap ${PILL_TONES[tone] ?? PILL_TONES.slate} ${className}`}>
      {children}
    </span>
  )
}

const STATUS_META = {
  subscribed: { tone: 'green', label: 'Paying' },
  comped: { tone: 'purple', label: 'Free' },
  trialing: { tone: 'amber', label: 'Trial' },
  expired: { tone: 'red', label: 'Expired' },
  none: { tone: 'slate', label: 'No plan' },
}

export function StatusPill({ status }) {
  const meta = STATUS_META[status] ?? STATUS_META.none
  return <Pill tone={meta.tone}>{meta.label}</Pill>
}

const POST_STATUS_TONES = {
  posted: 'green',
  failed: 'red',
  pending: 'amber',
  approved: 'blue',
  scheduled: 'blue',
  dispatching: 'purple',
  rejected: 'slate',
}

export function PostStatusPill({ status }) {
  return <Pill tone={POST_STATUS_TONES[status] ?? 'slate'}>{status}</Pill>
}

// ─── Bar chart ────────────────────────────────────────────────────────────────

/**
 * Daily bar chart for the overview series. Pure SVG so there is no charting
 * dependency to ship for a single internal screen.
 */
export function BarChart({ data, metric, colour = '#E07B30', height = 140 }) {
  if (!data?.length) {
    return <div className="h-[140px] flex items-center justify-center text-xs text-white/30">No data yet</div>
  }

  const values = data.map((d) => d[metric] ?? 0)
  const max = Math.max(...values, 1)
  const gap = 2
  const width = 100 // percentage-based viewBox
  const barWidth = Math.max((width - gap * (data.length - 1)) / data.length, 0.5)

  return (
    <div>
      <svg
        viewBox={`0 0 ${width} ${height}`}
        preserveAspectRatio="none"
        className="w-full"
        style={{ height }}
        role="img"
        aria-label={`Daily ${metric}`}
      >
        {[0.25, 0.5, 0.75, 1].map((f) => (
          <line
            key={f}
            x1="0" x2={width}
            y1={height - height * f} y2={height - height * f}
            stroke="rgba(255,255,255,0.06)"
            strokeWidth="1"
            vectorEffect="non-scaling-stroke"
          />
        ))}

        {data.map((d, i) => {
          const value = d[metric] ?? 0
          const barHeight = value === 0 ? 1 : Math.max((value / max) * (height - 6), 2)
          return (
            <rect
              key={d.date}
              x={i * (barWidth + gap)}
              y={height - barHeight}
              width={barWidth}
              height={barHeight}
              rx="0.6"
              fill={value === 0 ? 'rgba(255,255,255,0.08)' : colour}
            >
              <title>{`${d.date}: ${value}`}</title>
            </rect>
          )
        })}
      </svg>

      <div className="flex justify-between mt-2 text-2xs text-white/30">
        <span>{formatDate(data[0]?.date)}</span>
        <span className="text-white/40">peak {max}</span>
        <span>{formatDate(data[data.length - 1]?.date)}</span>
      </div>
    </div>
  )
}

// ─── Horizontal share bar ─────────────────────────────────────────────────────

export function ShareBar({ value, total, colour = 'bg-amber-500' }) {
  const pct = total > 0 ? Math.round((value / total) * 100) : 0
  return (
    <div className="h-1.5 rounded-full bg-white/[0.06] overflow-hidden">
      <div className={`h-full rounded-full ${colour}`} style={{ width: `${pct}%` }} />
    </div>
  )
}
