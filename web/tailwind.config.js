/** @type {import('tailwindcss').Config} */
/*
 * Relay: the postd brand (2027). Source of truth: docs/brand-2027/README.md.
 * The old amber, honey and cream scales are gone. Tailwind's default red,
 * green, purple, blue, yellow and orange are re-pointed at the Relay semantic
 * colours so every status chip stays on-brand wherever it is used.
 */
const cobalt = {
  50:  '#EEF1FF', // tinted panels
  100: '#E0E6FF', // selected rows, soft chips, focus ring
  200: '#C2CCFF',
  300: '#95A5FF',
  400: '#4A63FF', // links and accents on dark backgrounds
  500: '#3550FA',
  600: '#2340F5', // primary
  700: '#1A30C4', // primary hover / pressed
  800: '#15279B',
  900: '#101D73',
  950: '#0A1350',
}

const success = {
  50:  '#E6F6EC', 100: '#CDEDD9', 200: '#A3DDB8', 300: '#6CC791', 400: '#34B06A',
  500: '#12A150', 600: '#0B7F3D', 700: '#096A33', 800: '#075429', 900: '#05401F',
}

const danger = {
  50:  '#FDECEA', 100: '#FAD7D3', 200: '#F5B1AA', 300: '#EE8379', 400: '#E4574B',
  500: '#D92D20', 600: '#C4271B', 700: '#A61F15', 800: '#861910', 900: '#66130C',
}

const warn = {
  50:  '#FFFAEF', 100: '#FFF3DC', 200: '#FFE3AD', 300: '#FFCB6B', 400: '#F0A82A',
  500: '#D98A00', 600: '#B76E00', 700: '#8F5600', 800: '#6E4200', 900: '#4D2E00',
}

const ai = {
  50:  '#EFEBFF', 100: '#E2DBFF', 200: '#C8BBFF', 300: '#A797FF', 400: '#8A73FF',
  500: '#7A5FFF', 600: '#6B4EFF', 700: '#5539DB', 800: '#4129AD', 900: '#2F1E80',
}

const sans = ['"Plus Jakarta Sans"', 'system-ui', '-apple-system', '"Segoe UI"', 'sans-serif']

