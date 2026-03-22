/**
 * Badge — postd.uk UI Component
 *
 * Variant groups:
 * - Plan tiers:  starter | growth | pro
 * - Status:      active | pending | failed | paused | draft | scheduled
 * - Platforms:   facebook | instagram | x | linkedin | tiktok | google
 * - General:     default | amber | honey | navy | success | warning | error | info
 */

// ─── Variant definitions ──────────────────────────────────────────────────────

const VARIANTS = {
  // ── General ──
  default: 'bg-slate-100 text-slate-700 border border-slate-200',
  amber:   'bg-amber-50 text-amber-700 border border-amber-200',
  navy:    'bg-navy-800 text-white border border-navy-700',
  honey:   'bg-honey-400 text-navy-800 border border-honey-500 font-bold',
  cream:   'bg-cream-200 text-navy-800 border border-cream-400',

  // ── Status ──
  success:   'bg-green-50 text-green-700 border border-green-200',
  warning:   'bg-amber-50 text-amber-700 border border-amber-200',
  error:     'bg-red-50 text-red-600 border border-red-200',
  info:      'bg-blue-50 text-blue-700 border border-blue-200',
  pending:   'bg-purple-50 text-purple-700 border border-purple-200',

  // ── Post status ──
  active:    'bg-green-50 text-green-700 border border-green-200',
  failed:    'bg-red-50 text-red-600 border border-red-200',
  paused:    'bg-slate-100 text-slate-600 border border-slate-200',
  draft:     'bg-slate-50 text-slate-500 border border-slate-200',
  scheduled: 'bg-blue-50 text-blue-600 border border-blue-200',
  queued:    'bg-purple-50 text-purple-600 border border-purple-200',
  posted:    'bg-green-50 text-green-700 border border-green-200',

  // ── Plan tiers ──
  starter: 'bg-slate-100 text-slate-700 border border-slate-200',
  growth:  'bg-amber-50 text-amber-700 border border-amber-200',
  pro:     'bg-honey-400 text-navy-800 border border-honey-500 font-bold',

  // ── Platforms ──
  facebook:  'bg-blue-50 text-blue-700 border border-blue-200',
  instagram: 'bg-pink-50 text-pink-700 border border-pink-200',
  x:         'bg-slate-900 text-white border border-slate-700',
  linkedin:  'bg-blue-50 text-blue-800 border border-blue-200',
  tiktok:    'bg-slate-900 text-white border border-slate-700',
  google:    'bg-blue-50 text-blue-600 border border-blue-200',
}

// ─── Size definitions ─────────────────────────────────────────────────────────

const SIZES = {
  xs: 'text-2xs px-1.5 py-0.5 gap-1',
  sm: 'text-xs px-2 py-0.5 gap-1',
  md: 'text-xs px-2.5 py-1 gap-1.5',
  lg: 'text-sm px-3 py-1.5 gap-2',
}

// ─── Status dot for live badges ───────────────────────────────────────────────

function StatusDot({ variant }) {
  const dotColours = {
    active:    'bg-green-500',
    success:   'bg-green-500',
    failed:    'bg-red-500',
    error:     'bg-red-500',
    warning:   'bg-amber-500',
    pending:   'bg-purple-400',
    scheduled: 'bg-blue-500',
    queued:    'bg-purple-400',
    paused:    'bg-slate-400',
    draft:     'bg-slate-300',
    posted:    'bg-green-500',
  }

  const colour = dotColours[variant]
  if (!colour) return null

  return (
    <span
      className={`inline-block w-1.5 h-1.5 rounded-full shrink-0 ${colour}`}
      aria-hidden="true"
    />
  )
}

// ─── Platform dots / indicators ───────────────────────────────────────────────

const PLATFORM_DOTS = {
  facebook:  'bg-[#1877F2]',
  instagram: 'bg-[#E1306C]',
  x:         'bg-slate-900',
  linkedin:  'bg-[#0A66C2]',
  tiktok:    'bg-slate-950',
  google:    'bg-[#4285F4]',
}

