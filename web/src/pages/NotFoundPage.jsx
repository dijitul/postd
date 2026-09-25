import { Link } from 'react-router-dom'
import { ArrowRight } from 'lucide-react'
import { Seo } from '../lib/head.jsx'
import SiteHeader from '../components/marketing/SiteHeader.jsx'
import SiteFooter from '../components/marketing/SiteFooter.jsx'

/**
 * Prerendered to dist/404.html. nginx returns it with a real 404 status for
 * any URL that is neither a public page nor an app route.
 */
export default function NotFoundPage() {
  return (
    <div className="min-h-screen bg-cream-200 flex flex-col">
      <Seo
        title="Page not found | postd.uk"
        description="The page you were looking for is not on postd.uk. Try the home page or the guides."
        path={null}
        robots="noindex, follow"
      />
      <SiteHeader />

      <main className="flex-1 flex items-center">
        <div className="max-w-xl mx-auto px-4 sm:px-6 py-20 text-center">
          <p className="text-amber-500 text-sm font-bold uppercase tracking-widest mb-4">Error 404</p>
          <h1 className="font-display font-black text-navy-800 text-3xl sm:text-4xl mb-4 text-balance">
            We could not find that page
          </h1>
          <p className="text-lg text-slate-600 mb-8 text-pretty">
            The link may be old or mistyped. These are good places to carry on from.
          </p>
          <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
            <Link
              to="/"
              className="inline-flex items-center justify-center gap-2 bg-amber-500 text-white font-bold px-6 py-3.5 rounded-xl hover:bg-amber-700 transition-colors"
            >
              Go to the home page
              <ArrowRight className="w-4 h-4" aria-hidden="true" />
            </Link>
            <Link
              to="/guides"
              className="inline-flex items-center justify-center gap-2 border border-navy-200 text-navy-700 font-semibold px-6 py-3.5 rounded-xl hover:bg-white transition-colors"
            >
              Read the guides
            </Link>
          </div>
        </div>
      </main>

      <SiteFooter />
    </div>
  )
}
