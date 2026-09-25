import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'
import { fileURLToPath, URL } from 'node:url'
import guidesPlugin from './plugins/guides.js'

// App routes are client-rendered from the app.html shell; everything else is
// prerendered to static HTML by scripts/prerender.mjs. Keep this list in step
// with the app location block in scripts/nginx-postd.uk.conf.
const APP_ROUTES = /^\/(dashboard|posts|inbox|platforms|photos|settings|billing|admin|onboarding|auth)(\/|$)/

const DEFAULT_HEAD = [
  '<title>postd.uk</title>',
  '<meta name="description" content="Automated social media posting for UK small businesses." />',
].join('\n    ')

/**
 * The HTML template carries <!--app-head--> and <!--app-html--> markers for
 * the prerender. This plugin:
 *  - in dev, fills the head marker with a default title;
 *  - in build, emits app.html: the empty SPA shell (noindex) that nginx and
 *    the service worker serve for app routes such as /dashboard. It is
 *    emitted during the bundle so the service worker can precache it.
 */
function spaShell() {
  return {
    name: 'postd-spa-shell',
    enforce: 'post',
    transformIndexHtml: {
      order: 'post',
      handler(html, ctx) {
        if (ctx.server) return html.replace('<!--app-head-->', DEFAULT_HEAD)
        return html
      },
    },
    generateBundle(_options, bundle) {
      const index = bundle['index.html']
      if (!index) return
      const shell = String(index.source)
        .replace('<!--app-head-->', `${DEFAULT_HEAD}\n    <meta name="robots" content="noindex, nofollow" />`)
        .replace('<!--app-html-->', '')
      this.emitFile({ type: 'asset', fileName: 'app.html', source: shell })
    },
  }
}

export default defineConfig(({ isSsrBuild }) => ({
  plugins: [
    react(),
    guidesPlugin({
      contentDir: fileURLToPath(new URL('./content/guides', import.meta.url)),
    }),
    // The SSR build only feeds the prerender script; it needs no service
    // worker or shell.
    ...(isSsrBuild
      ? []
      : [
          spaShell(),
          VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.ico', 'apple-touch-icon.png', 'brand/**/*'],
            manifest: {
              name: 'postd.uk',
              short_name: 'postd',
              description: 'All your posts. One hive. Automated social media for UK small businesses.',
              theme_color: '#E07B30',
              background_color: '#F9F5EE',
              display: 'standalone',
              orientation: 'portrait',
              scope: '/',
              start_url: '/',
              icons: [
                {
                  src: '/app-icons/icon-192.png',
                  sizes: '192x192',
                  type: 'image/png'
                },
                {
                  src: '/app-icons/icon-512.png',
                  sizes: '512x512',
                  type: 'image/png'
                },
                {
                  src: '/app-icons/icon-512-maskable.png',
                  sizes: '512x512',
                  type: 'image/png',
                  purpose: 'maskable'
                }
              ]
            },
            workbox: {
              // Prerendered pages (home, guides, legal) are deliberately NOT
              // precached: they change daily as guides publish and must come
              // from the network. Only the app shell is precached.
              globPatterns: ['**/*.{js,css,ico,png,svg,woff,woff2}', 'app.html'],
              globIgnores: ['og-image.png'],
              // Offline and repeat visits to app routes get the SPA shell.
              // Public pages are never answered with the shell, so a cached
              // SW can never hide a prerendered page or a new guide.
              navigateFallback: '/app.html',
              navigateFallbackAllowlist: [APP_ROUTES],
              runtimeCaching: [
                {
                  urlPattern: /^https:\/\/api\.postd\.uk\/.*/i,
                  handler: 'NetworkFirst',
                  options: {
                    cacheName: 'api-cache',
                    expiration: {
                      maxEntries: 50,
                      maxAgeSeconds: 300
                    }
                  }
                },
                {
                  urlPattern: /^https:\/\/fonts\.googleapis\.com\/.*/i,
                  handler: 'CacheFirst',
                  options: {
                    cacheName: 'google-fonts-cache',
                    expiration: {
                      maxEntries: 10,
                      maxAgeSeconds: 60 * 60 * 24 * 365
                    }
                  }
                }
              ]
            }
          }),
        ]),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  server: {
    port: 5173,
    host: true
  }
}))
