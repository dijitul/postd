import React from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import App from './App.jsx'
import './styles/globals.css'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 2,      // 2 minutes
      gcTime: 1000 * 60 * 10,        // 10 minutes
      retry: 1,
      refetchOnWindowFocus: false
    },
    mutations: {
      retry: 0
    }
  }
})

const app = (
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <App />
      </BrowserRouter>
    </QueryClientProvider>
  </React.StrictMode>
)

// When a deploy ships a new service worker it takes over straight away
// (registerType autoUpdate), but the page already on screen was served by the
// old one and stays stale until the next visit. Reload once when control
// changes hands so the new version shows immediately. Skipped on a first
// visit, where there was no previous worker and nothing is stale.
if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
  let reloading = false
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (reloading) return
    reloading = true
    window.location.reload()
  })
}

const rootElement = document.getElementById('root')

// Public pages (home, legal, guides, 404) arrive as prerendered HTML and are
// hydrated. App routes are served the empty shell (app.html) and rendered
// entirely in the browser, as before.
const currentRoute = window.location.pathname.replace(/(.)\/+$/, '$1')
const renderedRoute = rootElement.dataset.route

if (rootElement.firstElementChild && (renderedRoute === '*' || renderedRoute === currentRoute)) {
  // A guide's body lives in its own chunk. Load it before hydrating so the
  // first render matches the static HTML instead of suspending mid-article.
  const guideMatch = window.location.pathname.match(/^\/guides\/([^/]+)\/?$/)
  const ready = guideMatch
    ? import('./lib/guides.js').then(({ loadGuide }) => loadGuide(decodeURIComponent(guideMatch[1])))
    : Promise.resolve()

  ready
    .catch(() => {})
    .then(() => ReactDOM.hydrateRoot(rootElement, app))
} else {
  // Prerendered HTML for a different route (see prerender.mjs) is thrown away
  // rather than hydrated against the wrong page.
  rootElement.replaceChildren()
  ReactDOM.createRoot(rootElement).render(app)
}
