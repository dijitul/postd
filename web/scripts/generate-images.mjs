/**
 * One-off generator for the raster brand assets, built from the SVGs in
 * web/public/brand. Run it again only when the brand changes:
 *
 *   npm run generate:images
 *
 * Writes (all committed, served from web/public):
 *   og-image.png               1200x630 social share card
 *   icons/icon-<size>.png      PWA and manifest icons
 *   icons/icon-512-maskable.png  full-bleed icon for Android adaptive masks
 *   apple-touch-icon.png       180x180, square, no transparency
 *   favicon.ico                16 and 32px PNGs in an ICO container
 *
 * Text is set in Syne and Inter from @fontsource. resvg only reads TrueType
 * and OpenType, so the WOFF files are unpacked to plain SFNT first.
 */
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import zlib from 'node:zlib'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import { Resvg } from '@resvg/resvg-js'

const require = createRequire(import.meta.url)
const webDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const publicDir = path.join(webDir, 'public')
const iconSvg = fs.readFileSync(path.join(publicDir, 'brand', 'icon.svg'), 'utf8')

const NAVY = '#1E2D4A'
const AMBER = '#E07B30'
const CREAM = '#F9F5EE'
const SLATE = '#475569'

// ── WOFF 1.0 to SFNT ─────────────────────────────────────────────────────────
function woffToSfnt(woff) {
  if (woff.toString('ascii', 0, 4) !== 'wOFF') throw new Error('Not a WOFF 1.0 file')
  const flavor = woff.readUInt32BE(4)
  const numTables = woff.readUInt16BE(12)
  const tables = []
  for (let i = 0; i < numTables; i++) {
    const at = 44 + i * 20
    const tag = woff.toString('ascii', at, at + 4)
    const offset = woff.readUInt32BE(at + 4)
    const compLength = woff.readUInt32BE(at + 8)
    const origLength = woff.readUInt32BE(at + 12)
    const checksum = woff.readUInt32BE(at + 16)
    const raw = woff.subarray(offset, offset + compLength)
    const data = compLength < origLength ? zlib.inflateSync(raw) : Buffer.from(raw)
    tables.push({ tag, checksum, data })
  }
  let pow = 1
  let log = 0
  while (pow * 2 <= numTables) {
    pow *= 2
    log++
  }
  const header = Buffer.alloc(12 + numTables * 16)
  header.writeUInt32BE(flavor, 0)
  header.writeUInt16BE(numTables, 4)
  header.writeUInt16BE(pow * 16, 6)
  header.writeUInt16BE(log, 8)
  header.writeUInt16BE(numTables * 16 - pow * 16, 10)
  let offset = header.length
  const bodies = []
  tables.forEach((t, i) => {
    const at = 12 + i * 16
    header.write(t.tag, at, 'ascii')
    header.writeUInt32BE(t.checksum, at + 4)
    header.writeUInt32BE(offset, at + 8)
    header.writeUInt32BE(t.data.length, at + 12)
    const padded = Buffer.alloc((t.data.length + 3) & ~3)
    t.data.copy(padded)
    bodies.push(padded)
    offset += padded.length
  })
  return Buffer.concat([header, ...bodies])
}

function fontFile(pkg, file) {
  const woff = fs.readFileSync(require.resolve(`${pkg}/files/${file}`))
  const out = path.join(os.tmpdir(), `postd-${file.replace(/\.woff$/, '.ttf')}`)
  fs.writeFileSync(out, woffToSfnt(woff))
  return out
}

const fontFiles = [
  fontFile('@fontsource/syne', 'syne-latin-700-normal.woff'),
  fontFile('@fontsource/syne', 'syne-latin-800-normal.woff'),
  fontFile('@fontsource/inter', 'inter-latin-500-normal.woff'),
  fontFile('@fontsource/inter', 'inter-latin-600-normal.woff'),
]

function renderPng(svg, width) {
  return new Resvg(svg, {
    fitTo: { mode: 'width', value: width },
    font: { fontFiles, loadSystemFonts: false, defaultFontFamily: 'Inter' },
    background: 'rgba(0,0,0,0)',
  })
    .render()
    .asPng()
}

function write(relPath, buffer) {
  const file = path.join(publicDir, relPath)
  fs.mkdirSync(path.dirname(file), { recursive: true })
  fs.writeFileSync(file, buffer)
  console.log(`wrote ${path.relative(webDir, file)} (${Math.round(buffer.length / 1024)} KB)`)
}

// The icon's artwork without its own <svg> wrapper, for reuse inside others.
const iconInner = iconSvg
  .replace(/<\?xml[^>]*>/, '')
  .replace(/<svg[^>]*>/, '')
  .replace(/<\/svg>\s*$/, '')
  .replace(/<title>[\s\S]*?<\/title>/, '')

