import { useState, useEffect, useRef, useCallback } from 'react'
import { Search, MapPin, X, Loader2, Building2 } from 'lucide-react'

const API_KEY = import.meta.env.VITE_GOOGLE_PLACES_API_KEY

// Load Google Maps script once, shared across instances
let scriptPromise = null
function loadGoogleMaps() {
  if (!API_KEY) return Promise.reject(new Error('No Google Places API key'))
  if (window.google?.maps?.places) return Promise.resolve()
  if (scriptPromise) return scriptPromise
  scriptPromise = new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${API_KEY}&libraries=places`
    script.async = true
    script.onload = resolve
    script.onerror = () => { scriptPromise = null; reject(new Error('Failed to load Google Maps')) }
    document.head.appendChild(script)
  })
  return scriptPromise
}

/**
 * BusinessSearch
 *
 * Google Places autocomplete restricted to UK establishments.
 * onSelect is called with { name, website, address, city } when a place is chosen.
 * onNameChange is called with just the string as the user types (for fallback free-text).
 */
export default function BusinessSearch({ onSelect, onNameChange, defaultValue = '', error }) {
  const [query, setQuery] = useState(defaultValue)
  const [predictions, setPredictions] = useState([])
  const [loading, setLoading] = useState(false)
  const [loadingDetails, setLoadingDetails] = useState(false)
  const [mapsReady, setMapsReady] = useState(!!window.google?.maps?.places)
  const [open, setOpen] = useState(false)
  const [selected, setSelected] = useState(false)

  const serviceRef = useRef(null)
  const debounceRef = useRef(null)
  const containerRef = useRef(null)

  // Load script on mount
  useEffect(() => {
    if (mapsReady) return
    loadGoogleMaps()
      .then(() => setMapsReady(true))
      .catch(() => {
        // Fall back gracefully — plain text input still works
      })
  }, [mapsReady])

  // Initialise service once maps is ready
  useEffect(() => {
    if (mapsReady && !serviceRef.current) {
      serviceRef.current = new window.google.maps.places.AutocompleteService()
    }
  }, [mapsReady])

  // Close dropdown when clicking outside
  useEffect(() => {
    const handler = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const search = useCallback((value) => {
    if (!serviceRef.current || value.length < 2) {
      setPredictions([])
      setOpen(false)
      return
    }
    setLoading(true)
    serviceRef.current.getPlacePredictions(
      {
        input: value,
        types: ['establishment'],
        componentRestrictions: { country: 'gb' },
      },
      (results, status) => {
        setLoading(false)
        if (status === window.google.maps.places.PlacesServiceStatus.OK && results) {
          setPredictions(results.slice(0, 6))
          setOpen(true)
        } else {
          setPredictions([])
          setOpen(false)
        }
      }
    )
  }, [])

  const handleChange = (e) => {
    const value = e.target.value
    setQuery(value)
    setSelected(false)
    onNameChange?.(value)
    clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => search(value), 280)
  }

  const handleSelect = (prediction) => {
    const name = prediction.structured_formatting.main_text
    setQuery(name)
    setPredictions([])
    setOpen(false)
    setSelected(true)
    onNameChange?.(name)

    if (!window.google?.maps?.places) {
      onSelect?.({ name })
      return
    }

    // Fetch place details to get website + address
    setLoadingDetails(true)
    const div = document.createElement('div')
    const placesService = new window.google.maps.places.PlacesService(div)
    placesService.getDetails(
      {
        placeId: prediction.place_id,
        // address_components carries postal_town, which is the field that actually
        // holds the town on UK addresses. Parsing it out of formatted_address means
        // guessing which comma-separated part is the town, and the guess is wrong
        // often enough to matter when posts say where you are.
        fields: ['name', 'website', 'formatted_address', 'address_components'],
      },
      (place, status) => {
        setLoadingDetails(false)
        if (status === window.google.maps.places.PlacesServiceStatus.OK && place) {
          onSelect?.({
            name: place.name ?? name,
            website: place.website ?? '',
            address: place.formatted_address ?? '',
            city: townFrom(place.address_components),
          })
        } else {
          onSelect?.({ name })
        }
      }
    )
  }

  const handleClear = () => {
    setQuery('')
    setPredictions([])
    setOpen(false)
    setSelected(false)
    onNameChange?.('')
    onSelect?.({ name: '', website: '', address: '' })
  }

  // Google labels UK towns postal_town. locality is the fallback for the places
  // that do not have one, and for addresses outside the UK.
  const townFrom = (components) => {
    if (!Array.isArray(components)) return ''

    const match = components.find((c) => c.types?.includes('postal_town'))
      ?? components.find((c) => c.types?.includes('locality'))
      ?? components.find((c) => c.types?.includes('administrative_area_level_2'))

    return match?.long_name ?? ''
  }

  const locationText = (prediction) => {
    const secondary = prediction.structured_formatting.secondary_text
    if (!secondary) return null
    // Trim to town/city level — last 2 parts
    const parts = secondary.split(',').map((s) => s.trim())
    return parts.slice(-2).join(', ')
  }

  return (
    <div ref={containerRef} className="relative">
      <div className="relative">
        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
        <input
          type="text"
          value={query}
          onChange={handleChange}
          onFocus={() => predictions.length > 0 && setOpen(true)}
          placeholder="Search for your business…"
          className={`input pl-10 pr-10 ${error ? 'border-red-400' : selected ? 'border-green-400' : ''}`}
          autoComplete="off"
          autoCorrect="off"
          spellCheck={false}
        />
        <div className="absolute right-3 top-1/2 -translate-y-1/2 flex items-center">
          {(loading || loadingDetails) && (
            <Loader2 className="w-4 h-4 text-amber-500 animate-spin" />
          )}
          {query && !loading && !loadingDetails && (
            <button
              type="button"
              onClick={handleClear}
              className="p-0.5 text-slate-400 hover:text-slate-600 transition-colors"
              aria-label="Clear"
            >
              <X className="w-4 h-4" />
            </button>
          )}
        </div>
      </div>

      {/* Dropdown */}
      {open && predictions.length > 0 && (
        <div className="absolute z-50 top-full left-0 right-0 mt-1.5 bg-white rounded-2xl border border-slate-200 overflow-hidden animate-fade-in"
          style={{ boxShadow: '0 8px 24px rgb(30 45 74 / 0.12)' }}>
          {predictions.map((prediction) => (
            <button
              key={prediction.place_id}
              type="button"
              onClick={() => handleSelect(prediction)}
              className="w-full flex items-start gap-3 px-4 py-3 hover:bg-cream-200 transition-colors text-left border-b border-slate-100 last:border-0"
            >
              <MapPin className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
              <div className="min-w-0">
                <p className="text-sm font-semibold text-navy-800 leading-snug">
                  {prediction.structured_formatting.main_text}
                </p>
                {locationText(prediction) && (
                  <p className="text-xs text-slate-400 mt-0.5">{locationText(prediction)}</p>
                )}
              </div>
            </button>
          ))}
          <div className="flex items-center justify-end px-4 py-2 bg-slate-50 border-t border-slate-100">
            <span className="text-2xs text-slate-400">Powered by Google</span>
          </div>
        </div>
      )}

      {/* No Maps key fallback hint */}
      {!mapsReady && !API_KEY && (
        <p className="mt-1.5 text-xs text-slate-400">Type your business name exactly as it appears on Google</p>
      )}

      {/* Plain text tip when maps loaded but nothing typed yet */}
      {mapsReady && !query && (
        <p className="mt-1.5 text-xs text-slate-400 flex items-center gap-1">
          <Building2 className="w-3 h-3" />
          Start typing — we&apos;ll find you on Google
        </p>
      )}
    </div>
  )
}
