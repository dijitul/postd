import { useId, useState } from 'react'
import { Link } from 'react-router-dom'
import { ChevronDown, Mail } from 'lucide-react'
import Logo from '../ui/Logo.jsx'
import { CONTACT_EMAIL } from '../../lib/site.js'

const COLUMNS = [
  {
    title: 'Product',
    links: [
      { label: 'How it works', href: '/#how-it-works' },
      { label: 'Platforms', href: '/#platforms' },
      { label: 'Pricing', href: '/#pricing' },
      { label: 'Questions and answers', href: '/#faq' },
      { label: 'Start free trial', to: '/register' },
      { label: 'Log in', to: '/login' },
    ],
  },
  {
    title: 'Guides',
    links: [
      { label: 'All guides', to: '/guides' },
      { label: 'Google Business Profile posts', href: '/guides#gbp' },
      { label: 'Automated social media posting', href: '/guides#automation' },
      { label: 'Social media for local businesses', href: '/guides#local' },
      { label: 'Tools and costs', href: '/guides#tools' },
    ],
  },
  {
    title: 'Company',
    links: [
      { label: 'dijitul', href: 'https://dijitul.uk', external: true },
      { label: 'Privacy policy', to: '/privacy' },
      { label: 'Terms of service', to: '/terms' },
    ],
  },
]

const linkClass =
  'block py-2.5 md:py-1.5 text-sm text-white/60 hover:text-white transition-colors'

function FooterLink({ link }) {
  if (link.to) {
    return <Link to={link.to} className={linkClass}>{link.label}</Link>
  }
  return (
    <a
      href={link.href}
      className={linkClass}
      {...(link.external ? { target: '_blank', rel: 'noopener' } : {})}
    >
      {link.label}
    </a>
  )
}

/**
 * One link column. On phones the heading is a button that expands the list
 * (tap to open, aria-expanded kept in sync). From md up the list is always
 * open and the heading is plain text.
 */
function FooterColumn({ title, links }) {
  const [open, setOpen] = useState(false)
  const listId = useId()

  return (
    <div className="border-b border-white/10 md:border-0">
      <h2 className="font-display font-bold text-sm text-white">
        <button
          type="button"
          className="md:hidden w-full flex items-center justify-between py-4 min-h-[48px] text-left"
          aria-expanded={open}
          aria-controls={listId}
          onClick={() => setOpen((v) => !v)}
        >
          {title}
          <ChevronDown
            className={`w-5 h-5 text-white/50 transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
            aria-hidden="true"
          />
        </button>
        <span className="hidden md:block mb-3">{title}</span>
      </h2>
      <ul id={listId} className={`${open ? 'block' : 'hidden'} md:block pl-0 pb-3 md:pb-0 [&>li]:mb-0`}>
        {links.map((link) => (
          <li key={link.label}>
            <FooterLink link={link} />
          </li>
        ))}
      </ul>
    </div>
  )
}

export default function SiteFooter() {
  const year = new Date().getFullYear()

  return (
    <footer className="bg-navy-950 text-white pb-[env(safe-area-inset-bottom)]">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-8">
        <div className="md:grid md:grid-cols-12 md:gap-8">

          {/* Brand block: always first, never collapsed */}
          <div className="md:col-span-4 mb-8 md:mb-0">
            <Logo variant="reversed" size="sm" />
            <p className="mt-4 text-sm text-white/60 leading-relaxed max-w-xs">
              Automated social media posting for UK small businesses. Written from your website and
              Google reviews, published to Google Business Profile, Facebook, LinkedIn and X.
            </p>
            <a
              href={`mailto:${CONTACT_EMAIL}`}
              className="mt-5 inline-flex items-center gap-3 min-h-[48px] px-4 rounded-xl bg-white/5 border border-white/10 text-white font-semibold text-base hover:bg-white/10 transition-colors"
            >
              <Mail className="w-5 h-5 text-amber-400" aria-hidden="true" />
              {CONTACT_EMAIL}
            </a>
          </div>

          <nav aria-label="Footer" className="md:col-span-8 md:grid md:grid-cols-3 md:gap-8 border-t border-white/10 md:border-0">
            {COLUMNS.map((col) => (
              <FooterColumn key={col.title} {...col} />
            ))}
          </nav>
        </div>

        <div className="mt-8 md:mt-12 pt-6 border-t border-white/10 flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between text-xs text-white/40">
          <p>&copy; {year} postd.uk</p>
          <p>
            Made by{' '}
            <a href="https://dijitul.uk" className="text-white/60 hover:text-white transition-colors underline-offset-2 hover:underline">
              dijitul
            </a>{' '}
            in Mansfield, UK
          </p>
        </div>
      </div>
    </footer>
  )
}
