import { useSyncExternalStore } from 'react'

const subscribe = () => () => {}

/**
 * False while React is prerendering or hydrating server HTML, true otherwise.
 *
 * Prerendered pages are built with no signed-in user, so anything that
 * depends on browser-only state (localStorage auth, window size) must wait
 * until hydration has finished or React reports a mismatch. On a normal
 * client-side navigation this is true from the first render, so nothing
 * flickers.
 */
export default function useHydrated() {
  return useSyncExternalStore(subscribe, () => true, () => false)
}
