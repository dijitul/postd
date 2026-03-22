/**
 * Input — postd.uk UI Component
 *
 * Features:
 * - Label with optional required marker
 * - Error state with message
 * - Helper text (below field)
 * - Left and right icon slots
 * - Textarea variant
 * - Select variant
 * - Character count
 */

import { forwardRef, useState, useId } from 'react'

// ─── Input sizes ──────────────────────────────────────────────────────────────

const SIZES = {
  sm: {
    input:     'h-8 px-3 text-xs rounded-lg',
    withIconL: 'pl-8',
    withIconR: 'pr-8',
    iconL:     'left-2.5 w-3.5 h-3.5',
    iconR:     'right-2.5 w-3.5 h-3.5',
  },
  md: {
    input:     'h-10 px-4 text-sm rounded-xl',
    withIconL: 'pl-10',
    withIconR: 'pr-10',
    iconL:     'left-3 w-4 h-4',
    iconR:     'right-3 w-4 h-4',
  },
  lg: {
    input:     'h-12 px-4 text-base rounded-xl',
    withIconL: 'pl-12',
    withIconR: 'pr-12',
    iconL:     'left-3.5 w-5 h-5',
    iconR:     'right-3.5 w-5 h-5',
  },
}

// ─── Base input classes ───────────────────────────────────────────────────────

function getInputClasses({ size, error, disabled, hasIconLeft, hasIconRight }) {
  const s = SIZES[size] ?? SIZES.md

  return [
    // Layout & sizing
    'w-full font-body',
    s.input,
    hasIconLeft ? s.withIconL : '',
    hasIconRight ? s.withIconR : '',
    // Colours & border
    'bg-white text-navy-800',
    'border-[1.5px]',
    error
      ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-200'
      : 'border-slate-200 hover:border-slate-300 focus:border-amber-500 focus:ring-2 focus:ring-amber-100',
    // Placeholder
    'placeholder:text-slate-400',
    // Transition
    'transition-all duration-150 ease-in-out',
    // Focus
    'outline-none',
    // Disabled
    disabled ? 'opacity-50 cursor-not-allowed bg-slate-50' : '',
  ]
    .filter(Boolean)
    .join(' ')
}

// ─── Label ───────────────────────────────────────────────────────────────────

function InputLabel({ htmlFor, label, required, className = '' }) {
  if (!label) return null
  return (
    <label
      htmlFor={htmlFor}
      className={`block font-display font-semibold text-sm text-navy-800 mb-1.5 ${className}`}
    >
      {label}
      {required && (
        <span className="text-amber-500 ml-0.5" aria-hidden="true">*</span>
      )}
    </label>
  )
}

// ─── Helper / Error text ─────────────────────────────────────────────────────

function InputHint({ error, helper, id }) {
  if (!error && !helper) return null
  return (
    <p
      id={id}
      className={[
        'mt-1.5 text-xs leading-snug',
        error ? 'text-red-500' : 'text-slate-500',
      ].join(' ')}
      role={error ? 'alert' : undefined}
    >
      {error || helper}
    </p>
  )
}

// ─── Icon wrapper ─────────────────────────────────────────────────────────────

function InputIcon({ icon, position, size, error }) {
  if (!icon) return null
  const s = SIZES[size] ?? SIZES.md
  const posClass = position === 'left' ? s.iconL : s.iconR

  return (
    <span
      className={[
        'absolute top-1/2 -translate-y-1/2 pointer-events-none',
        'flex items-center justify-center',
        posClass,
        error ? 'text-red-400' : 'text-slate-400',
      ].join(' ')}
      aria-hidden="true"
    >
      {icon}
    </span>
  )
}

// ─── Main Input component ─────────────────────────────────────────────────────

const Input = forwardRef(function Input(
  {
    // Label
    label,
    required = false,
    // Field
    type = 'text',
    placeholder,
    value,
    defaultValue,
    onChange,
    onBlur,
    onFocus,
    // State
    error,
    helper,
    disabled = false,
    readOnly = false,
    // Size
    size = 'md',
    // Icons
    iconLeft = null,
    iconRight = null,
    // Character count
    maxLength,
    showCount = false,
    // IDs
    id: idProp,
    name,
    // Autocomplete
    autoComplete,
    autoFocus = false,
    // Extra
    className = '',
    inputClassName = '',
    ...rest
  },
  ref
) {
  const generatedId = useId()
  const id = idProp || generatedId
  const hintId = `${id}-hint`
  const countId = `${id}-count`

  const inputClasses = [
    getInputClasses({
      size,
      error,
      disabled: disabled || readOnly,
      hasIconLeft: !!iconLeft,
      hasIconRight: !!iconRight || (showCount && maxLength),
    }),
    inputClassName,
  ]
    .filter(Boolean)
    .join(' ')

  const ariaDesc = [
    error || helper ? hintId : null,
    showCount && maxLength ? countId : null,
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <div className={`w-full ${className}`}>
      <InputLabel htmlFor={id} label={label} required={required} />

      <div className="relative">
        <InputIcon icon={iconLeft} position="left" size={size} error={!!error} />

        <input
          ref={ref}
          id={id}
          name={name}
          type={type}
          placeholder={placeholder}
          value={value}
          defaultValue={defaultValue}
          onChange={onChange}
          onBlur={onBlur}
          onFocus={onFocus}
          disabled={disabled}
          readOnly={readOnly}
          required={required}
          maxLength={maxLength}
          autoComplete={autoComplete}
          autoFocus={autoFocus}
          aria-invalid={!!error}
          aria-describedby={ariaDesc || undefined}
          aria-required={required}
          className={inputClasses}
          {...rest}
        />

        {/* Right icon or character count */}
        {showCount && maxLength ? (
          <span
            id={countId}
            className={[
              'absolute top-1/2 -translate-y-1/2 pointer-events-none',
              'text-2xs font-mono tabular-nums',
              SIZES[size]?.iconR ?? SIZES.md.iconR,
              // Shift right icon further if showing count
              'right-3',
              (value?.length ?? 0) >= maxLength * 0.9
                ? 'text-red-400'
                : 'text-slate-400',
            ].join(' ')}
            aria-live="polite"
          >
            {value?.length ?? 0}/{maxLength}
          </span>
        ) : (
          <InputIcon icon={iconRight} position="right" size={size} error={!!error} />
        )}
      </div>

      <InputHint error={error} helper={helper} id={hintId} />
    </div>
  )
})

