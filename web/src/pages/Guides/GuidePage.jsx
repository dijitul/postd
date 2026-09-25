import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowRight, ChevronDown, ListOrdered } from 'lucide-react'
import { Seo } from '../../lib/head.jsx'
import {
  SITE_URL,
  DEFAULT_OG_IMAGE,
  organizationSchema,
  websiteSchema,
  breadcrumbSchema,
  faqSchema,
  graph,
} from '../../lib/site.js'
import { readGuide, relatedGuides, formatDate } from '../../lib/guides.js'
import SiteHeader from '../../components/marketing/SiteHeader.jsx'
import SiteFooter from '../../components/marketing/SiteFooter.jsx'
import NotFoundPage from '../NotFoundPage.jsx'
import GuideCta from './GuideCta.jsx'

function authorParts(author) {
  // Frontmatter author is "Olly, dijitul": a person, then who they work for.
  const [name, org] = String(author).split(',').map((s) => s.trim())
  return { name: name || 'Olly', org: org || 'dijitul' }
}

function articleSchema(guide, url) {
  const { name } = authorParts(guide.author)
  return {
    '@type': 'Article',
    '@id': `${url}#article`,
    headline: guide.title,
    description: guide.description,
    image: DEFAULT_OG_IMAGE,
    datePublished: guide.date,
    dateModified: guide.updated ?? guide.date,
    inLanguage: 'en-GB',
    author: {
      '@type': 'Person',
      name,
      worksFor: { '@id': organizationSchema['@id'] },
      url: 'https://dijitul.uk',
    },
    publisher: { '@id': organizationSchema['@id'] },
    isPartOf: { '@id': websiteSchema['@id'] },
    mainEntityOfPage: { '@type': 'WebPage', '@id': url },
    articleSection: guide.pillarLabel,
    ...(guide.primaryKeyword ? { keywords: guide.primaryKeyword } : {}),
  }
}

function TableOfContents({ toc, variant }) {
  const items = toc.map((item) => (
    <li key={item.id} className={item.level === 3 ? 'pl-4' : ''}>
      <a
        href={`#${item.id}`}
        className="block py-1.5 text-sm text-slate-600 hover:text-amber-700 transition-colors leading-snug"
      >
        {item.text}
      </a>
    </li>
  ))

  if (variant === 'inline') {
    // Phones and tablets: collapsed by default above the article
    return (
      <details className="lg:hidden group mb-8 bg-white rounded-2xl border border-cream-300">
        <summary className="flex items-center justify-between gap-3 px-5 min-h-[52px] cursor-pointer list-none font-display font-bold text-navy-800 [&::-webkit-details-marker]:hidden">
          <span className="flex items-center gap-2">
            <ListOrdered className="w-4 h-4 text-amber-500" aria-hidden="true" />
            On this page
          </span>
          <ChevronDown className="w-5 h-5 text-slate-400 transition-transform group-open:rotate-180" aria-hidden="true" />
        </summary>
        <nav aria-label="On this page" className="px-5 pb-4">
          <ol className="pl-0 [&>li]:mb-0">{items}</ol>
        </nav>
      </details>
    )
  }

  // Desktop: sticky in the sidebar
  return (
    <nav aria-label="On this page">
      <p className="font-display font-bold text-sm text-navy-800 mb-2">On this page</p>
      <ol className="border-l border-cream-300 pl-4 [&>li]:mb-0">{items}</ol>
    </nav>
  )
}

