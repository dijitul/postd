/**
 * One-off generator for the raster brand assets, built from the SVGs in
 * web/public/brand. Run it again only when the brand changes:
 *
 *   npm run generate:images
 *
 * Writes (all committed, served from web/public):
 *   og-image.png               1200x630 social share card
 *   app-icons/icon-<size>.png      PWA and manifest icons
 *   app-icons/icon-512-maskable.png  full-bleed icon for Android adaptive masks
 *   apple-touch-icon.png       180x180, square, no transparency
 *   favicon.ico                16 and 32px PNGs in an ICO container
 *
 * Text is set in Sora and Plus Jakarta Sans from @fontsource. resvg only reads TrueType
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

// Relay tokens (docs/brand-2027/README.md)
const NAVY = '#0E1A3F'
const COBALT = '#2340F5'
const COBALT_50 = '#EEF1FF'
const SUN = '#FFD23F'
const CLOUD = '#F3F5FB'
const LINE = '#E3E7F1'
const WHITE = '#FFFFFF'
const SLATE = '#56607A'
const AI = '#6B4EFF'
const AI_50 = '#EFEBFF'
const SUCCESS_SOFT = '#E6F6EC'
const SUCCESS_TEXT = '#0B7F3D'

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
  fontFile('@fontsource/sora', 'sora-latin-700-normal.woff'),
  fontFile('@fontsource/sora', 'sora-latin-800-normal.woff'),
  fontFile('@fontsource/plus-jakarta-sans', 'plus-jakarta-sans-latin-500-normal.woff'),
  fontFile('@fontsource/plus-jakarta-sans', 'plus-jakarta-sans-latin-600-normal.woff'),
]

function renderPng(svg, width) {
  return new Resvg(svg, {
    fitTo: { mode: 'width', value: width },
    font: { fontFiles, loadSystemFonts: false, defaultFontFamily: 'Plus Jakarta Sans' },
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
// Relay: white card, cloud panel, cobalt mark with a live "postd" wordmark,
// and the hero headline with the sun marker behind "thinking".

// Width of a run of text as resvg sets it, so the marker fits the word.
function textWidth(text, { family = 'Sora', weight = 800, size = 72, spacing = -2.5 } = {}) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="2000" height="200"><text x="0" y="150" font-family="${family}" font-weight="${weight}" font-size="${size}" letter-spacing="${spacing}" fill="#000">${text}</text></svg>`
  const bbox = new Resvg(svg, { font: { fontFiles, loadSystemFonts: false, defaultFontFamily: 'Plus Jakarta Sans' } }).getBBox()
  return bbox ? bbox.width : text.length * size * 0.6
}

const HEAD = { size: 72, spacing: -2.5 }
const lineX = 80
const withoutW = textWidth('without', HEAD)
const spaceW = textWidth('without thinking', HEAD) - withoutW - textWidth('thinking', HEAD)
const thinkingX = lineX + withoutW + spaceW
const thinkingW = textWidth('thinking', HEAD)
const pad = HEAD.size * 0.12

const ogSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <rect width="1200" height="630" fill="${WHITE}"/>
  <rect x="830" y="0" width="370" height="630" fill="${CLOUD}"/>
  <circle cx="1120" cy="92" r="34" fill="${SUN}"/>

  <!-- Brand lock-up -->
  <g transform="translate(80 64) scale(0.875)">${iconInner}</g>
  <text x="152" y="108" font-family="Sora" font-weight="800" font-size="46" fill="${NAVY}" letter-spacing="-1.6">postd</text>

  <!-- Headline, with the sun marker behind one word -->
  <rect x="${(thinkingX - pad).toFixed(1)}" y="${(330 - HEAD.size * 0.86).toFixed(1)}" width="${(thinkingW + pad * 2).toFixed(1)}" height="${(HEAD.size * 1.08).toFixed(1)}" rx="${(HEAD.size * 0.18).toFixed(1)}" fill="${SUN}"/>
  <text font-family="Sora" font-weight="800" font-size="${HEAD.size}" fill="${NAVY}" letter-spacing="${HEAD.spacing}">
    <tspan x="${lineX}" y="246">Post every week</tspan>
    <tspan x="${lineX}" y="330">without thinking</tspan>
    <tspan x="${lineX}" y="414">about it.</tspan>
  </text>

  <!-- Supporting line -->
  <text font-family="Plus Jakarta Sans" font-weight="500" font-size="24" fill="${SLATE}">
    <tspan x="80" y="482">Written from your website and Google reviews, published</tspan>
    <tspan x="80" y="516">to Google Business Profile, Facebook, LinkedIn and X.</tspan>
  </text>

  <!-- Floating post card on the cloud panel -->
  <g transform="translate(855 170)">
    <rect width="315" height="250" rx="18" fill="${WHITE}"/>
    <rect width="315" height="250" rx="18" fill="none" stroke="${LINE}" stroke-width="2"/>
    <circle cx="44" cy="46" r="20" fill="${COBALT_50}"/>
    <g transform="translate(32 34) scale(0.375)">${iconInner}</g>
    <rect x="76" y="34" width="150" height="12" rx="6" fill="${NAVY}" fill-opacity="0.85"/>
    <rect x="76" y="54" width="96" height="10" rx="5" fill="${LINE}"/>
    <rect x="24" y="92" width="267" height="10" rx="5" fill="${LINE}"/>
    <rect x="24" y="112" width="245" height="10" rx="5" fill="${LINE}"/>
    <rect x="24" y="132" width="220" height="10" rx="5" fill="${LINE}"/>
    <rect x="24" y="176" width="146" height="34" rx="17" fill="${AI_50}"/>
    <text x="97" y="198" text-anchor="middle" font-family="Plus Jakarta Sans" font-weight="600" font-size="15" fill="${AI}">Written by postd</text>
    <rect x="180" y="176" width="88" height="34" rx="17" fill="${SUCCESS_SOFT}"/>
    <text x="224" y="198" text-anchor="middle" font-family="Plus Jakarta Sans" font-weight="600" font-size="15" fill="${SUCCESS_TEXT}">Posted</text>
  </g>

  <!-- Footer band -->
  <rect y="566" width="1200" height="64" fill="${NAVY}"/>
  <text x="80" y="606" font-family="Plus Jakarta Sans" font-weight="600" font-size="22" fill="#FFFFFF">postd.uk</text>
  <text x="1120" y="606" text-anchor="end" font-family="Plus Jakarta Sans" font-weight="500" font-size="20" fill="#FFFFFF" fill-opacity="0.8">Automated social media for UK small businesses, built by dijitul</text>
</svg>`

write('og-image.png', renderPng(ogSvg, 1200))

// ── App icons ────────────────────────────────────────────────────────────────
// Served from /app-icons, not /icons: Apache aliases /icons/ to its own stock
// icon set server-wide, so anything under /icons/ never reaches our files.
for (const size of [72, 96, 128, 144, 152, 192, 384, 512]) {
  write(`app-icons/icon-${size}.png`, renderPng(iconSvg, size))
}

// Maskable: artwork kept inside the central safe zone on a full-bleed square.
const maskableSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
  <rect width="512" height="512" fill="${COBALT}"/>
  <g transform="translate(76 76) scale(5.625)">${iconInner}</g>
</svg>`
write('app-icons/icon-512-maskable.png', renderPng(maskableSvg, 512))

// Apple touch icon: iOS rounds the corners itself and shows transparency as
// black, so fill the whole square.
const appleSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="180" height="180" viewBox="0 0 64 64">
  <rect width="64" height="64" fill="${COBALT}"/>
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

