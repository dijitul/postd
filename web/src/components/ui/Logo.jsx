import { Link } from 'react-router-dom'
import { clsx } from 'clsx'

/**
 * postd logo (Relay, docs/brand-2027).
 * The mark is inline SVG; the wordmark is live text in Sora 800 so it uses
 * the loaded font. Always lowercase "postd".
 *
 * variant: 'default' (navy text, for light backgrounds) | 'reversed' (white
 *          text, for dark backgrounds) | 'mark-only'
 * product: 'postd' (cobalt mark) | 'office' (navy mark, "postd office")
 * size:    'sm' | 'md' | 'lg'
 */
export function LogoMark({ size = 32, product = 'postd', className }) {
  const circle = product === 'office' ? '#0E1A3F' : '#2340F5'
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      aria-hidden="true"
      focusable="false"
      className={clsx('flex-shrink-0', className)}
    >
      <circle cx="32" cy="32" r="32" fill={circle} />
      <path
        d="M22 46V20h12a10 10 0 0 1 0 20h-6"
        fill="none"
        stroke="#FFFFFF"
        strokeWidth="6"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <circle cx="44" cy="44" r="6" fill="#FFD23F" />
    </svg>
  )
}

export function Logo({ variant = 'default', size = 'md', product = 'postd', className, asLink = true }) {
  const sizeMap = {
    sm: { mark: 28, text: '1.375rem' },
    md: { mark: 34, text: '1.625rem' },
    lg: { mark: 44, text: '2.125rem' },
  }

  const { mark, text } = sizeMap[size] ?? sizeMap.md
  const isReversed = variant === 'reversed'

  const LogoInner = (
    <span className={clsx('inline-flex items-center', className)} style={{ gap: mark * 0.3 }}>
      <LogoMark size={mark} product={product} />

      {variant !== 'mark-only' && (
        <span
          className={clsx('font-display leading-none whitespace-nowrap', isReversed ? 'text-white' : 'text-navy-900')}
          style={{ fontSize: text, fontWeight: 800, letterSpacing: '-0.035em' }}
        >
          postd
          {product === 'office' && (
            <span
              className="text-cobalt-400"
              style={{ fontWeight: 500, marginLeft: '0.25em' }}
            >
              office
            </span>
          )}
        </span>
      )}
    </span>
  )

  if (asLink) {
    return (
      <Link
        to="/"
        className="inline-flex items-center min-h-[44px] tap-highlight-none rounded-full"
        aria-label="postd, home"
      >
        {LogoInner}
      </Link>
    )
  }

  return LogoInner
}

export default Logo
