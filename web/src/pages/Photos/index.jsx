import { useRef, useState } from 'react'
import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  Images, Upload, RefreshCw, Trash2, ExternalLink, Globe, Sparkles, MapPin, Camera, Check, AlertCircle, X
} from 'lucide-react'
import { imagesApi } from '../../lib/api.js'

const QUERY_KEY = ['images']

// Where a photo came from, worded for the owner rather than for us.
const SOURCES = {
  website: { label: 'Website',     Icon: Globe,    cls: 'bg-white/95 text-navy-800' },
  google:  { label: 'Google',      Icon: MapPin,   cls: 'bg-white/95 text-navy-800' },
  upload:  { label: 'Your upload', Icon: Camera,   cls: 'bg-white/95 text-navy-800' },
  ai:      { label: 'AI',          Icon: Sparkles, cls: 'bg-purple-600/95 text-white' },
}

const ACCEPT = 'image/jpeg,image/png,image/webp,image/avif,image/heic,image/heif,.heic,.heif'

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString('en-GB', { hour: 'numeric', minute: '2-digit' })
}

// "/services/boiler-repairs" reads better on a small card than the full URL.
function pageLabel(url) {
  try {
    const { pathname } = new URL(url)
    return pathname === '/' || pathname === '' ? 'Home page' : decodeURIComponent(pathname.replace(/\/$/, ''))
  } catch {
    return url
  }
}

// Why postd switched a photo off by itself, in the owner's terms. The owner
// can switch any of these back on; this is only postd's first opinion.
const VETTING_NOTES = {
  screenshot: 'Looks like a screenshot, so postd switched it off',
  mostly_text: 'Mostly text, so postd switched it off',
  logo: 'Looks like a logo, so postd switched it off',
  unsuitable: 'Not suitable for a post, so postd switched it off',
  duplicate: 'Same as another photo, so postd switched it off',
}

function usageText(image) {
  if (!image.use_count) return 'Not used yet'
  const times = image.use_count === 1 ? 'once' : `${image.use_count} times`
  return image.last_used_at ? `Used ${times}, last on ${formatDate(image.last_used_at)}` : `Used ${times}`
}

function errorMessage(err, fallback) {
  return err?.response?.data?.message ?? err?.response?.data?.errors?.image?.[0] ?? fallback
}

