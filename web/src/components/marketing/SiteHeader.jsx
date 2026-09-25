import { Link } from 'react-router-dom'
import { ArrowRight } from 'lucide-react'
import Logo from '../ui/Logo.jsx'

/**
 * Header for the public content pages (guides, legal, 404). The home page
 * keeps its own transparent-to-solid header.
 */
export default function SiteHeader() {
  return (
    <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-cream-300">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16 gap-4">
          <Logo size="md" />

          <nav aria-label="Main" className="hidden md:flex items-center gap-6">
            <a href="/#how-it-works" className="text-sm font-medium text-navy-700 hover:text-amber-500 transition-colors">
              How it works
            </a>
            <a href="/#pricing" className="text-sm font-medium text-navy-700 hover:text-amber-500 transition-colors">
              Pricing
            </a>
            <Link to="/guides" className="text-sm font-medium text-navy-700 hover:text-amber-500 transition-colors">
              Guides
            </Link>
          </nav>

          <div className="flex items-center gap-2 sm:gap-3">
            <Link to="/login" className="text-sm font-semibold text-navy-700 hover:text-amber-500 transition-colors px-3 py-2 hidden sm:block">
              Log in
            </Link>
            <Link
              to="/register"
              className="inline-flex items-center gap-2 bg-amber-500 text-white text-sm font-bold px-4 sm:px-5 py-2.5 rounded-xl hover:bg-amber-700 active:scale-[0.97] transition-all duration-200"
              style={{ boxShadow: '0 4px 16px rgb(224 123 48 / 0.3)' }}
            >
              Start free trial
              <ArrowRight className="w-4 h-4" aria-hidden="true" />
            </Link>
          </div>
        </div>
      </div>
    </header>
  )
}
