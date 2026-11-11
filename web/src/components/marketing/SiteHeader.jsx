import { Link } from 'react-router-dom'
import { ArrowRight } from 'lucide-react'
import Logo from '../ui/Logo.jsx'

const navLink =
  'inline-flex items-center min-h-[44px] px-1 text-sm font-medium text-navy-900 hover:text-cobalt-600 transition-colors'

/**
 * Header for every public page: home, guides, legal and 404. Static markup
 * only, so the prerendered HTML matches the first client render.
 */
export default function SiteHeader() {
  return (
    <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-line">
      <div className="container-brand">
        <div className="flex items-center justify-between h-16 lg:h-[72px] gap-4">
          <Logo size="md" />

          <nav aria-label="Main" className="hidden md:flex items-center gap-5 lg:gap-7">
            <a href="/#how-it-works" className={navLink}>How it works</a>
            <a href="/#platforms" className={`${navLink} hidden lg:inline-flex`}>Platforms</a>
            <a href="/#pricing" className={navLink}>Pricing</a>
            <Link to="/guides" className={navLink}>Guides</Link>
            <a href="https://office.postd.uk" className={`${navLink} hidden lg:inline-flex gap-1.5`}>
              Postd Office
              <span className="text-2xs font-bold uppercase tracking-wide bg-sun-400 text-navy-900 px-2 py-0.5 rounded-full">New</span>
            </a>
          </nav>

          <div className="flex items-center gap-1 sm:gap-2">
            <Link to="/login" className="btn btn-ghost btn-sm hidden sm:inline-flex">
              Log in
            </Link>
            <Link to="/register" className="btn btn-primary btn-sm">
              Start free trial
              <ArrowRight className="w-4 h-4 hidden xs:block" aria-hidden="true" />
            </Link>
          </div>
        </div>
      </div>
    </header>
  )
}
