/**
 * Button — postd.uk UI Component
 *
 * Variants: primary (amber), secondary (navy), ghost, danger
 * Sizes: sm, md, lg
 * States: default, hover, active, disabled, loading
 */

import { forwardRef } from 'react'

// ─── Spinner ────────────────────────────────────────────────────────────────

function Spinner({ size = 'md', className = '' }) {
  const sizeClasses = {
    sm: 'w-3.5 h-3.5',
    md: 'w-4 h-4',
    lg: 'w-5 h-5',
  }

  return (
    <svg
      className={`animate-spin shrink-0 ${sizeClasses[size]} ${className}`}
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <circle
        className="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        strokeWidth="3"
      />
      <path
        className="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      />
    </svg>
  )
}

// ─── Variant classes ─────────────────────────────────────────────────────────

const VARIANTS = {
  /**
   * Primary — Deep Amber. Use for the single most important action per screen.
   * Appears in: CTAs, form submits, "Connect platform", "Upgrade".
   */
  primary: [
    // Base
    'bg-amber-500 text-white border border-amber-500',
    // Hover — slightly darker
    'hover:bg-amber-700 hover:border-amber-700',
    // Active — pressed state
    'active:bg-amber-800 active:border-amber-800 active:scale-[0.98]',
    // Focus
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2',
    // Shadow
    'shadow-sm hover:shadow-amber-lg',
  ].join(' '),

  /**
   * Secondary — Rich Navy. Use for secondary actions alongside a primary.
   * Appears in: "Cancel", "Back", "View details".
   */
  secondary: [
    'bg-navy-800 text-white border border-navy-800',
    'hover:bg-navy-700 hover:border-navy-700',
    'active:bg-navy-900 active:border-navy-900 active:scale-[0.98]',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-800 focus-visible:ring-offset-2',
    'shadow-sm hover:shadow-navy-lg',
  ].join(' '),

  /**
   * Ghost — transparent with border. Use for tertiary actions, toolbar buttons.
   * Appears in: "Edit", "Copy", "Skip for now".
   */
  ghost: [
    'bg-transparent text-navy-800 border border-slate-200',
    'hover:bg-slate-50 hover:border-slate-300 hover:text-navy-700',
    'active:bg-slate-100 active:scale-[0.98]',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2',
  ].join(' '),

  /**
   * Ghost amber — transparent amber. Use for inline actions with amber context.
   */
  'ghost-amber': [
    'bg-transparent text-amber-500 border border-amber-200',
    'hover:bg-amber-50 hover:border-amber-300',
    'active:bg-amber-100 active:scale-[0.98]',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2',
  ].join(' '),

  /**
   * Danger — Red. Use only for destructive, irreversible actions.
   * Appears in: "Delete account", "Remove connection", "Cancel subscription".
   */
  danger: [
    'bg-red-600 text-white border border-red-600',
    'hover:bg-red-700 hover:border-red-700',
    'active:bg-red-800 active:scale-[0.98]',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2',
    'shadow-sm',
  ].join(' '),

  /**
   * Honey — premium/upgrade contexts. Use for plan upgrade prompts.
   */
  honey: [
    'bg-honey-400 text-navy-800 border border-honey-400',
    'hover:bg-honey-500 hover:border-honey-500',
    'active:bg-honey-600 active:scale-[0.98]',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-honey-400 focus-visible:ring-offset-2',
    'shadow-sm font-bold',
  ].join(' '),
}

// ─── Size classes ─────────────────────────────────────────────────────────────

const SIZES = {
  sm: 'h-8 px-3 text-xs gap-1.5 rounded-lg',
  md: 'h-10 px-4 text-sm gap-2 rounded-xl',
  lg: 'h-12 px-6 text-base gap-2.5 rounded-xl',
}

// ─── Component ───────────────────────────────────────────────────────────────

const Button = forwardRef(function Button(
  {
    // Content
    children,
    // Behaviour
    type = 'button',
    onClick,
    href,
    // Variants
    variant = 'primary',
    size = 'md',
    // States
    loading = false,
    disabled = false,
    // Layout
    fullWidth = false,
    // Icon slots
    iconLeft = null,
    iconRight = null,
    // Accessibility
    'aria-label': ariaLabel,
    // Extra
    className = '',
    ...rest
  },
  ref
) {
  const isDisabled = disabled || loading

  const baseClasses = [
    // Layout
    'inline-flex items-center justify-center',
    'font-display font-semibold',
    'select-none',
    // Transition
    'transition-all duration-150 ease-in-out',
    // Width
    fullWidth ? 'w-full' : '',
    // Variant
    VARIANTS[variant] ?? VARIANTS.primary,
    // Size
    SIZES[size] ?? SIZES.md,
    // Disabled
    isDisabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'cursor-pointer',
    // Extra
    className,
  ]
    .filter(Boolean)
    .join(' ')

  const content = (
    <>
      {loading ? (
        <Spinner size={size} />
      ) : iconLeft ? (
        <span className="shrink-0 flex items-center">{iconLeft}</span>
      ) : null}

      {children && (
        <span className={loading ? 'opacity-0 absolute' : ''}>
          {children}
        </span>
      )}

      {!loading && iconRight && (
        <span className="shrink-0 flex items-center">{iconRight}</span>
      )}
    </>
  )

  // Render as anchor if href is provided
  if (href && !isDisabled) {
    return (
      <a
        ref={ref}
        href={href}
        className={baseClasses}
        aria-label={ariaLabel}
        {...rest}
      >
        {content}
      </a>
    )
  }

  return (
    <button
      ref={ref}
      type={type}
      onClick={onClick}
      disabled={isDisabled}
      aria-disabled={isDisabled}
      aria-label={ariaLabel}
      aria-busy={loading}
      className={`relative ${baseClasses}`}
      {...rest}
    >
      {content}
    </button>
  )
})

Button.displayName = 'Button'

export default Button

// ─── Named exports for convenience ───────────────────────────────────────────

export function PrimaryButton(props) {
  return <Button variant="primary" {...props} />
}

export function SecondaryButton(props) {
  return <Button variant="secondary" {...props} />
}

export function GhostButton(props) {
  return <Button variant="ghost" {...props} />
}

export function DangerButton(props) {
  return <Button variant="danger" {...props} />
}

export function HoneyButton(props) {
  return <Button variant="honey" {...props} />
}