// ─── Plan icons (simple emoji-equivalent SVG dots with tier colour) ──────────

function PlanIcon({ variant }) {
  const colours = {
    starter: '#94A3B8',
    growth:  '#E07B30',
    pro:     '#F5C842',
  }
  const colour = colours[variant]
  if (!colour) return null

  return (
    <svg width="8" height="8" viewBox="0 0 8 8" fill="none" aria-hidden="true">
      <circle cx="4" cy="4" r="4" fill={colour} />
    </svg>
  )
}

// ─── Main Badge component ─────────────────────────────────────────────────────

export default function Badge({
  // Content
  children,
  // Variant
  variant = 'default',
  // Size
  size = 'md',
  // Options
  dot = false,          // Show status dot on left
  icon = null,          // Custom icon element
  pill = true,          // Rounded pill vs rounded rectangle
  uppercase = false,    // All caps text (plan labels etc)
  // Extras
  className = '',
  ...rest
}) {
  const variantClass  = VARIANTS[variant] ?? VARIANTS.default
  const sizeClass     = SIZES[size] ?? SIZES.md
  const shapeClass    = pill ? 'rounded-full' : 'rounded-md'
  const caseClass     = uppercase ? 'uppercase tracking-wide' : ''
  const platformDot   = PLATFORM_DOTS[variant]

  return (
    <span
      className={[
        'inline-flex items-center',
        'font-display font-semibold',
        'leading-none whitespace-nowrap',
        variantClass,
        sizeClass,
        shapeClass,
        caseClass,
        className,
      ]
        .filter(Boolean)
        .join(' ')}
      {...rest}
    >
      {/* Status dot — for active/pending/failed status variants */}
      {dot && <StatusDot variant={variant} />}

      {/* Platform colour dot — for platform variants */}
      {!dot && !icon && platformDot && (
        <span
          className={`inline-block w-1.5 h-1.5 rounded-full shrink-0 ${platformDot}`}
          aria-hidden="true"
        />
      )}

      {/* Plan tier icon */}
      {!dot && !icon && !platformDot && ['starter', 'growth', 'pro'].includes(variant) && (
        <PlanIcon variant={variant} />
      )}

      {/* Custom icon */}
      {icon && (
        <span className="shrink-0 flex items-center" aria-hidden="true">
          {icon}
        </span>
      )}

      {children}
    </span>
  )
}

// ─── Convenience named exports ────────────────────────────────────────────────

export function PlanBadge({ plan, ...props }) {
  const labels = {
    starter: 'Starter',
    growth:  'Growth',
    pro:     'Pro',
  }
  return (
    <Badge variant={plan} dot={false} uppercase {...props}>
      {labels[plan] ?? plan}
    </Badge>
  )
}

export function StatusBadge({ status, ...props }) {
  const labels = {
    active:    'Active',
    pending:   'Pending',
    failed:    'Failed',
    paused:    'Paused',
    draft:     'Draft',
    scheduled: 'Scheduled',
    queued:    'Queued',
    posted:    'Posted',
  }
  return (
    <Badge variant={status} dot {...props}>
      {labels[status] ?? status}
    </Badge>
  )
}

export function PlatformBadge({ platform, label, ...props }) {
  const labels = {
    facebook:  'Facebook',
    instagram: 'Instagram',
    x:         'X',
    linkedin:  'LinkedIn',
    tiktok:    'TikTok',
    google:    'Google BP',
  }
  return (
    <Badge variant={platform} {...props}>
      {label ?? labels[platform] ?? platform}
    </Badge>
  )
}

export function TrialBadge({ daysLeft, ...props }) {
  const isExpiring = daysLeft <= 3
  return (
    <Badge variant={isExpiring ? 'error' : 'amber'} dot {...props}>
      {daysLeft === 0 ? 'Trial expired' : `${daysLeft}d trial`}
    </Badge>
  )
}