export default function GuidePage() {
  const { slug } = useParams()
  const navigate = useNavigate()
  const guide = readGuide(slug)

  if (!guide) return <NotFoundPage />

  const path = `/guides/${guide.slug}`
  const url = `${SITE_URL}${path}`
  const related = relatedGuides(guide)
  const { name: authorName, org: authorOrg } = authorParts(guide.author)
  const hasToc = guide.toc.length > 0

  const jsonLd = graph(
    organizationSchema,
    websiteSchema,
    articleSchema(guide, url),
    breadcrumbSchema(
      [
        { name: 'Home', path: '/' },
        { name: 'Guides', path: '/guides' },
        { name: guide.title, path },
      ],
      url
    ),
    guide.faq.length > 0 ? faqSchema(guide.faq, url) : null
  )

  // Links inside the Markdown body are plain <a> tags. Route internal ones
  // through React Router so moving between guides does not reload the page.
  function onBodyClick(event) {
    const anchor = event.target.closest('a')
    if (!anchor || event.defaultPrevented || event.button !== 0) return
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return
    const href = anchor.getAttribute('href') || ''
    if (!href.startsWith('/') || href.startsWith('//') || anchor.target) return
    event.preventDefault()
    navigate(href)
    window.scrollTo(0, 0)
  }

  return (
    <div className="min-h-screen bg-cream-200 flex flex-col">
      <Seo
        title={guide.seoTitle || `${guide.title} | postd.uk`}
        description={guide.description}
        path={path}
        type="article"
        jsonLd={jsonLd}
        publishedTime={guide.date}
        modifiedTime={guide.updated ?? guide.date}
      />
      <SiteHeader />

      <main className="flex-1">
        {guide.scheduled && (
          <div className="bg-honey-400 text-navy-900 text-sm font-semibold text-center px-4 py-2">
            Preview only: scheduled for {formatDate(guide.date)} and not live yet.
          </div>
        )}

        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
          <nav aria-label="Breadcrumb" className="text-sm text-slate-500 mb-6">
            <ol className="flex flex-wrap items-center gap-1.5 pl-0 [&>li]:mb-0">
              <li><Link to="/" className="hover:text-amber-700 transition-colors">Home</Link></li>
              <li aria-hidden="true">/</li>
              <li><Link to="/guides" className="hover:text-amber-700 transition-colors">Guides</Link></li>
              <li aria-hidden="true">/</li>
              <li aria-current="page" className="text-navy-700 font-medium line-clamp-1">{guide.title}</li>
            </ol>
          </nav>

          <div className="lg:grid lg:grid-cols-12 lg:gap-12">
            <article className="lg:col-span-8 min-w-0">
              <header className="mb-8">
                <a
                  href={`/guides#${guide.pillar}`}
                  className="inline-block text-amber-700 text-xs font-bold uppercase tracking-widest mb-4 hover:text-amber-800"
                >
                  {guide.pillarLabel}
                </a>
                <h1
                  className="font-display font-black text-navy-800 mb-5 text-balance"
                  style={{ fontSize: 'clamp(2rem, 4.5vw, 3rem)', lineHeight: '1.1' }}
                >
                  {guide.title}
                </h1>
                <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500">
                  <span>
                    By <span className="font-semibold text-navy-700">{authorName}</span>, {authorOrg}
                  </span>
                  <span aria-hidden="true">·</span>
                  <span>
                    Published <time dateTime={guide.date}>{formatDate(guide.date)}</time>
                  </span>
                  {guide.updated && (
                    <>
                      <span aria-hidden="true">·</span>
                      <span>
                        Last reviewed <time dateTime={guide.updated}>{formatDate(guide.updated)}</time>
                      </span>
                    </>
                  )}
                  <span aria-hidden="true">·</span>
                  <span>{guide.readingMinutes} min read</span>
                </p>
              </header>

              {hasToc && <TableOfContents toc={guide.toc} variant="inline" />}

              <div
                className="guide-prose"
                onClick={onBodyClick}
                dangerouslySetInnerHTML={{ __html: guide.html }}
              />

              {guide.faq.length > 0 && (
                <section aria-labelledby="guide-faq" className="mt-12">
                  <h2 id="guide-faq" className="font-display font-black text-2xl sm:text-3xl text-navy-800 mb-5">
                    Frequently asked questions
                  </h2>
                  <div className="space-y-3">
                    {guide.faq.map((item) => (
                      <details key={item.q} className="group bg-white rounded-2xl border border-cream-300">
                        <summary className="flex items-center justify-between gap-4 cursor-pointer list-none px-5 py-4 min-h-[56px] [&::-webkit-details-marker]:hidden">
                          <h3 className="font-display font-bold text-base sm:text-lg text-navy-800">{item.q}</h3>
                          <ChevronDown className="w-5 h-5 text-amber-500 flex-shrink-0 transition-transform duration-200 group-open:rotate-180" aria-hidden="true" />
                        </summary>
                        <p
                          className="px-5 pb-5 -mt-1 text-slate-600 leading-relaxed guide-faq-answer"
                          dangerouslySetInnerHTML={{ __html: item.answerHtml }}
                        />
                      </details>
                    ))}
                  </div>
                </section>
              )}

              <div className="mt-12 flex items-start gap-4 bg-white rounded-2xl border border-cream-300 p-5 sm:p-6">
                <div className="w-12 h-12 rounded-2xl bg-navy-800 text-white font-display font-black text-lg flex items-center justify-center flex-shrink-0" aria-hidden="true">
                  {authorName.charAt(0)}
                </div>
                <div>
                  <p className="font-display font-bold text-navy-800">Written by {authorName}, {authorOrg}</p>
                  <p className="text-sm text-slate-600 mt-1 leading-relaxed">
                    {authorName} runs{' '}
                    <a href="https://dijitul.uk" className="text-amber-700 hover:text-amber-800 underline underline-offset-2">dijitul</a>,
                    the Mansfield digital agency that builds and runs postd.uk.
                  </p>
                </div>
              </div>

              <div className="mt-10">
                <GuideCta slug={guide.slug} />
              </div>
            </article>

            <aside className="hidden lg:block lg:col-span-4">
              <div className="sticky top-24 space-y-8">
                {hasToc && <TableOfContents toc={guide.toc} />}
                <div className="bg-white rounded-2xl border border-cream-300 p-5">
                  <p className="font-display font-bold text-navy-800 mb-2">Let postd.uk post for you</p>
                  <p className="text-sm text-slate-600 mb-4">
                    Posts written from your website and reviews, published to all four platforms.
                  </p>
                  <Link
                    to={`/register?utm_source=postd.uk&utm_medium=guide-sidebar&utm_campaign=${encodeURIComponent(guide.slug)}`}
                    className="inline-flex items-center gap-2 bg-amber-500 text-white text-sm font-bold px-4 py-2.5 rounded-xl hover:bg-amber-700 transition-colors"
                  >
                    Start free trial
                    <ArrowRight className="w-4 h-4" aria-hidden="true" />
                  </Link>
                </div>
              </div>
            </aside>
          </div>

          {related.length > 0 && (
            <section aria-labelledby="read-next" className="mt-16">
              <h2 id="read-next" className="font-display font-black text-2xl text-navy-800 mb-5">
                Read next
              </h2>
              <p className="text-slate-600 -mt-3 mb-5">More on {guide.pillarLabel}.</p>
              <ul className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 pl-0 [&>li]:mb-0">
                {related.map((item) => (
                  <li key={item.slug}>
                    <Link
                      to={`/guides/${item.slug}`}
                      className="group flex flex-col h-full bg-white rounded-2xl p-5 border border-cream-300 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200"
                    >
                      <span className="font-display font-bold text-navy-800 group-hover:text-amber-700 transition-colors mb-2">
                        {item.title}
                      </span>
                      <span className="text-sm text-slate-600 flex-1">{item.description}</span>
                    </Link>
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>
      </main>

      <SiteFooter />
    </div>
  )
}
