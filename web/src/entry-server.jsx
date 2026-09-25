/**
 * Server entry used only by the build-time prerender (scripts/prerender.mjs).
 * It renders a public route to an HTML string plus its <head> tags. Nothing
 * here runs in production at request time: nginx serves the resulting files.
 */
import { Writable } from 'node:stream'
import React from 'react'
import { renderToPipeableStream } from 'react-dom/server'
import { StaticRouter } from 'react-router-dom/server'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import App from './App.jsx'
import { HeadProvider, renderHead } from './lib/head.jsx'
import { guides, loadGuide } from './lib/guides.js'

export { guides }

function renderToString(element) {
  return new Promise((resolve, reject) => {
    let html = ''
    const sink = new Writable({
      write(chunk, _encoding, callback) {
        html += chunk.toString()
        callback()
      },
      final(callback) {
        resolve(html)
        callback()
      },
    })

    const stream = renderToPipeableStream(element, {
      // Wait for every lazy route and Suspense boundary, so crawlers get the
      // whole page and not a loading spinner.
      onAllReady() {
        stream.pipe(sink)
      },
      onShellError: reject,
      onError(error) {
        reject(error)
      },
    })
  })
}

export async function render(url) {
  const guideMatch = url.match(/^\/guides\/([^/]+)$/)
  if (guideMatch) await loadGuide(guideMatch[1])

  const collector = { head: null }
  const queryClient = new QueryClient()

  const html = await renderToString(
    <React.StrictMode>
      <QueryClientProvider client={queryClient}>
        <StaticRouter location={url}>
          <HeadProvider collector={collector}>
            <App />
          </HeadProvider>
        </StaticRouter>
      </QueryClientProvider>
    </React.StrictMode>
  )

  return { html, head: renderHead(collector.head) }
}
