import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { Cookie, X, ChevronDown, ChevronUp } from 'lucide-react'

const CONSENT_KEY = 'postd_cookie_consent'

function getStoredConsent() {
  try {
    const raw = localStorage.getItem(CONSENT_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function applyConsent(analytics) {
  if (typeof window.gtag === 'function') {
    window.gtag('consent', 'update', {
      analytics_storage: analytics ? 'granted' : 'denied',
      ad_storage: 'denied',
    })
  }
}

export default function CookieBanner() {
  const [visible, setVisible] = useState(false)
  const [showDetails, setShowDetails] = useState(false)

  useEffect(() => {
    const stored = getStoredConsent()
    if (!stored) {
      // Small delay so the page renders first
      const t = setTimeout(() => setVisible(true), 800)
      return () => clearTimeout(t)
    }
    // Re-apply consent on every page load
    applyConsent(stored.analytics)
  }, [])

  function saveConsent(analytics) {
    const consent = { analytics, savedAt: new Date().toISOString() }
    localStorage.setItem(CONSENT_KEY, JSON.stringify(consent))
    applyConsent(analytics)
    setVisible(false)
  }

  if (!visible) return null

  return (
    <div
      role="dialog"
      aria-label="Cookie preferences"
      className="fixed bottom-0 left-0 right-0 z-50 p-4 sm:p-6 animate-in slide-in-from-bottom duration-300"
    >
      <div
        className="max-w-3xl mx-auto bg-navy-800 text-white rounded-2xl p-5 sm:p-6 border border-white/10"
        style={{ boxShadow: '0 -4px 40px rgb(0 0 0 / 0.25)' }}
      >
        <div className="flex items-start gap-4">
          <div className="w-9 h-9 rounded-xl bg-amber-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
            <Cookie className="w-4 h-4 text-amber-400" />
          </div>

          <div className="flex-1 min-w-0">
            <h2 className="font-display font-bold text-base text-white mb-1">
              We use cookies
            </h2>
            <p className="text-sm text-white/60 leading-relaxed">
              We use essential cookies to keep the service running, and optional analytics cookies (Google Analytics) to understand how people use postd.uk so we can improve it. We never use cookies for advertising.{' '}
              <Link to="/privacy" className="text-amber-400 hover:text-amber-300 underline transition-colors">
                Privacy Policy
              </Link>
            </p>

            {/* Expandable detail */}
            <button
              onClick={() => setShowDetails(!showDetails)}
              className="flex items-center gap-1 text-xs text-white/40 hover:text-white/60 mt-2 transition-colors"
            >
              {showDetails ? <ChevronUp className="w-3 h-3" /> : <ChevronDown className="w-3 h-3" />}
              {showDetails ? 'Hide details' : 'What cookies do we use?'}
            </button>

            {showDetails && (
              <div className="mt-3 space-y-2">
                <div className="bg-white/5 rounded-xl p-3">
                  <div className="flex items-center justify-between mb-1">
                    <p className="text-xs font-semibold text-white">Essential cookies</p>
                    <span className="text-xs text-white/40 bg-white/10 px-2 py-0.5 rounded-full">Always on</span>
                  </div>
                  <p className="text-xs text-white/50">Session management, authentication, and security. The service cannot function without these.</p>
                </div>
                <div className="bg-white/5 rounded-xl p-3">
                  <div className="flex items-center justify-between mb-1">
                    <p className="text-xs font-semibold text-white">Analytics cookies</p>
                    <span className="text-xs text-amber-400/80 bg-amber-400/10 px-2 py-0.5 rounded-full">Optional</span>
                  </div>
                  <p className="text-xs text-white/50">Google Analytics (G-QLSYF2DZCL) collects anonymised usage data. IP addresses are anonymised. No personal data is shared.</p>
                </div>
              </div>
            )}
          </div>

          <button
            onClick={() => saveConsent(false)}
            className="p-1.5 rounded-lg text-white/30 hover:text-white/60 hover:bg-white/5 transition-colors flex-shrink-0"
            aria-label="Reject optional cookies and close"
            title="Reject optional cookies"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        <div className="flex flex-col sm:flex-row gap-2 mt-4 sm:mt-5">
          <button
            onClick={() => saveConsent(false)}
            className="flex-1 sm:flex-none px-4 py-2.5 rounded-xl text-sm font-semibold text-white/60 bg-white/5 hover:bg-white/10 transition-colors border border-white/10"
          >
            Reject optional
          </button>
          <button
            onClick={() => saveConsent(true)}
            className="flex-1 sm:flex-none px-5 py-2.5 rounded-xl text-sm font-bold text-navy-900 bg-amber-500 hover:bg-amber-400 transition-colors"
            style={{ boxShadow: '0 2px 12px rgb(224 123 48 / 0.3)' }}
          >
            Accept all cookies
          </button>
        </div>
      </div>
    </div>
  )
}
