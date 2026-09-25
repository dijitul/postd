import { ChevronDown } from 'lucide-react'
import { Seo } from '../../lib/head.jsx'
import {
  SITE_URL,
  organizationSchema,
  websiteSchema,
  softwareSchema,
  faqSchema,
  graph,
} from '../../lib/site.js'
import { homeFaq } from './faq.js'

/** Head tags and structured data for the home page. */
export function HomeSeo() {
  return (
    <Seo
      title="AI Social Media Automation for UK Small Businesses | postd.uk"
      description="postd.uk writes your social media posts from your website and Google reviews, then publishes them to Google Business Profile, Facebook, LinkedIn and X."
      path="/"
      jsonLd={graph(
        organizationSchema,
        websiteSchema,
        softwareSchema,
        faqSchema(homeFaq, `${SITE_URL}/`)
      )}
    />
  )
}

/**
 * Visible questions and answers. Native <details> so every answer is in the
 * static HTML and still opens and closes before the JavaScript has loaded.
 */
export default function HomeFaq() {
  return (
    <section id="faq" className="py-20 sm:py-28 scroll-mt-20" aria-labelledby="faq-heading">
      <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <span className="inline-block text-amber-500 text-sm font-bold uppercase tracking-widest mb-4">Questions</span>
          <h2 id="faq-heading" className="font-display font-black text-navy-800 text-3xl sm:text-4xl lg:text-5xl mb-4 text-balance">
            Questions about postd.uk
          </h2>
          <p className="text-lg text-slate-500 text-pretty">
            Straight answers about what postd.uk does, what it does not do, and who it is for.
          </p>
        </div>

        <div className="space-y-3">
          {homeFaq.map((item) => (
            <details
              key={item.q}
              className="group bg-white rounded-2xl border border-cream-300 open:shadow-md transition-shadow"
            >
              <summary className="flex items-center justify-between gap-4 cursor-pointer list-none px-5 sm:px-6 py-4 sm:py-5 min-h-[56px] [&::-webkit-details-marker]:hidden">
                <h3 className="font-display font-bold text-base sm:text-lg text-navy-800">{item.q}</h3>
                <ChevronDown className="w-5 h-5 text-amber-500 flex-shrink-0 transition-transform duration-200 group-open:rotate-180" aria-hidden="true" />
              </summary>
              <p className="px-5 sm:px-6 pb-5 -mt-1 text-slate-600 leading-relaxed">{item.a}</p>
            </details>
          ))}
        </div>
      </div>
    </section>
  )
}
