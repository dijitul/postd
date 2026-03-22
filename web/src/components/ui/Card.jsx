/**
 * Card — postd.uk UI Component
 *
 * Variants: default, elevated, interactive
 * Optionally accepts header, footer, padding control
 */

import { forwardRef } from 'react'

// ─── Variant definitions ──────────────────────────────────────────────────────

const VARIANTS = {
  /**
   * Default — clean white card with subtle border and shadow.
   * Use for: content panels, settings sections, form containers.
   */
  default: [
    'bg-white',
    'border border-slate-200',
    'shadow-card',
    'rounded-xl',
  ].join(' '),

  /**
   * Elevated — more pronounced shadow, sits above the page.
   * Use for: modals, popovers, notification panels, pricing cards.
   */
  elevated: [
    'bg-white',
    'border border-slate-200',
    'shadow-xl',
    'rounded-2xl',
  ].join(' '),

  /**
   * Interactive — has hover/active states, signals clickability.
   * Use for: post preview cards, platform selector tiles, dashboard widgets.
   */
  interactive: [
    'bg-white',
    'border border-slate-200',
    'shadow-card',
    'rounded-xl',
    'cursor-pointer',
    'transition-all duration-200 ease-in-out',
    'hover:shadow-card-hover hover:border-slate-300 hover:-translate-y-0.5',
    'active:shadow-card active:translate-y-0 active:scale-[0.99]',
  ].join(' '),

  /**
   * Cream — cream background, slightly warmer feel.
   * Use for: onboarding steps, sidebar widgets, info panels.
   */
  cream: [
    'bg-cream-200',
    'border border-cream-400',
    'shadow-xs',
    'rounded-xl',
  ].join(' '),

  /**
   * Navy — dark card, stands out on cream/white backgrounds.
   * Use for: stats highlights, feature callouts.
   */
  navy: [
    'bg-navy-800',
    'border border-navy-700',
    'shadow-navy-lg',
    'rounded-xl',
    'text-white',
  ].join(' '),

  /**
   * Amber — amber-tinted, used for CTA callouts or upgrade nudges.
   * Use for: trial expiry banners, feature upsell cards.
   */
  amber: [
    'bg-gradient-amber-soft',
    'border border-amber-200',
    'shadow-amber',
    'rounded-xl',
  ].join(' '),

  /**
   * Honey — premium plan feature card.
   * Use for: "Pro" plan feature highlights, upgrade prompts.
   */
  honey: [
    'bg-white',
    'border-2 border-honey-400',
    'shadow-honey',
    'rounded-xl',
    'relative overflow-hidden',
  ].join(' '),
}

// ─── Padding presets ──────────────────────────────────────────────────────────

const PADDING = {
  none: '',
  sm:   'p-3',
  md:   'p-4',
  lg:   'p-6',
  xl:   'p-8',
}

// ─── Sub-components ───────────────────────────────────────────────────────────

function CardHeader({ title, subtitle, action, className = '' }) {
  return (
    <div className={`flex items-start justify-between gap-4 mb-4 ${className}`}>
      <div className="min-w-0">
        {title && (
          <h3 className="font-display font-bold text-navy-800 text-base leading-snug truncate">
            {title}
          </h3>
        )}
        {subtitle && (
          <p className="text-slate-500 text-sm mt-0.5 leading-snug">
            {subtitle}
          </p>
        )}
      </div>
      {action && (
        <div className="shrink-0">{action}</div>
      )}
    </div>
  )
}

function CardFooter({ children, className = '', border = true }) {
  return (
    <div
      className={[
        'mt-4 pt-4',
        border ? 'border-t border-slate-100' : '',
        className,
      ]
        .filter(Boolean)
        .join(' ')}
    >
      {children}
    </div>
  )
}

function CardSection({ children, className = '', border = false }) {
  return (
    <div
      className={[
        border ? 'border-t border-slate-100 pt-4 mt-4' : '',
        className,
      ]
        .filter(Boolean)
        .join(' ')}
    >
      {children}
    </div>
  )
}

// ─── Honey corner ribbon ─────────────────────────────────────────────────────

function HoneyRibbon({ label = 'Most popular' }) {
  return (
    <div
      className="absolute top-0 right-0 overflow-hidden w-24 h-24 pointer-events-none"
      aria-hidden="true"
    >
      <div
        className={[
          'absolute top-4 right-[-24px]',
          'bg-honey-400 text-navy-800',
          'text-2xs font-display font-bold uppercase tracking-wider',
          'px-8 py-1',
          'rotate-45',
          'shadow-sm',
        ].join(' ')}
      >
        {label}
      </div>
    </div>
  )
}

// ─── Main Card component ──────────────────────────────────────────────────────

const Card = forwardRef(function Card(
  {
    // Content
    children,
    // Variant
    variant = 'default',
    // Padding
    padding = 'lg',
    noPadding = false,
    // Sub-component props (convenience)
    title,
    subtitle,
    headerAction,
    footer,
    // Honey ribbon
    ribbon = false,
    ribbonLabel,
    // Click handler (also sets interactive classes if not already)
    onClick,
    // Extras
    className = '',
    as: Tag = 'div',
    ...rest
  },
  ref
) {
  // If onClick is provided but variant isn't interactive, add pointer
  const clickClasses = onClick && variant !== 'interactive'
    ? 'cursor-pointer transition-all duration-200 hover:shadow-card-hover hover:-translate-y-0.5'
    : ''

  const cardClasses = [
    VARIANTS[variant] ?? VARIANTS.default,
    noPadding ? '' : PADDING[padding] ?? PADDING.lg,
    clickClasses,
    className,
  ]
    .filter(Boolean)
    .join(' ')

  const hasHeader = title || subtitle || headerAction

  return (
    <Tag
      ref={ref}
      onClick={onClick}
      className={cardClasses}
      role={onClick ? 'button' : undefined}
      tabIndex={onClick ? 0 : undefined}
      onKeyDown={
        onClick
          ? (e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault()
                onClick(e)
              }
            }
          : undefined
      }
      {...rest}
    >
      {/* Honey ribbon for premium cards */}
      {ribbon && <HoneyRibbon label={ribbonLabel} />}

      {/* Auto header */}
      {hasHeader && (
        <CardHeader title={title} subtitle={subtitle} action={headerAction} />
      )}

      {children}

      {/* Auto footer */}
      {footer && <CardFooter>{footer}</CardFooter>}
    </Tag>
  )
})

Card.displayName = 'Card'

// Attach sub-components
Card.Header  = CardHeader
Card.Footer  = CardFooter
Card.Section = CardSection

export default Card
export { CardHeader, CardFooter, CardSection }