export default {
  content: [
    './index.html',
    './src/**/*.{js,jsx,ts,tsx}',
  ],

  theme: {
    extend: {

      // ─── Relay colour palette ───────────────────────────────────────────────
      colors: {
        cobalt,

        // Sun: one highlighted word, badges, the logo dot. Never text on white.
        sun: {
          50:  '#FFFBEB',
          100: '#FFF4CC', // soft highlight panels
          200: '#FFE994',
          300: '#FFDD66',
          400: '#FFD23F', // highlight
          500: '#F5C21A',
          600: '#D9A400',
          700: '#8F5600', // text-safe dark, AA on white and sun-100
          800: '#6E4200',
        },

        // Navy: ink, dark sections, footer
        navy: {
          50:  '#EEF0F7',
          100: '#DCE0EE',
          200: '#B9C0D9',
          300: '#8E98BA',
          400: '#5F6A91',
          500: '#3D4870',
          600: '#313D66',
          700: '#26335C', // secondary dark surfaces
          800: '#17234D',
          900: '#0E1A3F', // ink
          950: '#08112B',
        },

        // Slate: Relay neutrals
        slate: {
          50:  '#F7F8FC',
          100: '#F3F5FB',
          200: '#E3E7F1',
          300: '#C9CFDF',
          400: '#8A93AB', // placeholders and disabled only
          500: '#56607A', // secondary text, 5.9:1 on white
          600: '#454E68',
          700: '#343C55',
          800: '#232A42',
          900: '#0E1A3F',
          950: '#08112B',
        },

        line:  { DEFAULT: '#E3E7F1', strong: '#C9CFDF' },
        cloud: { DEFAULT: '#F3F5FB' },

        // AI violet: machine-written content only
        ai,

        // Semantic
        success: { DEFAULT: '#12A150', soft: '#E6F6EC', text: '#0B7F3D' },
        warn:    { DEFAULT: '#B76E00', soft: '#FFF3DC', text: '#8F5600' },
        danger:  { DEFAULT: '#D92D20', soft: '#FDECEA', text: '#B42318' },

        // Tailwind defaults re-pointed at Relay semantics
        green: success,
        emerald: success,
        red: danger,
        rose: danger,
        yellow: warn,
        orange: warn,
        purple: ai,
        violet: ai,
        blue: cobalt,
        indigo: cobalt,

        // Platform brand colours (for badges / icons)
        platform: {
          facebook:  '#1877F2',
          instagram: '#E1306C',
          x:         '#000000',
          linkedin:  '#0A66C2',
          tiktok:    '#010101',
          google:    '#4285F4',
        },
      },

      // ─── Typography ─────────────────────────────────────────────────────────
      // Sora: headings, buttons, numbers that matter. Plus Jakarta Sans: the rest.
      fontFamily: {
        display: ['Sora', 'system-ui', '-apple-system', '"Segoe UI"', 'sans-serif'],
        body:    sans,
        sans,
        mono:    ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
      },

      fontSize: {
        '2xs': ['0.625rem',  { lineHeight: '0.875rem' }],  // 10px
        xs:    ['0.75rem',   { lineHeight: '1rem' }],       // 12px
        sm:    ['0.875rem',  { lineHeight: '1.25rem' }],    // 14px
        base:  ['1rem',      { lineHeight: '1.5rem' }],     // 16px
        lg:    ['1.125rem',  { lineHeight: '1.75rem' }],    // 18px
        xl:    ['1.25rem',   { lineHeight: '1.75rem' }],    // 20px
        '2xl': ['1.5rem',    { lineHeight: '2rem' }],       // 24px
        '3xl': ['1.875rem',  { lineHeight: '2.25rem' }],    // 30px
        '4xl': ['2.25rem',   { lineHeight: '2.5rem' }],     // 36px
        '5xl': ['3rem',      { lineHeight: '1.1' }],        // 48px
        '6xl': ['3.75rem',   { lineHeight: '1.05' }],       // 60px
        '7xl': ['4.5rem',    { lineHeight: '1' }],          // 72px
        '8xl': ['6rem',      { lineHeight: '1' }],          // 96px
      },

      letterSpacing: {
        tightest: '-0.035em', // Relay headline tracking
      },

      // ─── Spacing scale ───────────────────────────────────────────────────────
      spacing: {
        '0.5': '0.125rem',
        '1':   '0.25rem',
        '1.5': '0.375rem',
        '2':   '0.5rem',
        '2.5': '0.625rem',
        '3':   '0.75rem',
        '3.5': '0.875rem',
        '4':   '1rem',
        '5':   '1.25rem',
        '6':   '1.5rem',
        '7':   '1.75rem',
        '8':   '2rem',
        '9':   '2.25rem',
        '10':  '2.5rem',
        '11':  '2.75rem',
        '12':  '3rem',
        '14':  '3.5rem',
        '16':  '4rem',
        '18':  '4.5rem',
        '20':  '5rem',
        '24':  '6rem',
        '28':  '7rem',
        '32':  '8rem',
        '36':  '9rem',
        '40':  '10rem',
        '44':  '11rem',
        '48':  '12rem',
        '52':  '13rem',
        '56':  '14rem',
        '60':  '15rem',
        '64':  '16rem',
        '72':  '18rem',
        '80':  '20rem',
        '96':  '24rem',
        'xs':  '0.25rem',
        'sm':  '0.5rem',
        'md':  '1rem',
        'lg':  '1.5rem',
        'xl':  '2rem',
        '2xl': '3rem',
        '3xl': '4rem',
        '4xl': '6rem',
      },

      // ─── Border radius ───────────────────────────────────────────────────────
      borderRadius: {
        none:   '0',
        sm:     '0.25rem',   //  4px
        DEFAULT:'0.375rem',  //  6px
        md:     '0.5rem',    //  8px
        lg:     '0.75rem',   // 12px, inputs
        xl:     '1rem',      // 16px
        '2xl':  '1.125rem',  // 18px, Relay cards
        '3xl':  '1.5rem',    // 24px
        '4xl':  '2rem',      // 32px
        full:   '9999px',    // pills
      },

      // ─── Box shadows ─────────────────────────────────────────────────────────
      boxShadow: {
        xs:   '0 1px 2px 0 rgba(14,26,63,.05)',
        sm:   '0 1px 3px 0 rgba(14,26,63,.08), 0 1px 2px -1px rgba(14,26,63,.06)',
        DEFAULT:'0 4px 6px -1px rgba(14,26,63,.07), 0 2px 4px -2px rgba(14,26,63,.06)',
        md:   '0 4px 6px -1px rgba(14,26,63,.07), 0 2px 4px -2px rgba(14,26,63,.06)',
        lg:   '0 10px 15px -3px rgba(14,26,63,.08), 0 4px 6px -4px rgba(14,26,63,.05)',
        xl:   '0 20px 25px -5px rgba(14,26,63,.08), 0 8px 10px -6px rgba(14,26,63,.05)',
        '2xl':'0 25px 50px -12px rgba(14,26,63,.18)',
        inner:'inset 0 2px 4px 0 rgba(14,26,63,.05)',
        none: 'none',

        // Relay depth
        'marketing':   '0 10px 30px rgba(14,26,63,.08)',
        'lift':        '0 18px 44px rgba(14,26,63,.14)',
        'popover':     '0 12px 32px rgba(14,26,63,.14)',
        'cobalt':      '0 0 0 3px #E0E6FF',
        'cobalt-lg':   '0 10px 24px -6px rgba(35,64,245,.45)',
        'navy-lg':     '0 10px 30px rgba(14,26,63,.16)',
        'card':        '0 1px 2px rgba(14,26,63,.04)',
        'card-hover':  '0 10px 30px rgba(14,26,63,.08)',
        'card-active': '0 1px 3px rgba(14,26,63,.06)',
        'focus':       '0 0 0 3px #E0E6FF',
      },

      // ─── Transitions & animation ─────────────────────────────────────────────
      transitionTimingFunction: {
        DEFAULT:   'cubic-bezier(0.4, 0, 0.2, 1)',
        spring:    'cubic-bezier(0.34, 1.56, 0.64, 1)',
        smooth:    'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
      },

      keyframes: {
        'pulse-ring': {
          '0%, 100%': { boxShadow: '0 0 0 0 rgba(35,64,245,.35)' },
          '50%':      { boxShadow: '0 0 0 8px rgba(35,64,245,0)' },
        },
        'fade-in': {
          from: { opacity: '0', transform: 'translateY(4px)' },
          to:   { opacity: '1', transform: 'translateY(0)' },
        },
        'fade-in-up': {
          from: { opacity: '0', transform: 'translateY(12px)' },
          to:   { opacity: '1', transform: 'translateY(0)' },
        },
        'fade-in-scale': {
          from: { opacity: '0', transform: 'scale(0.95)' },
          to:   { opacity: '1', transform: 'scale(1)' },
        },
        'slide-in-right': {
          from: { opacity: '0', transform: 'translateX(16px)' },
          to:   { opacity: '1', transform: 'translateX(0)' },
        },
        'slide-in-left': {
          from: { opacity: '0', transform: 'translateX(-16px)' },
          to:   { opacity: '1', transform: 'translateX(0)' },
        },
        'spin-slow': {
          from: { transform: 'rotate(0deg)' },
          to:   { transform: 'rotate(360deg)' },
        },
        'bounce-subtle': {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%':       { transform: 'translateY(-4px)' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%':      { transform: 'translateY(-8px)' },
        },
        'loading-bar': {
          '0%':   { transform: 'translateX(-100%)' },
          '100%': { transform: 'translateX(100%)' },
        },
        skeleton: {
          '0%':   { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition: '200% 0' },
        },
      },

      animation: {
        'pulse-ring':      'pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        'fade-in':         'fade-in 0.2s ease-out both',
        'fade-in-slow':    'fade-in 0.4s ease-out both',
        'fade-in-up':      'fade-in-up 0.3s ease-out both',
        'fade-in-up-slow': 'fade-in-up 0.5s ease-out both',
        'fade-in-scale':   'fade-in-scale 0.2s ease-out both',
        'slide-in-right':  'slide-in-right 0.25s ease-out both',
        'slide-in-left':   'slide-in-left 0.25s ease-out both',
        'spin-slow':       'spin-slow 3s linear infinite',
        'bounce-subtle':   'bounce-subtle 2s ease-in-out infinite',
        'float':           'float 6s ease-in-out infinite',
        'float-slow':      'float 8s ease-in-out infinite',
        'loading-bar':     'loading-bar 1.5s ease-in-out infinite',
        'skeleton':        'skeleton 1.8s ease-in-out infinite',
        none:              'none',
      },

      backgroundImage: {
        'skeleton': 'linear-gradient(90deg, #F3F5FB 25%, #E3E7F1 50%, #F3F5FB 75%)',
      },

      screens: {
        xs:  '375px',
      },

      zIndex: {
        header:  '100',
        dropdown:'200',
        overlay: '300',
        modal:   '400',
        toast:   '500',
        tooltip: '600',
      },

      aspectRatio: {
        portrait:'3 / 4',
        post:    '4 / 5',
        banner:  '3 / 1',
      },

      maxWidth: {
        prose:'65ch',
        site: '75rem', // 1200px marketing max width
      },
    },
  },

  plugins: [],
}
