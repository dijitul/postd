/**
 * PlatformIcon — postd.uk UI Component
 *
 * SVG icon component for all 6 supported platforms plus Google Business Profile:
 *   facebook | instagram | x | linkedin | tiktok | google | gbp
 *
 * Props:
 *   platform  — platform slug string
 *   size      — number (px) or 'xs'|'sm'|'md'|'lg'|'xl'|'2xl'
 *   branded   — use platform brand colour vs currentColor (default: true)
 *   container — 'none'|'circle'|'square'|'filled'|'soft'
 *   showLabel — display platform name alongside icon
 *   className — extra Tailwind classes
 *   label     — override aria-label
 */

// ─── Size resolver ────────────────────────────────────────────────────────────

const SIZE_MAP = { xs: 12, sm: 16, md: 20, lg: 24, xl: 32, '2xl': 40 }

function resolveSize(size) {
  if (typeof size === 'number') return size
  return SIZE_MAP[size] ?? SIZE_MAP.md
}

// ─── Platform brand colours ───────────────────────────────────────────────────

export const PLATFORM_COLOURS = {
  facebook:  '#1877F2',
  instagram: '#E1306C',
  x:         '#000000',
  linkedin:  '#0A66C2',
  tiktok:    '#010101',
  google:    '#4285F4',
  gbp:       '#34A853',
}

// ─── Platform background tints (for 'soft' container) ────────────────────────

export const PLATFORM_TINTS = {
  facebook:  '#E7F1FD',
  instagram: '#FCE8EF',
  x:         '#F0F0F0',
  linkedin:  '#E8F1FB',
  tiktok:    '#F0F0F0',
  google:    '#E8F0FE',
  gbp:       '#E6F4EA',
}

// ─── Accessible labels ────────────────────────────────────────────────────────

export const PLATFORM_NAMES = {
  facebook:  'Facebook',
  instagram: 'Instagram',
  x:         'X (formerly Twitter)',
  linkedin:  'LinkedIn',
  tiktok:    'TikTok',
  google:    'Google',
  gbp:       'Google Business Profile',
}

// ─── All platform slugs ───────────────────────────────────────────────────────

export const ALL_PLATFORMS = ['facebook', 'instagram', 'x', 'linkedin', 'tiktok', 'google']

// ─── SVG Icons ────────────────────────────────────────────────────────────────

function FacebookIcon({ size, colour }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill={colour}
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047v-2.66c0-3.025 1.791-4.697 4.532-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.265h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z" />
    </svg>
  )
}

function InstagramIcon({ size, colour }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill={colour}
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
    </svg>
  )
}

function XIcon({ size, colour }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill={colour}
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.262 5.636L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z" />
    </svg>
  )
}

function LinkedInIcon({ size, colour }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill={colour}
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
    </svg>
  )
}

function TikTokIcon({ size, colour }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill={colour}
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.76a4.85 4.85 0 01-1.01-.07z" />
    </svg>
  )
}

// Google is always rendered in its official multi-colour — a monochrome G is unrecognisable
function GoogleIcon({ size }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4" />
      <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
      <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
      <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
    </svg>
  )
}

// Google Business Profile: G mark + green location pin badge
function GBPIcon({ size }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 32 32"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      {/* Google G — scaled to occupy the left 2/3 */}
      <g transform="scale(0.82) translate(0, 1)">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4" />
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
      </g>
      {/* Map pin badge — bottom-right corner */}
      <circle cx="23.5" cy="23.5" r="8.5" fill="#34A853" />
      <path
        d="M23.5 17c-3.59 0-6.5 2.91-6.5 6.5 0 4.88 6.5 11.5 6.5 11.5S30 28.38 30 23.5C30 19.91 27.09 17 23.5 17zm0 8.75a2.25 2.25 0 110-4.5 2.25 2.25 0 010 4.5z"
        fill="white"
      />
    </svg>
  )
}

// ─── Icon registry ────────────────────────────────────────────────────────────

const ICON_MAP = {
  facebook:  FacebookIcon,
  instagram: InstagramIcon,
  x:         XIcon,
  linkedin:  LinkedInIcon,
  tiktok:    TikTokIcon,
  google:    GoogleIcon,
  gbp:       GBPIcon,
}

// ─── Container helpers ────────────────────────────────────────────────────────

