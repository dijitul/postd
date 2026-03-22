/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{js,jsx,ts,tsx}',
  ],

  theme: {
    extend: {

      // ─── Brand colour palette ───────────────────────────────────────────────
      colors: {

        // Amber — primary CTA, logo accent, active states
        amber: {
          50:  '#FEF6EE',
          100: '#FDE9D5',
          200: '#FBD0A8',
          300: '#F8B06C',
          400: '#F4892E',
          500: '#E07B30', // brand-amber — canonical
          600: '#C96620',
          700: '#B85E1A', // brand-amber-dark — hover states
          800: '#93481A',
          900: '#763C19',
          950: '#3F1D0B',
        },

        // Navy — text, headers, navigation
        navy: {
          50:  '#EEF1F7',
          100: '#D8DFED',
          200: '#B5C2DC',
          300: '#8AA0C5',
          400: '#617EAD',
          500: '#4A6195',
          600: '#3B4F7D',
          700: '#2E4470', // brand-navy-light — gradients
          800: '#1E2D4A', // brand-navy — canonical
          900: '#172239',
          950: '#0D1520',
        },

        // Honey — highlights, badges, premium indicators
        honey: {
          50:  '#FEFCE8',
          100: '#FEF8C3',
          200: '#FEEE89',
          300: '#FDE047',
          400: '#F5C842', // brand-honey — canonical
          500: '#EAB308',
          600: '#CA8A04',
          700: '#A16207',
          800: '#854D0E',
          900: '#713F12',
          950: '#422006',
        },

        // Cream — backgrounds, card surfaces
        cream: {
          50:  '#FFFFFF',
          100: '#FDFBF8',
          200: '#F9F5EE', // brand-cream — canonical
          300: '#F3EBD9',
          400: '#EBE0C4',
          500: '#DDD0AC',
          600: '#C8B88A',
          700: '#AC9B6A',
          800: '#8E7E52',
          900: '#746643',
          950: '#3D3421',
        },

        // Slate — neutral system (text, borders, dividers)
        slate: {
          50:  '#F8FAFC',
          100: '#F1F5F9',
          200: '#E2E8F0',
          300: '#CBD5E1',
          400: '#94A3B8',
          500: '#64748B',
          600: '#475569',
          700: '#334155',
          800: '#1E293B',
          900: '#0F172A',
          950: '#020617',
        },

        // Semantic aliases — use these in components
        brand: {
          amber:        '#E07B30',
          'amber-dark': '#B85E1A',
          'amber-light':'#F4892E',
          navy:         '#1E2D4A',
          'navy-light': '#2E4470',
          honey:        '#F5C842',
          cream:        '#F9F5EE',
          white:        '#FFFFFF',
        },

        // Status colours — platform-appropriate
        status: {
          success:  '#16A34A',
          warning:  '#D97706',
          error:    '#DC2626',
          info:     '#2563EB',
          pending:  '#7C3AED',
        },

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
      fontFamily: {
        display: ['Syne', 'system-ui', 'sans-serif'],
        body:    ['Inter', 'system-ui', 'sans-serif'],
        mono:    ['JetBrains Mono', 'Fira Code', 'monospace'],
        sans:    ['Inter', 'system-ui', 'sans-serif'], // Tailwind base override
      },

      fontSize: {
        // Fluid / fixed type scale
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

      fontWeight: {
        thin:       '100',
        extralight: '200',
        light:      '300',
        normal:     '400',
        medium:     '500',
        semibold:   '600',
        bold:       '700',
        extrabold:  '800',
        black:      '900',
      },

      // ─── Spacing scale ───────────────────────────────────────────────────────
      // Extends Tailwind's default. Brand uses an 8pt grid.
      spacing: {
        '0.5': '0.125rem',  //  2px
        '1':   '0.25rem',   //  4px
        '1.5': '0.375rem',  //  6px
        '2':   '0.5rem',    //  8px
        '2.5': '0.625rem',  // 10px
        '3':   '0.75rem',   // 12px
        '3.5': '0.875rem',  // 14px
        '4':   '1rem',      // 16px
        '5':   '1.25rem',   // 20px
        '6':   '1.5rem',    // 24px
        '7':   '1.75rem',   // 28px
        '8':   '2rem',      // 32px
        '9':   '2.25rem',   // 36px
        '10':  '2.5rem',    // 40px
        '11':  '2.75rem',   // 44px
        '12':  '3rem',      // 48px
        '14':  '3.5rem',    // 56px
        '16':  '4rem',      // 64px
        '18':  '4.5rem',    // 72px
        '20':  '5rem',      // 80px
        '24':  '6rem',      // 96px
        '28':  '7rem',      // 112px
        '32':  '8rem',      // 128px
        '36':  '9rem',      // 144px
        '40':  '10rem',     // 160px
        '44':  '11rem',     // 176px
        '48':  '12rem',     // 192px
        '52':  '13rem',     // 208px
        '56':  '14rem',     // 224px
        '60':  '15rem',     // 240px
        '64':  '16rem',     // 256px
        '72':  '18rem',     // 288px
        '80':  '20rem',     // 320px
        '96':  '24rem',     // 384px
        // Brand semantic spacing tokens
        'xs':  '0.25rem',   //  4px — tight gaps
        'sm':  '0.5rem',    //  8px — small gaps
        'md':  '1rem',      // 16px — standard gap
        'lg':  '1.5rem',    // 24px — medium gap
        'xl':  '2rem',      // 32px — large gap
        '2xl': '3rem',      // 48px — section gap
        '3xl': '4rem',      // 64px — hero gap
        '4xl': '6rem',      // 96px — page section gap
      },

      // ─── Border radius ───────────────────────────────────────────────────────
      borderRadius: {
        none:   '0',
        sm:     '0.25rem',   //  4px
        DEFAULT:'0.375rem',  //  6px
        md:     '0.5rem',    //  8px
        lg:     '0.75rem',   // 12px
        xl:     '1rem',      // 16px
        '2xl':  '1.25rem',   // 20px
        '3xl':  '1.5rem',    // 24px
        '4xl':  '2rem',      // 32px — large cards
        full:   '9999px',    // pill / circle
      },

      // ─── Box shadows ─────────────────────────────────────────────────────────
      boxShadow: {
        // Elevation system
        xs:   '0 1px 2px 0 rgb(0 0 0 / 0.05)',
        sm:   '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
        DEFAULT:'0 4px 6px -1px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.08)',
        md:   '0 4px 6px -1px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.08)',
        lg:   '0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.05)',
        xl:   '0 20px 25px -5px rgb(0 0 0 / 0.08), 0 8px 10px -6px rgb(0 0 0 / 0.05)',
        '2xl':'0 25px 50px -12px rgb(0 0 0 / 0.15)',
        inner:'inset 0 2px 4px 0 rgb(0 0 0 / 0.05)',
        none: 'none',

        // Brand-specific glow shadows
        'amber':       '0 0 0 3px rgb(224 123 48 / 0.25)',
        'amber-lg':    '0 8px 24px -4px rgb(224 123 48 / 0.35), 0 0 0 1px rgb(224 123 48 / 0.15)',
        'amber-glow':  '0 0 20px 4px rgb(224 123 48 / 0.3), 0 4px 16px rgb(224 123 48 / 0.2)',
        'honey':       '0 0 0 3px rgb(245 200 66 / 0.3)',
        'honey-glow':  '0 0 20px 4px rgb(245 200 66 / 0.35)',
        'navy':        '0 0 0 3px rgb(30 45 74 / 0.2)',
        'navy-lg':     '0 8px 24px -4px rgb(30 45 74 / 0.3)',
        'card':        '0 2px 8px 0 rgb(30 45 74 / 0.06), 0 1px 3px 0 rgb(30 45 74 / 0.04)',
        'card-hover':  '0 8px 24px -4px rgb(30 45 74 / 0.1), 0 2px 8px 0 rgb(30 45 74 / 0.06)',
        'card-active': '0 1px 4px 0 rgb(30 45 74 / 0.08)',

        // Focus ring
        'focus':       '0 0 0 3px rgb(224 123 48 / 0.35)',
        'focus-navy':  '0 0 0 3px rgb(46 68 112 / 0.35)',
      },

      // ─── Transitions & animation ─────────────────────────────────────────────
      transitionDuration: {
        0:    '0ms',
        75:   '75ms',
        100:  '100ms',
        150:  '150ms',
        200:  '200ms',
        300:  '300ms',
        500:  '500ms',
        700:  '700ms',
        1000: '1000ms',
      },

      transitionTimingFunction: {
        DEFAULT:   'cubic-bezier(0.4, 0, 0.2, 1)',
        linear:    'linear',
        in:        'cubic-bezier(0.4, 0, 1, 1)',
        out:       'cubic-bezier(0, 0, 0.2, 1)',
        'in-out':  'cubic-bezier(0.4, 0, 0.2, 1)',
        spring:    'cubic-bezier(0.34, 1.56, 0.64, 1)', // slight overshoot
        smooth:    'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
      },

      keyframes: {
        // Amber pulse / glow for CTAs and loading states
        'amber-pulse': {
          '0%, 100%': {
            boxShadow: '0 0 0 0 rgb(224 123 48 / 0.4)',
          },
          '50%': {
            boxShadow: '0 0 0 8px rgb(224 123 48 / 0)',
          },
        },
        'honey-shimmer': {
          '0%':   { backgroundPosition: '-200% center' },
          '100%': { backgroundPosition: '200% center' },
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
        'hex-float': {
          '0%, 100%': { transform: 'translateY(0) rotate(0deg)' },
          '33%':       { transform: 'translateY(-6px) rotate(1deg)' },
          '66%':       { transform: 'translateY(3px) rotate(-0.5deg)' },
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
        'amber-pulse':     'amber-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        'honey-shimmer':   'honey-shimmer 2.5s linear infinite',
        'fade-in':         'fade-in 0.2s ease-out both',
        'fade-in-slow':    'fade-in 0.4s ease-out both',
        'fade-in-up':      'fade-in-up 0.3s ease-out both',
        'fade-in-up-slow': 'fade-in-up 0.5s ease-out both',
        'fade-in-scale':   'fade-in-scale 0.2s ease-out both',
        'slide-in-right':  'slide-in-right 0.25s ease-out both',
        'slide-in-left':   'slide-in-left 0.25s ease-out both',
        'spin-slow':       'spin-slow 3s linear infinite',
        'bounce-subtle':   'bounce-subtle 2s ease-in-out infinite',
        'hex-float':       'hex-float 6s ease-in-out infinite',
        'loading-bar':     'loading-bar 1.5s ease-in-out infinite',
        'skeleton':        'skeleton 1.8s ease-in-out infinite',
        none:              'none',
      },

      // ─── Gradients (background images) ───────────────────────────────────────
      backgroundImage: {
        // Brand gradients
        'gradient-amber':       'linear-gradient(135deg, #E07B30 0%, #F5C842 100%)',
        'gradient-navy':        'linear-gradient(135deg, #1E2D4A 0%, #2E4470 100%)',
        'gradient-navy-deep':   'linear-gradient(180deg, #1E2D4A 0%, #0D1520 100%)',
        'gradient-cream':       'linear-gradient(180deg, #FFFFFF 0%, #F9F5EE 100%)',
        'gradient-amber-soft':  'linear-gradient(135deg, #FEF6EE 0%, #FDE9D5 100%)',
        'gradient-hero':        'linear-gradient(135deg, #1E2D4A 0%, #2E4470 60%, #3B4F7D 100%)',
        'gradient-honey-shine': 'linear-gradient(90deg, transparent 0%, #F5C842 50%, transparent 100%)',
        // Skeleton loading
        'skeleton':             'linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%)',
        // Glass effect
        'glass-amber':          'linear-gradient(135deg, rgb(224 123 48 / 0.1) 0%, rgb(245 200 66 / 0.05) 100%)',
        'glass-navy':           'linear-gradient(135deg, rgb(30 45 74 / 0.8) 0%, rgb(46 68 112 / 0.8) 100%)',
        'none':                 'none',
      },

      // ─── Screens / breakpoints ────────────────────────────────────────────────
      screens: {
        xs:  '375px',
        sm:  '640px',
        md:  '768px',
        lg:  '1024px',
        xl:  '1280px',
        '2xl': '1536px',
      },

      // ─── Z-index scale ───────────────────────────────────────────────────────
      zIndex: {
        0:    '0',
        10:   '10',
        20:   '20',
        30:   '30',
        40:   '40',
        50:   '50',
        header:  '100',
        dropdown:'200',
        overlay: '300',
        modal:   '400',
        toast:   '500',
        tooltip: '600',
      },

      // ─── Aspect ratios ───────────────────────────────────────────────────────
      aspectRatio: {
        auto:    'auto',
        square:  '1 / 1',
        video:   '16 / 9',
        portrait:'3 / 4',
        post:    '4 / 5',  // Instagram portrait post ratio
        banner:  '3 / 1',
      },

      // ─── Max widths ──────────────────────────────────────────────────────────
      maxWidth: {
        xs:   '20rem',   // 320px
        sm:   '24rem',   // 384px
        md:   '28rem',   // 448px
        lg:   '32rem',   // 512px
        xl:   '36rem',   // 576px
        '2xl':'42rem',   // 672px
        '3xl':'48rem',   // 768px
        '4xl':'56rem',   // 896px
        '5xl':'64rem',   // 1024px
        '6xl':'72rem',   // 1152px
        '7xl':'80rem',   // 1280px
        full: '100%',
        prose:'65ch',    // readable body copy
        none: 'none',
      },

    },
  },

  plugins: [],
}