// ── Social share card ────────────────────────────────────────────────────────
function hexPath(cx, cy, r) {
  const pts = []
  for (let i = 0; i < 6; i++) {
    const a = (Math.PI / 3) * i + Math.PI / 6
    pts.push(`${(cx + r * Math.cos(a)).toFixed(1)},${(cy + r * Math.sin(a)).toFixed(1)}`)
  }
  return `M${pts.join('L')}Z`
}

function honeycomb(width, height, r) {
  const w = Math.sqrt(3) * r
  const h = 1.5 * r
  const paths = []
  for (let row = -1; row * h < height + r; row++) {
    for (let col = -1; col * w < width + w; col++) {
      paths.push(hexPath(col * w + (row % 2 ? w / 2 : 0), row * h, r - 2))
    }
  }
  return paths.join('')
}

const ogSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <rect width="1200" height="630" fill="${CREAM}"/>
  <path d="${honeycomb(1200, 630, 34)}" fill="none" stroke="${NAVY}" stroke-opacity="0.045" stroke-width="2"/>
  <circle cx="1010" cy="250" r="260" fill="${AMBER}" fill-opacity="0.07"/>

  <!-- Brand lock-up -->
  <g transform="translate(80 72) scale(1.125)">${iconInner}</g>
  <text x="170" y="126" font-family="Syne" font-weight="800" font-size="52" fill="${NAVY}" letter-spacing="-1">postd<tspan fill="${AMBER}">.</tspan>uk</text>

  <!-- Headline -->
  <text font-family="Syne" font-weight="800" font-size="72" fill="${NAVY}" letter-spacing="-1.5">
    <tspan x="80" y="262">Social media that</tspan>
    <tspan x="80" y="346" fill="${AMBER}">posts itself.</tspan>
  </text>

  <!-- Supporting line -->
  <text font-family="Inter" font-weight="500" font-size="28" fill="${SLATE}">
    <tspan x="80" y="418">Written from your website and Google reviews.</tspan>
    <tspan x="80" y="458">Published to Google Business Profile, Facebook,</tspan>
    <tspan x="80" y="498">LinkedIn and X, automatically.</tspan>
  </text>

  <!-- Large mark -->
  <g transform="translate(850 110) scale(4.6875)">${iconInner}</g>

  <!-- Footer band -->
  <rect y="560" width="1200" height="70" fill="${NAVY}"/>
  <rect y="560" width="1200" height="4" fill="${AMBER}"/>
  <text x="80" y="604" font-family="Inter" font-weight="600" font-size="24" fill="#FFFFFF">postd.uk</text>
  <text x="1120" y="604" text-anchor="end" font-family="Inter" font-weight="500" font-size="22" fill="#FFFFFF" fill-opacity="0.75">Automated social media for UK small businesses, built by dijitul</text>
</svg>`

write('og-image.png', renderPng(ogSvg, 1200))

// ── App icons ────────────────────────────────────────────────────────────────
for (const size of [72, 96, 128, 144, 152, 192, 384, 512]) {
  write(`icons/icon-${size}.png`, renderPng(iconSvg, size))
}

// Maskable: artwork kept inside the central safe zone on a full-bleed square.
const maskableSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
  <rect width="512" height="512" fill="${NAVY}"/>
  <g transform="translate(76 76) scale(5.625)">${iconInner}</g>
</svg>`
write('icons/icon-512-maskable.png', renderPng(maskableSvg, 512))

// Apple touch icon: iOS rounds the corners itself and shows transparency as
// black, so fill the whole square.
const appleSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="180" height="180" viewBox="0 0 64 64">
  <rect width="64" height="64" fill="${NAVY}"/>
  <g transform="translate(4 4) scale(0.875)">${iconInner}</g>
</svg>`
write('apple-touch-icon.png', renderPng(appleSvg, 180))

// ── favicon.ico (PNG-compressed entries, supported by every current browser)
const icoSizes = [16, 32, 48]
const pngs = icoSizes.map((s) => renderPng(iconSvg, s))
const icoHeader = Buffer.alloc(6 + icoSizes.length * 16)
icoHeader.writeUInt16LE(0, 0)
icoHeader.writeUInt16LE(1, 2)
icoHeader.writeUInt16LE(icoSizes.length, 4)
let icoOffset = icoHeader.length
icoSizes.forEach((s, i) => {
  const at = 6 + i * 16
  icoHeader.writeUInt8(s, at)
  icoHeader.writeUInt8(s, at + 1)
  icoHeader.writeUInt8(0, at + 2)
  icoHeader.writeUInt8(0, at + 3)
  icoHeader.writeUInt16LE(1, at + 4)
  icoHeader.writeUInt16LE(32, at + 6)
  icoHeader.writeUInt32LE(pngs[i].length, at + 8)
  icoHeader.writeUInt32LE(icoOffset, at + 12)
  icoOffset += pngs[i].length
})
write('favicon.ico', Buffer.concat([icoHeader, ...pngs]))

