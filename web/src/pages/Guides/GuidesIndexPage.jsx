import { Link } from 'react-router-dom'
import { ArrowRight, BookOpen } from 'lucide-react'
import { Seo } from '../../lib/head.jsx'
import { SITE_URL, websiteSchema, organizationSchema, breadcrumbSchema, graph } from '../../lib/site.js'
import { guides, pillars, formatDate } from '../../lib/guides.js'
import SiteHeader from '../../components/marketing/SiteHeader.jsx'
import SiteFooter from '../../components/marketing/SiteFooter.jsx'
import GuideCta from './GuideCta.jsx'

const PAGE_URL = `${SITE_URL}/guides`

const PILLAR_INTROS = {
  gbp: 'What to post on your Google Business Profile, how often, and how to keep it active without logging in every week.',
  automation: 'How automated and AI-written social media posting works for a small business, and where it helps or does not.',
  local: 'Practical, UK-flavoured ideas for trades, salons, cafés and other local businesses.',
  tools: 'Honest comparisons of social media tools and what they cost a UK small business.',
}

const TYPE_LABELS = {
  pillar: 'Complete guide',
  comparison: 'Comparison',
  trade: 'Trade guide',
  template: 'Templates',
}

function GuideCard({ guide }) {
  return (
    <li>
      <Link
        to={`/guides/${guide.slug}`}
        className="group flex flex-col h-full bg-white rounded-2xl p-5 sm:p-6 border border-cream-300 hover:-translate-y-0.5 hover:shadow-lg transition-all duration-200"
      >
        {TYPE_LABELS[guide.type] && (
          <span className="self-start text-2xs font-black uppercase tracking-wide text-amber-700 bg-amber-500/10 px-2.5 py-1 rounded-full mb-3">
            {TYPE_LABELS[guide.type]}
          </span>
        )}
        <h3 className="font-display font-bold text-lg text-navy-800 mb-2 group-hover:text-amber-700 transition-colors text-balance">
          {guide.title}
        </h3>
        <p className="text-sm text-slate-600 leading-relaxed mb-4 flex-1">{guide.description}</p>
        <p className="text-xs text-slate-400">
          <time dateTime={guide.updated ?? guide.date}>{formatDate(guide.updated ?? guide.date)}</time>
          {' · '}
          {guide.readingMinutes} min read
        </p>
      </Link>
    </li>
  )
}

export default function GuidesIndexPage() {
  const groups = Object.entries(pillars)
    .map(([key, label]) => ({
      key,
      label,
      // The pillar guide leads its group; the rest stay newest first.
      items: guides
        .filter((g) => g.pillar === key)
        .sort((a, b) => (a.type === 'pillar') === (b.type === 'pillar') ? 0 : a.type === 'pillar' ? -1 : 1),
    }))
    .filter((group) => group.items.length > 0)

  const jsonLd = graph(
    organizationSchema,
    websiteSchema,
    {
      '@type': 'CollectionPage',
      '@id': `${PAGE_URL}#page`,
      url: PAGE_URL,
      name: 'Social media guides for UK small businesses',
      inLanguage: 'en-GB',
      isPartOf: { '@id': websiteSchema['@id'] },
      publisher: { '@id': organizationSchema['@id'] },
      mainEntity: {
        '@type': 'ItemList',
        itemListElement: guides.map((g, i) => ({
          '@type': 'ListItem',
          position: i + 1,
          url: `${SITE_URL}/guides/${g.slug}`,
          name: g.title,
        })),
      },
    },
    breadcrumbSchema(
      [
        { name: 'Home', path: '/' },
        { name: 'Guides', path: '/guides' },
      ],
      PAGE_URL
    )
  )

  return (
    <div className="min-h-screen bg-cream-200 flex flex-col">
      <Seo
        title="Social Media Guides for UK Small Businesses | postd.uk"
        description="Practical guides to Google Business Profile posts, automated social media and what to post as a UK local business, written by the team behind postd.uk."
        path="/guides"
        jsonLd={jsonLd}
      />
      <SiteHeader />

      <main className="flex-1">
        <section className="honeycomb-bg border-b border-cream-300">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
            <nav aria-label="Breadcrumb" className="text-sm text-slate-500 mb-6">
              <ol className="flex flex-wrap items-center gap-1.5 pl-0 [&>li]:mb-0">
                <li><Link to="/" className="hover:text-amber-700 transition-colors">Home</Link></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page" className="text-navy-700 font-medium">Guides</li>
              </ol>
            </nav>
            <h1 className="font-display font-black text-navy-800 mb-4 text-balance" style={{ fontSize: 'clamp(2rem, 5vw, 3.25rem)', lineHeight: '1.1' }}>
              Social media guides for UK small businesses
            </h1>
            <p className="text-lg text-slate-600 max-w-2xl text-pretty">
              Straight answers on Google Business Profile, automated posting and what to post as a local
              business. Written in Mansfield by the team that builds postd.uk.
            </p>

            {groups.length > 1 && (
              <nav aria-label="Guide topics" className="mt-8 flex flex-wrap gap-2">
                {groups.map((group) => (
                  <a
                    key={group.key}
                    href={`#${group.key}`}
                    className="inline-flex items-center min-h-[44px] px-4 rounded-full bg-white border border-cream-300 text-sm font-semibold text-navy-700 hover:border-amber-500 hover:text-amber-700 transition-colors"
                  >
                    {group.label}
                  </a>
                ))}
              </nav>
            )}
          </div>
        </section>

        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 space-y-16">
          {groups.length === 0 && (
            <div className="bg-white rounded-3xl border border-cream-300 p-8 text-center">
              <BookOpen className="w-8 h-8 text-amber-500 mx-auto mb-4" aria-hidden="true" />
              <p className="text-slate-600">The first guides are on their way. Check back soon.</p>
            </div>
          )}

          {groups.map((group) => (
            <section key={group.key} id={group.key} aria-labelledby={`${group.key}-heading`} className="scroll-mt-24">
              <div className="mb-6 max-w-2xl">
                <h2 id={`${group.key}-heading`} className="font-display font-black text-2xl sm:text-3xl text-navy-800 mb-2">
                  {group.label}
                </h2>
                <p className="text-slate-600">{PILLAR_INTROS[group.key]}</p>
              </div>
              <ul className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 pl-0 [&>li]:mb-0">
                {group.items.map((guide) => (
                  <GuideCard key={guide.slug} guide={guide} />
                ))}
              </ul>
            </section>
          ))}

          <GuideCta />

          <p className="text-sm text-slate-500">
            Want the short version?{' '}
            <a href="/#faq" className="font-semibold text-amber-700 hover:text-amber-800 inline-flex items-center gap-1">
              Read the postd.uk questions and answers <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
            </a>
          </p>
        </div>
      </main>

      <SiteFooter />
    </div>
  )
}
