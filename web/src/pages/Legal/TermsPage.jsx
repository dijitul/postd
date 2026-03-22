import { Link } from 'react-router-dom'
import Logo from '../../components/ui/Logo.jsx'

export default function TermsPage() {
  return (
    <div className="min-h-screen bg-cream-200">
      <header className="border-b border-cream-300 bg-white/80 backdrop-blur-sm sticky top-0 z-10">
        <div className="max-w-3xl mx-auto px-6 py-4 flex items-center justify-between">
          <Link to="/"><Logo size="md" /></Link>
          <Link to="/register" className="text-sm font-semibold text-amber-600 hover:text-amber-700 transition-colors">
            Start free trial
          </Link>
        </div>
      </header>

      <main className="max-w-3xl mx-auto px-6 py-12">
        <div className="bg-white rounded-3xl p-8 sm:p-12 border border-cream-300" style={{ boxShadow: '0 8px 32px rgb(30 45 74 / 0.08)' }}>

          <div className="mb-10">
            <p className="text-xs font-semibold text-amber-600 uppercase tracking-widest mb-2">Legal</p>
            <h1 className="font-display font-bold text-3xl text-navy-800 mb-3">Terms of Service</h1>
            <p className="text-sm text-slate-400">Last updated: 22 March 2026 &nbsp;&middot;&nbsp; Effective: 22 March 2026</p>
          </div>

          <div className="prose prose-slate max-w-none text-slate-600 leading-relaxed space-y-8">

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">1. Introduction and Agreement</h2>
              <p>These Terms of Service (&ldquo;Terms&rdquo;) govern your use of postd.uk (&ldquo;the Service&rdquo;), a social media automation platform operated by Dijitul (&ldquo;we&rdquo;, &ldquo;us&rdquo;, &ldquo;our&rdquo;), a company registered in England and Wales, with its principal place of business in Mansfield, Nottinghamshire.</p>
              <p className="mt-3">By registering for an account, starting a free trial, or using any part of the Service, you (&ldquo;the User&rdquo;, &ldquo;you&rdquo;, &ldquo;your&rdquo;) agree to be bound by these Terms in full. If you do not agree with any part of these Terms, you must not use the Service.</p>
              <p className="mt-3">These Terms form a legally binding contract between you and Dijitul. We recommend you read them carefully and retain a copy for your records.</p>
              <p className="mt-3">We may update these Terms from time to time. We will notify you of material changes by email or via an in-app notification at least 14 days before the changes take effect. Continued use of the Service after that date constitutes acceptance of the revised Terms.</p>
              <p className="mt-3">If you have any questions about these Terms, please contact us at <a href="mailto:hello@postd.uk" className="text-amber-600 hover:text-amber-700">hello@postd.uk</a>.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">2. The Service</h2>
              <p>postd.uk is a software-as-a-service (SaaS) product that enables users to schedule, automate, and publish content to their social media accounts, including but not limited to Facebook, Instagram, Twitter/X, LinkedIn, TikTok, and Google Business Profile (&ldquo;Connected Platforms&rdquo;).</p>
              <p className="mt-3">The Service includes an AI-assisted content generation feature that suggests or drafts social media posts on your behalf, based on information you provide about your business.</p>
              <p className="mt-3">We reserve the right to modify, suspend, or discontinue any part of the Service at any time. Where possible, we will provide advance notice of significant changes. We will not be liable to you or any third party for any modification, suspension, or discontinuation of the Service.</p>
              <p className="mt-3">The Service is intended for use by businesses and sole traders operating lawfully in the United Kingdom. You must be at least 18 years of age and have the legal authority to enter into these Terms.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">3. Account Registration and Security</h2>
              <p>To use the Service, you must register for an account by providing accurate and complete information. You are responsible for maintaining the confidentiality of your account credentials and for all activity that occurs under your account. You must notify us immediately at <a href="mailto:hello@postd.uk" className="text-amber-600 hover:text-amber-700">hello@postd.uk</a> if you suspect any unauthorised access.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">4. Social Media Account Connections and Permission to Post</h2>
              <p>By connecting your social media accounts to postd.uk, you expressly grant us the authority to access those accounts and to publish content to them on your behalf, in accordance with the scheduling and instructions you provide through the Service.</p>
              <p className="mt-3">This authorisation is granted via the official authentication mechanisms provided by each Connected Platform (for example, OAuth tokens). We will only use this access for the purposes of delivering the Service to you.</p>
              <p className="mt-3">You confirm that you are the authorised owner or administrator of any social media accounts you connect, and that you have the right to grant us access. You may revoke our access at any time via the Service settings or directly through the relevant platform.</p>
              <p className="mt-3">We do not guarantee uninterrupted access to Connected Platforms. Social media platforms may change their APIs, policies, or authentication requirements at any time and without notice to us.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">5. AI-Generated Content &mdash; User Responsibility</h2>
              <p>The Service includes an AI content generation feature that creates suggested social media posts based on your business description and instructions. This content is provided for your review and approval only.</p>
              <p className="mt-3 font-semibold text-navy-800">Before any AI-generated content is published to your social media accounts, you are responsible for reviewing it, editing it as necessary, and approving it. By approving and scheduling content through the Service, you take full responsibility for that content and its publication.</p>
              <p className="mt-3">We do not warrant that AI-generated content will be accurate, appropriate, suitable for your industry, or compliant with applicable laws or regulations.</p>
              <p className="mt-3 font-semibold text-navy-800">Once you have approved content and it has been published to your social media accounts, postd.uk accepts no responsibility or liability for that content. You are the publisher of record and are solely responsible for all content posted to your accounts via the Service.</p>
              <p className="mt-3">You must not approve or schedule any content that is false or misleading, infringes third-party intellectual property rights, is defamatory or hateful, is illegal, breaches ASA or CMA standards, violates any Connected Platform&apos;s terms, or constitutes spam.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">6. Social Media Platform Compliance and Account Risk</h2>
              <p>Each Connected Platform has its own terms of service, community standards, and usage policies. You are solely responsible for ensuring that your use of the Service complies with the terms and policies of each platform.</p>
              <p className="mt-3 font-semibold text-navy-800">postd.uk is not responsible for any suspension, restriction, ban, penalty, or other action taken by any social media platform against your account, whether or not that action arises from content posted via the Service.</p>
              <p className="mt-3">You acknowledge that automated posting carries inherent risk on some platforms and that platform policies regarding automation may change. You accept this risk as part of using the Service.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">7. Limitation of Liability</h2>
              <p className="font-semibold text-navy-800">postd.uk and Dijitul will not be liable for any loss of business, revenue, profits, reputation, data, or customers, or any other indirect or consequential loss arising from your use of the Service, including but not limited to losses arising from: content published via the Service; the suspension or banning of any social media account; errors in AI-generated content; service downtime; or changes to Connected Platform APIs or policies.</p>
              <p className="mt-3">Our total aggregate liability to you shall not exceed the total fees paid by you to us in the three months immediately preceding the event giving rise to the claim.</p>
              <p className="mt-3">Nothing in these Terms limits our liability for death or personal injury caused by our negligence, or for fraud or fraudulent misrepresentation.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">8. Acceptable Use Policy</h2>
              <p>You agree to use the Service only for lawful purposes. You must not use the Service to publish illegal content, harass or abuse others, spread disinformation, infringe intellectual property rights, distribute malware or spam, artificially inflate engagement metrics, circumvent platform security measures, or resell the Service without our prior written consent.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">9. Subscription Plans, Billing, and Payment</h2>
              <p>Access to the full features of the Service requires a paid subscription, starting from &pound;19 per month. We offer a 14-day free trial for new users with no payment required. At the end of the trial, your account will automatically convert to a paid subscription unless you cancel before the trial expires.</p>
              <p className="mt-3">Subscription fees are charged in advance on a recurring basis via Stripe. All prices are in GBP and exclusive of VAT unless stated otherwise. We reserve the right to change our subscription prices with at least 30 days&apos; written notice.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">10. Cancellation and Refund Policy</h2>
              <p>You may cancel your subscription at any time via your account settings or by contacting us at <a href="mailto:hello@postd.uk" className="text-amber-600 hover:text-amber-700">hello@postd.uk</a>. Upon cancellation, your subscription will remain active until the end of the current billing period.</p>
              <p className="mt-3 font-semibold text-navy-800">We do not offer refunds for any part of a subscription period that has already been paid for and used.</p>
              <p className="mt-3">If you cancel during the 14-day free trial, no charge will be made. In exceptional circumstances where a technical fault on our part has prevented you from using the Service for a significant period, we may at our sole discretion offer a partial credit.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">11. Intellectual Property</h2>
              <p>The Service, including its software, design, and branding, is owned by or licensed to Dijitul. You retain ownership of all content you create, upload, or input into the Service. By using the Service, you grant us a limited licence to store and process your content solely to deliver the Service to you.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">12. Termination</h2>
              <p>We reserve the right to suspend or terminate your account if you breach these Terms, breach the terms of any Connected Platform through use of the Service, engage in fraudulent or illegal activity, or fail to meet payment obligations following reasonable notice.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">13. Third-Party Services</h2>
              <p>The Service integrates with third-party platforms including Connected Platforms, Stripe for payment processing, and OpenAI for AI content generation. Your use of these services is subject to their respective terms. We are not responsible for the availability or actions of any third-party service.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">14. Governing Law and Disputes</h2>
              <p>These Terms and any dispute or claim arising out of or in connection with them shall be governed by and construed in accordance with the law of England and Wales. The courts of England and Wales shall have exclusive jurisdiction to settle any dispute.</p>
              <p className="mt-3">Before bringing any formal legal proceedings, we encourage you to contact us at <a href="mailto:hello@postd.uk" className="text-amber-600 hover:text-amber-700">hello@postd.uk</a> to attempt to resolve any dispute informally. We will make reasonable efforts to resolve complaints within 28 days of receipt.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">15. General Provisions</h2>
              <p>These Terms, together with our Privacy Policy, constitute the entire agreement between you and us regarding your use of the Service. If any provision of these Terms is found to be unlawful or unenforceable, the remaining provisions shall continue in full force. Formal notices should be sent to <a href="mailto:hello@postd.uk" className="text-amber-600 hover:text-amber-700">hello@postd.uk</a> or addressed to Dijitul, Mansfield, Nottinghamshire, England.</p>
            </section>

          </div>

          <div className="mt-10 pt-8 border-t border-cream-300 flex flex-wrap gap-4 text-sm text-slate-400">
            <Link to="/privacy" className="underline hover:text-slate-600 transition-colors">Privacy Policy</Link>
            <Link to="/" className="underline hover:text-slate-600 transition-colors">Back to postd.uk</Link>
          </div>
        </div>
      </main>
    </div>
  )
}
