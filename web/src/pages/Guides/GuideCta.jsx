import { Link } from 'react-router-dom'
import { ArrowRight, Check } from 'lucide-react'

/** Trial call to action shown under every guide and on the guides index. */
export default function GuideCta({ slug }) {
  // Tag the sign-up link so each guide's trials can be attributed.
  const href = slug
    ? `/register?utm_source=postd.uk&utm_medium=guide&utm_campaign=${encodeURIComponent(slug)}`
    : '/register?utm_source=postd.uk&utm_medium=guides'

  return (
    <aside
      aria-label="Try postd.uk"
      className="relative overflow-hidden rounded-3xl bg-gradient-navy p-6 sm:p-10 text-white"
    >
      <div className="absolute inset-0 honeycomb-bg opacity-20 pointer-events-none" aria-hidden="true" />
      <div className="relative max-w-2xl">
        <p className="font-display font-black text-2xl sm:text-3xl mb-3 text-balance">
          Rather not write the posts yourself?
        </p>
        <p className="text-white/75 mb-6 text-pretty">
          postd.uk reads your website and Google reviews, writes posts in UK English and publishes them to
          Google Business Profile, Facebook, LinkedIn and X on a schedule. Approve each one, or let it run.
        </p>
        <ul className="flex flex-wrap gap-x-5 gap-y-2 pl-0 text-sm text-white/70 mb-7 [&>li]:mb-0">
          <li className="flex items-center gap-1.5"><Check className="w-4 h-4 text-amber-400" aria-hidden="true" /> Free trial</li>
          <li className="flex items-center gap-1.5"><Check className="w-4 h-4 text-amber-400" aria-hidden="true" /> No card required</li>
          <li className="flex items-center gap-1.5"><Check className="w-4 h-4 text-amber-400" aria-hidden="true" /> Cancel anytime</li>
        </ul>
        <Link
          to={href}
          className="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 bg-amber-500 text-white font-bold text-base px-7 py-4 rounded-2xl hover:bg-amber-600 active:scale-[0.98] transition-all duration-200"
          style={{ boxShadow: '0 8px 24px rgb(224 123 48 / 0.4)' }}
        >
          Start your free trial
          <ArrowRight className="w-5 h-5" aria-hidden="true" />
        </Link>
      </div>
    </aside>
  )
}