Input.displayName = 'Input'

// ─── Textarea variant ─────────────────────────────────────────────────────────

export const Textarea = forwardRef(function Textarea(
  {
    label,
    required = false,
    placeholder,
    value,
    defaultValue,
    onChange,
    onBlur,
    error,
    helper,
    disabled = false,
    readOnly = false,
    rows = 4,
    maxLength,
    showCount = false,
    id: idProp,
    name,
    className = '',
    inputClassName = '',
    ...rest
  },
  ref
) {
  const generatedId = useId()
  const id = idProp || generatedId
  const hintId = `${id}-hint`

  const textareaClasses = [
    'w-full font-body text-sm text-navy-800',
    'bg-white px-4 py-3 rounded-xl',
    'border-[1.5px]',
    error
      ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-200'
      : 'border-slate-200 hover:border-slate-300 focus:border-amber-500 focus:ring-2 focus:ring-amber-100',
    'placeholder:text-slate-400',
    'transition-all duration-150 ease-in-out',
    'outline-none resize-y',
    disabled || readOnly ? 'opacity-50 cursor-not-allowed bg-slate-50' : '',
    inputClassName,
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <div className={`w-full ${className}`}>
      <InputLabel htmlFor={id} label={label} required={required} />

      <div className="relative">
        <textarea
          ref={ref}
          id={id}
          name={name}
          placeholder={placeholder}
          value={value}
          defaultValue={defaultValue}
          onChange={onChange}
          onBlur={onBlur}
          disabled={disabled}
          readOnly={readOnly}
          required={required}
          rows={rows}
          maxLength={maxLength}
          aria-invalid={!!error}
          aria-describedby={error || helper ? hintId : undefined}
          className={textareaClasses}
          {...rest}
        />

        {showCount && maxLength && (
          <span
            className={[
              'absolute bottom-3 right-3 text-2xs font-mono tabular-nums pointer-events-none',
              (value?.length ?? 0) >= maxLength * 0.9
                ? 'text-red-400'
                : 'text-slate-400',
            ].join(' ')}
            aria-live="polite"
          >
            {value?.length ?? 0}/{maxLength}
          </span>
        )}
      </div>

      <InputHint error={error} helper={helper} id={hintId} />
    </div>
  )
})

Textarea.displayName = 'Textarea'

// ─── Select variant ───────────────────────────────────────────────────────────

const ChevronDownIcon = () => (
  <svg
    width="16" height="16"
    viewBox="0 0 16 16"
    fill="none"
    aria-hidden="true"
    className="w-4 h-4"
  >
    <path
      d="M4 6L8 10L12 6"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
)

export const Select = forwardRef(function Select(
  {
    label,
    required = false,
    value,
    defaultValue,
    onChange,
    onBlur,
    error,
    helper,
    disabled = false,
    size = 'md',
    placeholder,
    options = [],
    id: idProp,
    name,
    className = '',
    inputClassName = '',
    ...rest
  },
  ref
) {
  const generatedId = useId()
  const id = idProp || generatedId
  const hintId = `${id}-hint`
  const s = SIZES[size] ?? SIZES.md

  const selectClasses = [
    'w-full font-body text-navy-800 appearance-none',
    s.input,
    s.withIconR,
    'bg-white',
    'border-[1.5px]',
    error
      ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-200'
      : 'border-slate-200 hover:border-slate-300 focus:border-amber-500 focus:ring-2 focus:ring-amber-100',
    'transition-all duration-150 ease-in-out',
    'outline-none cursor-pointer',
    disabled ? 'opacity-50 cursor-not-allowed bg-slate-50' : '',
    inputClassName,
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <div className={`w-full ${className}`}>
      <InputLabel htmlFor={id} label={label} required={required} />

      <div className="relative">
        <select
          ref={ref}
          id={id}
          name={name}
          value={value}
          defaultValue={defaultValue}
          onChange={onChange}
          onBlur={onBlur}
          disabled={disabled}
          required={required}
          aria-invalid={!!error}
          aria-describedby={error || helper ? hintId : undefined}
          className={selectClasses}
          {...rest}
        >
          {placeholder && (
            <option value="" disabled>
              {placeholder}
            </option>
          )}
          {options.map((opt) => {
            const value = typeof opt === 'string' ? opt : opt.value
            const label = typeof opt === 'string' ? opt : opt.label
            return (
              <option key={value} value={value}>
                {label}
              </option>
            )
          })}
        </select>

        <span
          className={[
            'absolute top-1/2 -translate-y-1/2 pointer-events-none text-slate-400',
            s.iconR,
          ].join(' ')}
          aria-hidden="true"
        >
          <ChevronDownIcon />
        </span>
      </div>

      <InputHint error={error} helper={helper} id={hintId} />
    </div>
  )
})

Select.displayName = 'Select'

export default Input
