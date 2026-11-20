import { Link } from 'react-router-dom'
import { ArrowRight, Check } from 'lucide-react'
import { OFFICE_URL } from '../Marketing/OfficePromo.jsx'

/** Tags a Postd Office link so each guide's sign-ups can be attributed. */
export function officeHref(slug, medium = 'guide') {
  const params = new URLSearchParams({ utm_source: 'postd.uk', utm_medium: medium })
  if (slug) params.set('utm_campaign', slug)
  return `${OFFICE_URL}/?${params.toString()}`
}

function Ticks({ items }) {
  return (
    <ul className="flex flex-wrap gap-x-5 gap-y-2 pl-0 text-sm text-white/80 mb-7 [&>li]:mb-0">
      {items.map((item) => (
        <li key={item} className="flex items-center gap-1.5">
          <Check className="w-4 h-4 text-sun-400" aria-hidden="true" /> {item}
        </li>
      ))}
    </ul>
  )
}

function Panel({ label, children }) {
  return (
    <aside
      aria-label={label}
      className="relative overflow-hidden rounded-2xl bg-navy-900 p-6 sm:p-10 text-white"
    >
      <div className="absolute -top-12 -right-12 w-40 h-40 rounded-full bg-sun-400 pointer-events-none hidden sm:block" aria-hidden="true" />
      <div className="relative max-w-2xl">{children}</div>
    </aside>
  )
}

function OfficeCta({ slug }) {
  return (
    <Panel label="Try Postd Office">
      <p className="font-display font-extrabold text-2xl sm:text-3xl tracking-tight text-white mb-3 text-balance">
        Every DM and comment in one inbox
      </p>
      <p className="text-white/75 mb-6 text-pretty">
        Postd Office brings Facebook and Instagram messages and comments, plus TikTok comments, into one list.
        Each one arrives with a reply drafted from answers you have approved, translated if it needs to be.
      </p>
      <Ticks items={['From £49 a month + VAT', 'Unlimited team seats', '30-day money-back guarantee']} />
      <a href={officeHref(slug)} className="btn btn-on-dark btn-lg w-full sm:w-auto">
        See Postd Office
        <ArrowRight className="w-5 h-5" aria-hidden="true" />
      </a>
    </Panel>
  )
}

/**
 * Call to action shown under every guide and on the guides index. `product`
 * comes from the guide's frontmatter: postd (default), office or both.
 */
export default function GuideCta({ slug, product = 'postd' }) {
  if (product === 'office') return <OfficeCta slug={slug} />

  // Tag the sign-up link so each guide's trials can be attributed.
  const href = slug
    ? `/register?utm_source=postd.uk&utm_medium=guide&utm_campaign=${encodeURIComponent(slug)}`
    : '/register?utm_source=postd.uk&utm_medium=guides'

  return (
    <Panel label="Try postd.uk">
      <p className="font-display font-extrabold text-2xl sm:text-3xl tracking-tight text-white mb-3 text-balance">
        Rather not write the posts yourself?
      </p>
      <p className="text-white/75 mb-6 text-pretty">
        postd.uk reads your website and Google reviews, writes posts in UK English and publishes them to
        Google Business Profile, Facebook, LinkedIn and X on a schedule. Approve each one, or let it run.
      </p>
      <Ticks items={['Free trial', 'No card required', 'Cancel anytime']} />
      <Link to={href} className="btn btn-on-dark btn-lg w-full sm:w-auto">
        Start your free trial
        <ArrowRight className="w-5 h-5" aria-hidden="true" />
      </Link>
      {product === 'both' && (
        <p className="mt-6 text-sm text-white/75 text-pretty">
          Drowning in DMs and comments too?{' '}
          <a href={officeHref(slug)} className="text-sun-400 font-semibold underline underline-offset-2">
            Postd Office
          </a>{' '}
          puts Facebook, Instagram and TikTok messages in one inbox with replies ready to send.
        </p>
      )}
    </Panel>
  )
}