// ── Photo card ─────────────────────────────────────────────────────────────────
function PhotoCard({ image, onToggle, onDelete, busy }) {
  const [confirming, setConfirming] = useState(false)
  const [broken, setBroken] = useState(false)
  const source = SOURCES[image.source] ?? SOURCES.website
  const SourceIcon = source.Icon

  return (
    <li className="m-0 bg-white rounded-2xl border border-cream-300 overflow-hidden flex flex-col" style={{ boxShadow: '0 2px 8px rgb(30 45 74 / 0.06)' }}>
      <div className="relative aspect-square bg-cream-300">
        {!broken ? (
          <img
            src={image.thumbnail_url}
            alt=""
            loading="lazy"
            decoding="async"
            onError={() => setBroken(true)}
            className={`w-full h-full object-cover transition-all duration-200 ${image.is_enabled ? '' : 'opacity-40 grayscale'}`}
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-slate-400">
            <Images className="w-8 h-8" aria-hidden="true" />
          </div>
        )}
        <span className={`absolute top-2 left-2 inline-flex items-center gap-1 px-2 py-1 rounded-lg text-2xs font-bold shadow-sm ${source.cls}`}>
          <SourceIcon className="w-3 h-3" aria-hidden="true" />
          {source.label}
        </span>
        {!image.is_enabled && (
          <span className="absolute bottom-2 left-2 px-2 py-1 rounded-lg text-2xs font-bold bg-navy-800/90 text-white">
            Switched off
          </span>
        )}
      </div>

      <div className="p-3 flex-1 flex flex-col gap-1.5 min-w-0">
        {image.page_url ? (
          <a
            href={image.page_url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1 text-xs font-semibold text-navy-700 hover:text-amber-600 transition-colors min-w-0"
            title={image.page_url}
          >
            <span className="truncate">{pageLabel(image.page_url)}</span>
            <ExternalLink className="w-3 h-3 flex-shrink-0" aria-hidden="true" />
          </a>
        ) : image.source === 'google' ? (
          <p className="text-xs font-semibold text-navy-700 truncate">Google Business Profile</p>
        ) : null}
        {image.description && (
          <p className="text-xs text-slate-600 leading-snug line-clamp-2" title={image.description}>
            {image.description}
          </p>
        )}
        {!image.is_enabled && image.vetting_note && VETTING_NOTES[image.vetting_note] && (
          <p className="text-2xs font-semibold text-amber-700">{VETTING_NOTES[image.vetting_note]}</p>
        )}
        {image.source !== 'ai' && !image.vetted && image.is_enabled && (
          <p className="text-2xs text-slate-500">Being checked, used on posts once it has been</p>
        )}
        <p className="text-2xs text-slate-500">{usageText(image)}</p>

        <div className="mt-auto pt-2 flex items-center gap-2">
          {confirming ? (
            <>
              <button
                type="button"
                onClick={() => { setConfirming(false); onDelete(image) }}
                disabled={busy}
                className="flex-1 min-h-11 text-xs font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 transition-colors disabled:opacity-50"
              >
                Delete
              </button>
              <button
                type="button"
                onClick={() => setConfirming(false)}
                className="flex-1 min-h-11 text-xs font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors"
              >
                Keep
              </button>
            </>
          ) : (
            <>
              <button
                type="button"
                aria-pressed={image.is_enabled}
                onClick={() => onToggle(image)}
                disabled={busy}
                className={`flex-1 min-h-11 px-2 inline-flex items-center justify-center gap-1.5 text-xs leading-tight font-bold rounded-xl border transition-colors disabled:opacity-50 ${
                  image.is_enabled
                    ? 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100'
                    : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50'
                }`}
              >
                {image.is_enabled
                  ? <><Check className="w-3.5 h-3.5" aria-hidden="true" /> Use in posts</>
                  : 'Use in posts'}
              </button>
              <button
                type="button"
                onClick={() => setConfirming(true)}
                disabled={busy}
                aria-label="Delete this photo"
                title="Delete"
                className="min-h-11 min-w-11 inline-flex items-center justify-center rounded-xl text-slate-400 border border-slate-200 hover:text-red-600 hover:border-red-200 hover:bg-red-50 transition-colors disabled:opacity-50"
              >
                <Trash2 className="w-4 h-4" aria-hidden="true" />
              </button>
            </>
          )}
        </div>
      </div>
    </li>
  )
}

// ── Upload area ────────────────────────────────────────────────────────────────
function UploadArea({ onFiles, uploading }) {
  const inputRef = useRef(null)
  const [dragging, setDragging] = useState(false)

  const handleDrop = (e) => {
    e.preventDefault()
    setDragging(false)
    const files = [...(e.dataTransfer?.files ?? [])]
    if (files.length) onFiles(files)
  }

  return (
    <div
      onDragOver={(e) => { e.preventDefault(); setDragging(true) }}
      onDragLeave={() => setDragging(false)}
      onDrop={handleDrop}
      className={`rounded-2xl border-2 border-dashed p-5 sm:p-6 text-center transition-colors ${
        dragging ? 'border-amber-500 bg-amber-50' : 'border-cream-300 bg-white'
      }`}
    >
      <input
        ref={inputRef}
        type="file"
        accept={ACCEPT}
        multiple
        className="sr-only"
        id="photo-upload"
        onChange={(e) => {
          const files = [...(e.target.files ?? [])]
          e.target.value = ''
          if (files.length) onFiles(files)
        }}
      />
      <label
        htmlFor="photo-upload"
        className="w-full sm:w-auto inline-flex items-center justify-center gap-2 min-h-12 px-5 rounded-xl bg-navy-800 text-white text-sm font-bold hover:bg-navy-700 active:scale-[0.98] transition-all cursor-pointer"
      >
        {uploading
          ? <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" aria-hidden="true" />
          : <Upload className="w-4 h-4" aria-hidden="true" />}
        Upload photos
      </label>
      <p className="mt-2 text-xs text-slate-500">
        <span className="hidden sm:inline">Or drag them here. </span>JPEG, PNG or WebP, up to 15MB each.
      </p>
    </div>
  )
}

// ── Page ───────────────────────────────────────────────────────────────────────
export default function PhotosPage() {
  const queryClient = useQueryClient()
  const [notice, setNotice] = useState(null) // { tone: 'info' | 'error', text }
  const [uploads, setUploads] = useState([]) // { id, name, status: 'uploading' | 'done' | 'error', message }
  const [busyId, setBusyId] = useState(null)

  const query = useInfiniteQuery({
    queryKey: QUERY_KEY,
    queryFn: ({ pageParam }) => imagesApi.getAll({ page: pageParam }).then((res) => res.data),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta?.current_page < last.meta?.last_page ? last.meta.current_page + 1 : undefined),
  })

  const images = query.data?.pages.flatMap((p) => p.images ?? []) ?? []
  const library = query.data?.pages[0]?.library ?? {}
  const total = query.data?.pages[0]?.meta?.total ?? 0
  const refreshBlockedUntil = library.refresh_available_at && new Date(library.refresh_available_at) > new Date()
    ? library.refresh_available_at
    : null

  // Change one photo in the cached pages without refetching the lot.
  const patchCached = (id, changes) => {
    queryClient.setQueryData(QUERY_KEY, (data) => data && {
      ...data,
      pages: data.pages.map((page) => ({
        ...page,
        images: changes === null
          ? page.images.filter((img) => img.id !== id)
          : page.images.map((img) => (img.id === id ? { ...img, ...changes } : img)),
      })),
    })
  }

  const toggle = useMutation({
    mutationFn: (image) => imagesApi.setEnabled(image.id, !image.is_enabled),
    onMutate: (image) => {
      setBusyId(image.id)
      patchCached(image.id, { is_enabled: !image.is_enabled })
    },
    onError: (err, image) => {
      patchCached(image.id, { is_enabled: image.is_enabled })
      setNotice({ tone: 'error', text: errorMessage(err, 'Could not change that photo. Please try again.') })
    },
    onSettled: () => setBusyId(null),
  })

  const remove = useMutation({
    mutationFn: (image) => imagesApi.remove(image.id).then((res) => res.data),
    onMutate: (image) => setBusyId(image.id),
    onSuccess: (data, image) => {
      patchCached(image.id, data.deleted ? null : { is_enabled: false })
      setNotice({ tone: 'info', text: data.message })
    },
    onError: (err) => setNotice({ tone: 'error', text: errorMessage(err, 'Could not delete that photo. Please try again.') }),
    onSettled: () => setBusyId(null),
  })

  const refresh = useMutation({
    mutationFn: () => imagesApi.refresh().then((res) => res.data),
    onSuccess: (data) => {
      setNotice({ tone: 'info', text: data.message })
      queryClient.invalidateQueries({ queryKey: QUERY_KEY })
    },
    onError: (err) => setNotice({ tone: 'error', text: errorMessage(err, 'Could not start the check. Please try again.') }),
  })

  // One at a time, so a handful of phone photos on a weak signal do not all
  // time out together, and each gets its own result.
  const uploadFiles = async (files) => {
    const batch = files.map((file, i) => ({ id: `${Date.now()}-${i}`, name: file.name, status: 'uploading', file }))
    setUploads((prev) => [...batch.map(({ file, ...rest }) => rest), ...prev].slice(0, 12))
    setNotice(null)

    for (const item of batch) {
      let result
      try {
        const res = await imagesApi.upload(item.file)
        result = { status: 'done', message: res.data?.message ?? 'Photo added.' }
      } catch (err) {
        result = { status: 'error', message: errorMessage(err, 'That photo could not be uploaded. Please try again.') }
      }
      setUploads((prev) => prev.map((u) => (u.id === item.id ? { ...u, ...result } : u)))
    }

    queryClient.invalidateQueries({ queryKey: QUERY_KEY })
  }

  const uploading = uploads.some((u) => u.status === 'uploading')

  return (
    <div className="max-w-5xl mx-auto animate-fade-in-up">
      {/* Header */}
      <div className="mb-5">
        <div className="flex items-center gap-3 mb-2">
          <Images className="w-5 h-5 text-amber-500" aria-hidden="true" />
          <h1 className="font-display font-black text-2xl text-navy-800">Photos</h1>
        </div>
        <p className="text-slate-600 text-sm leading-relaxed max-w-2xl">
          postd uses your own photos first. Untick anything you don't want used, for example stock photos you only have a licence to use on your website. If there's no suitable photo, postd can create one.
        </p>
      </div>

      {/* Actions */}
      <div className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-stretch mb-5">
        <UploadArea onFiles={uploadFiles} uploading={uploading} />
        <div className="flex flex-col justify-center gap-2 bg-white rounded-2xl border border-cream-300 p-5 sm:max-w-xs">
          <button
            type="button"
            onClick={() => refresh.mutate()}
            disabled={refresh.isPending || !!refreshBlockedUntil}
            className="w-full inline-flex items-center justify-center gap-2 min-h-12 px-4 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 text-sm font-bold hover:bg-amber-100 active:scale-[0.98] transition-all disabled:opacity-50"
          >
            <RefreshCw className={`w-4 h-4 flex-shrink-0 ${refresh.isPending ? 'animate-spin' : ''}`} aria-hidden="true" />
            Check my website and Google for new photos
          </button>
          <p className="text-2xs text-slate-500 text-center">
            {refreshBlockedUntil
              ? `You can check again after ${formatTime(refreshBlockedUntil)}.`
              : library.last_checked_at
                ? `Last checked ${formatDate(library.last_checked_at)}. We also check every night.`
                : 'We also check every night.'}
          </p>
        </div>
      </div>

      {/* Upload results */}
      {uploads.length > 0 && (
        <ul className="list-none pl-0 mb-4 space-y-2" aria-live="polite">
          {uploads.map((u) => (
            <li
              key={u.id}
              className={`flex items-start gap-2 rounded-xl px-3 py-2.5 text-xs border ${
                u.status === 'error' ? 'bg-red-50 border-red-200 text-red-700'
                  : u.status === 'done' ? 'bg-green-50 border-green-200 text-green-700'
                  : 'bg-white border-cream-300 text-slate-600'
              }`}
            >
              {u.status === 'uploading'
                ? <span className="w-3.5 h-3.5 mt-0.5 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin flex-shrink-0" aria-hidden="true" />
                : u.status === 'done'
                  ? <Check className="w-3.5 h-3.5 mt-0.5 flex-shrink-0" aria-hidden="true" />
                  : <AlertCircle className="w-3.5 h-3.5 mt-0.5 flex-shrink-0" aria-hidden="true" />}
              <span className="min-w-0">
                <span className="font-semibold break-all">{u.name}</span>
                {u.status === 'uploading' ? ': uploading...' : `: ${u.message}`}
              </span>
            </li>
          ))}
        </ul>
      )}

      {/* Notice */}
      {notice && (
        <div
          role="status"
          className={`mb-4 flex items-start gap-2 rounded-xl px-4 py-3 text-sm border ${
            notice.tone === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-navy-50 border-navy-100 text-navy-800'
          }`}
        >
          <p className="flex-1">{notice.text}</p>
          <button type="button" onClick={() => setNotice(null)} aria-label="Dismiss" className="p-1 -m-1 opacity-60 hover:opacity-100">
            <X className="w-4 h-4" aria-hidden="true" />
          </button>
        </div>
      )}

      {/* Library */}
      {query.isLoading ? (
        <ul className="list-none pl-0 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3" aria-hidden="true">
          {[...Array(8)].map((_, i) => (
            <li key={i} className="m-0 aspect-[3/4] rounded-2xl bg-cream-300 animate-pulse" />
          ))}
        </ul>
      ) : query.isError ? (
        <div className="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">
          Could not load your photos. Please refresh the page to try again.
        </div>
      ) : images.length === 0 ? (
        <div className="bg-white rounded-2xl border border-cream-300 px-6 py-10 text-center">
          <Images className="w-10 h-10 text-slate-300 mx-auto mb-3" aria-hidden="true" />
          <h2 className="font-display font-bold text-lg text-navy-800 mb-1">No photos yet</h2>
          <p className="text-sm text-slate-500 max-w-md mx-auto leading-relaxed">
            Photos from your website{library.has_google ? ' and Google Business Profile' : ''} appear here after the next website check, which happens every night.
            You can also upload your own now, or use the button above to check straight away.
          </p>
        </div>
      ) : (
        <>
          <p className="text-xs text-slate-500 mb-3">
            {total} {total === 1 ? 'photo' : 'photos'}. Newest first.
          </p>
          <ul className="list-none pl-0 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            {images.map((image) => (
              <PhotoCard
                key={image.id}
                image={image}
                busy={busyId === image.id}
                onToggle={(img) => toggle.mutate(img)}
                onDelete={(img) => remove.mutate(img)}
              />
            ))}
          </ul>
          {query.hasNextPage && (
            <div className="mt-5 text-center">
              <button
                type="button"
                onClick={() => query.fetchNextPage()}
                disabled={query.isFetchingNextPage}
                className="w-full sm:w-auto min-h-12 px-6 rounded-xl bg-white border border-cream-300 text-sm font-bold text-navy-800 hover:bg-cream-300 transition-colors disabled:opacity-50"
              >
                {query.isFetchingNextPage ? 'Loading...' : 'Show more photos'}
              </button>
            </div>
          )}
        </>
      )}
    </div>
  )
}