const CONTAINER_BASE = {
  none:   '',
  circle: 'rounded-full bg-white shadow-sm border border-slate-200 inline-flex items-center justify-center shrink-0',
  square: 'rounded-lg bg-white shadow-sm border border-slate-200 inline-flex items-center justify-center shrink-0',
  filled: 'rounded-xl inline-flex items-center justify-center shrink-0',
  soft:   'rounded-xl inline-flex items-center justify-center shrink-0',
}

function containerDimensions(iconPx, hasContainer) {
  if (!hasContainer) return {}
  // Container is icon + padding on all sides
  const pad = Math.max(4, Math.round(iconPx * 0.25))
  const total = iconPx + pad * 2
  return { width: `${total}px`, height: `${total}px`, padding: `${pad}px` }
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function PlatformIcon({
  platform,
  size = 'md',
  branded = true,
  container = 'none',
  showLabel = false,
  className = '',
  label,
}) {
  const key = platform?.toLowerCase()
  const IconComponent = ICON_MAP[key]
  const px = resolveSize(size)
  const colour = branded ? (PLATFORM_COLOURS[key] ?? 'currentColor') : 'currentColor'
  const ariaLabel = label ?? PLATFORM_NAMES[key] ?? platform ?? 'Platform'
  const hasContainer = container !== 'none'

  if (!IconComponent) {
    return (
      <span
        className={`inline-flex items-center justify-center rounded-lg bg-slate-200 text-slate-600 font-bold text-xs select-none ${className}`}
        style={{ width: px, height: px }}
        aria-label={ariaLabel}
        role="img"
      >
        {platform?.slice(0, 2).toUpperCase() ?? '?'}
      </span>
    )
  }

  const dims = containerDimensions(px, hasContainer)

  // Filled uses platform colour as bg; soft uses tint
  const containerStyle = {
    ...dims,
    ...(container === 'filled' && { backgroundColor: PLATFORM_COLOURS[key] ?? '#94A3B8' }),
    ...(container === 'soft'   && { backgroundColor: PLATFORM_TINTS[key] ?? '#F1F5F9' }),
  }

  const iconColour = container === 'filled' ? '#FFFFFF' : colour

  const iconEl = <IconComponent size={px} colour={iconColour} />

  if (!hasContainer && !showLabel) {
    return (
      <span
        className={`inline-flex items-center justify-center ${className}`}
        aria-label={ariaLabel}
        role="img"
      >
        {iconEl}
      </span>
    )
  }

  return (
    <span className={`inline-flex items-center gap-2 ${className}`}>
      <span
        className={CONTAINER_BASE[container] ?? ''}
        style={containerStyle}
        aria-label={showLabel ? undefined : ariaLabel}
        role={showLabel ? undefined : 'img'}
        aria-hidden={showLabel ? true : undefined}
      >
        {iconEl}
      </span>
      {showLabel && (
        <span className="text-sm font-semibold text-navy-800 leading-none">
          {PLATFORM_NAMES[key] ?? platform}
        </span>
      )}
    </span>
  )
}

// ─── Compound: row of platform icons ─────────────────────────────────────────

export function PlatformIconRow({
  platforms = [],
  size = 'md',
  container = 'circle',
  branded = true,
  gap = 'gap-2',
  className = '',
}) {
  return (
    <div className={`flex items-center flex-wrap ${gap} ${className}`}>
      {platforms.map((p) => (
        <PlatformIcon
          key={p}
          platform={p}
          size={size}
          container={container}
          branded={branded}
        />
      ))}
    </div>
  )
}

// ─── Compound: grid of platform icons with labels ─────────────────────────────

export function PlatformIconGrid({
  platforms = [],
  size = 'lg',
  container = 'soft',
  branded = true,
  cols = 3,
  className = '',
}) {
  return (
    <div
      className={`grid gap-3 ${className}`}
      style={{ gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }}
    >
      {platforms.map((p) => (
        <div key={p} className="flex flex-col items-center gap-1.5">
          <PlatformIcon
            platform={p}
            size={size}
            container={container}
            branded={branded}
          />
          <span className="text-slate-500 font-medium leading-none" style={{ fontSize: '10px' }}>
            {PLATFORM_NAMES[p] ?? p}
          </span>
        </div>
      ))}
    </div>
  )
}

// ─── Re-export for backwards compatibility ────────────────────────────────────

export { PlatformIcon }

// Keep old named export shape working
export const PLATFORMS = Object.fromEntries(
  Object.entries(PLATFORM_NAMES).map(([key, label]) => [
    key,
    { label, color: PLATFORM_COLOURS[key], bg: PLATFORM_TINTS[key] },
  ])
)
