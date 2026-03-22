import { Link } from 'react-router-dom'
import Logo from '../../components/ui/Logo.jsx'

export default function PrivacyPage() {
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
            <h1 className="font-display font-bold text-3xl text-navy-800 mb-3">Privacy Policy</h1>
            <p className="text-sm text-slate-400">Last updated: 22 March 2026 &nbsp;&middot;&nbsp; Effective: 22 March 2026</p>
          </div>

          <div className="prose prose-slate max-w-none text-slate-600 leading-relaxed space-y-8">

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">1. Introduction</h2>
              <p>This Privacy Policy explains how Dijitul (&ldquo;we&rdquo;, &ldquo;us&rdquo;, &ldquo;our&rdquo;), the company behind postd.uk, collects, uses, stores, and protects your personal data when you use our Service at <a href="https://postd.uk" className="text-amber-600 hover:text-amber-700">https://postd.uk</a>.</p>
              <p className="mt-3">We are committed to handling your personal data responsibly and in full compliance with the UK General Data Protection Regulation (UK GDPR) and the Data Protection Act 2018. For the purposes of UK GDPR, Dijitul is the data controller.</p>
              <div className="mt-4 bg-cream-100 rounded-xl p-4 text-sm space-y-1">
                <p><strong>Company:</strong> Dijitul</p>
                <p><strong>Registered in:</strong> England and Wales</p>
                <p><strong>Address:</strong> Mansfield, Nottinghamshire, England</p>
                <p><strong>Email:</strong> <a href="mailto:hello@postd.uk" className="text-amber-600 hover:text-amber-700">hello@postd.uk</a></p>
              </div>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">2. Data We Collect</h2>
              <p>We collect the following categories of personal and business data when you use the Service:</p>
              <div className="mt-4 space-y-4">
                <div>
                  <p className="font-semibold text-navy-800">Account and Identity Data</p>
                  <p className="text-sm mt-1">Your full name, email address, business name, business description, and billing address.</p>
                </div>
                <div>
                  <p className="font-semibold text-navy-800">Payment Data</p>
                  <p className="text-sm mt-1">Billing name and address. Payment card details are collected and processed directly by Stripe. We do not store full card numbers or CVV codes on our systems.</p>
                </div>
                <div>
                  <p className="font-semibold text-navy-800">Social Media Access Tokens</p>
                  <p className="text-sm mt-1">When you connect a social media account (Facebook, Instagram, Twitter/X, LinkedIn, TikTok, or Google Business Profile), we receive and store an access token issued by that platform. This token allows us to publish content to your account on your behalf.</p>
                </div>
                <div>
                  <p className="font-semibold text-navy-800">Usage and Service Data</p>
                  <p className="text-sm mt-1">How you use the Service, including features accessed, content scheduled, and actions taken. Log data including IP address, browser type, pages visited, and timestamps.</p>
                </div>
                <div>
                  <p className="font-semibold text-navy-800">Communications Data</p>
                  <p className="text-sm mt-1">Messages you send us via email or through any support channels.</p>
                </div>
              </div>
              <p className="mt-4 text-sm">We do not knowingly collect data from children under 18. If you believe a child has provided us with personal data, please contact us at <a href="mailto:hello@postd.uk" className="text-amber-600 hover:text-amber-700">hello@postd.uk</a> and we will delete it promptly.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">3. How We Use Your Data</h2>
              <p>We use your personal data only for the purposes set out below and only where we have a lawful basis for doing so under UK GDPR.</p>
              <div className="mt-4 overflow-x-auto">
                <table className="w-full text-sm border-collapse">
                  <thead>
                    <tr className="bg-cream-100">
                      <th className="text-left p-3 font-semibold text-navy-800 rounded-tl-lg">Purpose</th>
                      <th className="text-left p-3 font-semibold text-navy-800 rounded-tr-lg">Lawful Basis</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-cream-200">
                    {[
                      ['Creating and managing your account', 'Performance of a contract'],
                      ['Delivering the Service (scheduling and posting content)', 'Performance of a contract'],
                      ['Processing subscription payments', 'Performance of a contract'],
                      ['Generating AI-assisted content suggestions', 'Performance of a contract'],
                      ['Sending service notifications and updates', 'Performance of a contract / Legitimate interests'],
                      ['Improving and developing the Service', 'Legitimate interests'],
                      ['Complying with legal obligations', 'Legal obligation'],
                      ['Marketing communications (where opted in)', 'Consent'],
                    ].map(([purpose, basis]) => (
                      <tr key={purpose} className="hover:bg-cream-50">
                        <td className="p-3 text-slate-600">{purpose}</td>
                        <td className="p-3 text-slate-500">{basis}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">4. Social Media Access Tokens</h2>
              <p>Access tokens are stored securely using industry-standard encryption, on servers located in the United Kingdom and/or the European Union. They are used solely for the purpose of publishing content to your social media accounts as directed by you through the Service.</p>
              <p className="mt-3">We do not share, sell, or transfer your access tokens to any third party, except where necessary to make authorised API calls to the relevant platform on your behalf. Tokens are deleted from our systems when you disconnect a social media account or delete your account.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">5. AI Content Generation and OpenAI</h2>
              <p>The Service uses OpenAI&apos;s API to power AI content generation. When you use this feature, we send your business description and content preferences to OpenAI to generate post suggestions.</p>
              <p className="mt-3 font-semibold text-navy-800">We do not send your name, email address, billing details, social media tokens, or any other personal data to OpenAI.</p>
              <p className="mt-3">Data sent via the API is not used by OpenAI to train their models under their current API terms. Please refer to <a href="https://openai.com/policies/privacy-policy" target="_blank" rel="noopener noreferrer" className="text-amber-600 hover:text-amber-700">OpenAI&apos;s Privacy Policy</a> for further information.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">6. Cookies</h2>
              <p>We use cookies and similar tracking technologies to enable the Service to function correctly and to improve your experience.</p>
              <div className="mt-4 space-y-4">
                <div className="bg-cream-50 rounded-xl p-4">
                  <p className="font-semibold text-navy-800 text-sm">Strictly Necessary Cookies</p>
                  <p className="text-sm text-slate-500 mt-1">Essential for the Service to operate, including session cookies that keep you logged in. These do not require your consent.</p>
                </div>
                <div className="bg-cream-50 rounded-xl p-4">
                  <p className="font-semibold text-navy-800 text-sm">Analytics Cookies (Google Analytics)</p>
                  <p className="text-sm text-slate-500 mt-1">We use Google Analytics to collect anonymised information about how visitors use the Service. IP anonymisation is enabled. You can opt out at any time by installing the <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener noreferrer" className="text-amber-600 hover:text-amber-700">Google Analytics Opt-out Browser Add-on</a>.</p>
                </div>
                <div className="bg-cream-50 rounded-xl p-4">
                  <p className="font-semibold text-navy-800 text-sm">Preference Cookies</p>
                  <p className="text-sm text-slate-500 mt-1">These remember your settings and choices to provide a more personalised experience.</p>
                </div>
              </div>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">7. Third-Party Data Sharing</h2>
              <p>We do not sell, rent, or trade your personal data to any third party. We share your data only with the following service providers, and only to the extent necessary for them to provide their services to us:</p>
              <div className="mt-4 overflow-x-auto">
                <table className="w-full text-sm border-collapse">
                  <thead>
                    <tr className="bg-cream-100">
                      <th className="text-left p-3 font-semibold text-navy-800">Third Party</th>
                      <th className="text-left p-3 font-semibold text-navy-800">Purpose</th>
                      <th className="text-left p-3 font-semibold text-navy-800">Location</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-cream-200">
                    {[
                      ['Stripe', 'Payment processing', 'USA (Data Privacy Framework)'],
                      ['OpenAI', 'AI content generation', 'USA (Standard Contractual Clauses)'],
                      ['Google Analytics', 'Service usage analytics', 'USA (Data Privacy Framework)'],
                      ['Meta, X, LinkedIn, TikTok, Google', 'Publishing content on your behalf', 'Various'],
                      ['Hosting / Infrastructure', 'Data storage and service delivery', 'UK/EU'],
                    ].map(([party, purpose, location]) => (
                      <tr key={party} className="hover:bg-cream-50">
                        <td className="p-3 font-medium text-navy-800">{party}</td>
                        <td className="p-3 text-slate-600">{purpose}</td>
                        <td className="p-3 text-slate-500">{location}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">8. International Data Transfers</h2>
              <p>Some of our third-party service providers are based in the United States. Transfers of your personal data to these providers are conducted using appropriate UK GDPR safeguards, including the UK International Data Transfer Agreement (IDTA) and Standard Contractual Clauses.</p>
              <p className="mt-3">Your core account data, social media access tokens, and posted content data are stored on servers located within the United Kingdom and/or the European Union.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">9. Data Retention</h2>
              <div className="mt-2 overflow-x-auto">
                <table className="w-full text-sm border-collapse">
                  <thead>
                    <tr className="bg-cream-100">
                      <th className="text-left p-3 font-semibold text-navy-800">Data Category</th>
                      <th className="text-left p-3 font-semibold text-navy-800">Retention Period</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-cream-200">
                    {[
                      ['Account data (name, email, business info)', 'Duration of account, plus 2 years after closure'],
                      ['Social media access tokens', 'Deleted upon account deletion or disconnection'],
                      ['Billing and transaction records', '7 years (UK tax and accounting law)'],
                      ['Usage and log data', '12 months from collection'],
                      ['Support and communications', '2 years from last correspondence'],
                    ].map(([category, period]) => (
                      <tr key={category} className="hover:bg-cream-50">
                        <td className="p-3 text-slate-600">{category}</td>
                        <td className="p-3 text-slate-500">{period}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">10. Your Rights Under UK GDPR</h2>
              <p>Under UK GDPR, you have the following rights in relation to your personal data:</p>
              <div className="mt-4 grid gap-3">
                {[
                  ['Right of Access', 'Request a copy of the personal data we hold about you. We will respond within 30 days.'],
                  ['Right to Rectification', 'Request that we correct any inaccurate or incomplete personal data.'],
                  ['Right to Erasure', 'Request that we delete your personal data, subject to any legal obligations to retain it.'],
                  ['Right to Restrict Processing', 'Request that we restrict processing of your data in certain circumstances.'],
                  ['Right to Data Portability', 'Receive your personal data in a structured, machine-readable format.'],
                  ['Right to Object', 'Object to processing based on legitimate interests, or to direct marketing at any time.'],
                  ['Right to Withdraw Consent', 'Where consent is the basis for processing, withdraw it at any time.'],
                ].map(([right, description]) => (
                  <div key={right} className="bg-cream-50 rounded-xl p-4">
                    <p className="font-semibold text-navy-800 text-sm">{right}</p>
                    <p className="text-sm text-slate-500 mt-1">{description}</p>
                  </div>
                ))}
              </div>
              <p className="mt-4">To exercise any of these rights, contact us at <a href="mailto:hello@postd.uk" className="text-amber-600 hover:text-amber-700">hello@postd.uk</a>.</p>
              <p className="mt-3">You also have the right to lodge a complaint with the UK Information Commissioner&apos;s Office (ICO): <a href="https://ico.org.uk" target="_blank" rel="noopener noreferrer" className="text-amber-600 hover:text-amber-700">ico.org.uk</a> &mdash; 0303 123 1113.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">11. Data Security</h2>
              <p>We implement appropriate technical and organisational measures to protect your data, including TLS encryption of data in transit, encryption of sensitive data at rest (including social media tokens), strict access controls, and secure data storage within UK/EU regions.</p>
              <p className="mt-3">In the event of a data breach likely to result in a risk to your rights and freedoms, we will notify you and the ICO as required by UK GDPR.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">12. Changes to This Privacy Policy</h2>
              <p>We may update this Privacy Policy from time to time. When we make material changes, we will notify you by email and/or via a prominent notice within the Service at least 14 days before the changes take effect. Your continued use of the Service after the effective date constitutes acceptance of the revised policy.</p>
            </section>

            <section>
              <h2 className="font-display font-bold text-xl text-navy-800 mb-3">13. Contact Us</h2>
              <div className="bg-cream-100 rounded-xl p-4 text-sm space-y-1">
                <p><strong>Email:</strong> <a href="mailto:hello@postd.uk" className="text-amber-600 hover:text-amber-700">hello@postd.uk</a></p>
                <p><strong>Website:</strong> <a href="https://postd.uk" className="text-amber-600 hover:text-amber-700">https://postd.uk</a></p>
                <p><strong>Post:</strong> Dijitul, Mansfield, Nottinghamshire, England</p>
              </div>
              <p className="mt-3 text-sm">We aim to respond to all enquiries within 5 working days.</p>
            </section>

          </div>

          <div className="mt-10 pt-8 border-t border-cream-300 flex flex-wrap gap-4 text-sm text-slate-400">
            <Link to="/terms" className="underline hover:text-slate-600 transition-colors">Terms of Service</Link>
            <Link to="/" className="underline hover:text-slate-600 transition-colors">Back to postd.uk</Link>
          </div>
        </div>
      </main>
    </div>
  )
}
