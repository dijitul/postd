import { Link } from 'react-router-dom'
import { clsx } from 'clsx'

/**
 * postd.uk logo component.
 * Renders the inline SVG brand mark + wordmark.
 * variant: 'default' | 'reversed' | 'mark-only'
 * size: 'sm' | 'md' | 'lg'
 */
export function Logo({ variant = 'default', size = 'md', className, asLink = true }) {
  const sizeMap = {
    sm: { height: 32, textSize: '1.25rem' },
    md: { height: 40, textSize: '1.5rem' },
    lg: { height: 52, textSize: '2rem' }
  }

  const { height, textSize } = sizeMap[size] ?? sizeMap.md
  const isReversed = variant === 'reversed'
  const nameColor = isReversed ? '#FFFFFF' : '#1E2D4A'

  const LogoInner = (
    <span className={clsx('inline-flex items-center gap-2', className)}>
      {/* Hexagon mark */}
      <svg
        width={height}
        height={height}
        viewBox="0 0 64 64"
        fill="none"
        aria-hidden="true"
        className="flex-shrink-0"
      >
        <rect width="64" height="64" rx="14" fill="#1E2D4A" />
        <polygon points="32,6 56,19.5 56,44.5 32,58 8,44.5 8,19.5" fill="#2E4470" />
        <polygon points="32,10 52,21.5 52,42.5 32,54 12,42.5 12,21.5" fill="#1E2D4A" />
        <rect x="13" y="19" width="38" height="24" rx="6" ry="6" fill="#F9F5EE" />
        <path d="M19 43 L15 53 L30 43 Z" fill="#F9F5EE" />
        <path d="M35 23 L28 34 L33.5 34 L30 41 L37 30 L31.5 30 Z" fill="#E07B30" />
        <circle cx="54" cy="54" r="5" fill="#F5C842" />
      </svg>

      {variant !== 'mark-only' && (
        <span
          className="font-display font-bold tracking-tight leading-none"
          style={{ fontSize: textSize, color: nameColor, fontFamily: 'Syne, system-ui, sans-serif' }}
        >
          postd
          <span style={{ color: '#E07B30' }}>.</span>
          <span style={{ color: nameColor }}>uk</span>
        </span>
      )}
    </span>
  )

  if (asLink) {
    return (
      <Link to="/" className="inline-flex items-center tap-highlight-none" aria-label="postd.uk — home">
        {LogoInner}
      </Link>
    )
  }

  return LogoInner
}

export default Logo
