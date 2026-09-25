/**
 * Serves web/dist locally with the same rules as scripts/nginx-postd.uk.conf,
 * so a production build can be checked before it ships:
 *
 *   real file            -> served as is
 *   /path                -> /path/index.html (prerendered page)
 *   app route            -> /app.html (SPA shell) with X-Robots-Tag noindex
 *   anything else        -> /404.html with a 404 status
 *   /path/ (trailing /)  -> 301 to /path
 *
 * Usage: npm run preview [-- --port 4173]
 */
import http from 'node:http'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', 'dist')
const portArg = process.argv.indexOf('--port')
const port = Number(portArg > -1 ? process.argv[portArg + 1] : process.env.PORT || 4173)
const APP_ROUTES = /^\/(dashboard|posts|inbox|platforms|settings|billing|admin|onboarding|auth)(\/|$)/

const TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.webmanifest': 'application/manifest+json',
  '.txt': 'text/plain; charset=utf-8',
  '.xml': 'application/xml; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2',
}

function isFile(p) {
  try {
    return fs.statSync(p).isFile()
  } catch {
    return false
  }
}

function send(res, status, file, extraHeaders = {}) {
  res.writeHead(status, {
    'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream',
    ...extraHeaders,
  })
  fs.createReadStream(file).pipe(res)
}

http
  .createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost')
    const pathname = decodeURIComponent(url.pathname)
    const safe = path.normalize(pathname).replace(/^(\.\.[/\\])+/, '')

    if (pathname.length > 1 && pathname.endsWith('/')) {
      res.writeHead(301, { Location: pathname.replace(/\/+$/, '') + url.search })
      return res.end()
    }
    if (APP_ROUTES.test(pathname)) {
      return send(res, 200, path.join(root, 'app.html'), { 'X-Robots-Tag': 'noindex, nofollow' })
    }
    const direct = path.join(root, safe)
    if (isFile(direct)) return send(res, 200, direct)
    const index = path.join(root, safe, 'index.html')
    if (isFile(index)) return send(res, 200, index)
    return send(res, 404, path.join(root, '404.html'))
  })
  .listen(port, () => {
    console.log(`Serving ${root} at http://localhost:${port}`)
  })
