import { useState } from 'react'
import { KeyRound } from 'lucide-react'
import { billingApi } from '../../lib/api.js'

/** "Have an access code?" for dijitul's retainer clients. */
export default function RedeemCode() {
  const [code, setCode] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await billingApi.redeemCode(code.trim())
      window.location.reload()
    } catch (err) {
      setError(err?.response?.data?.message ?? "That code isn't valid.")
      setBusy(false)
    }
  }

  return (
    <div className="bg-white rounded-2xl border border-line p-5">
      <div className="flex items-center gap-2 mb-1">
        <KeyRound className="w-4 h-4 text-cobalt-600" />
        <h2 className="font-display font-bold text-base text-navy-900">Have an access code?</h2>
      </div>
      <p className="text-sm text-slate-500 mb-3">Enter the code dijitul gave you and there is nothing to pay.</p>
      <form onSubmit={submit} className="flex flex-wrap gap-2">
        <label htmlFor="redeem-code" className="sr-only">Access code</label>
        <input
          id="redeem-code"
          value={code}
          onChange={(e) => setCode(e.target.value)}
          autoComplete="off"
          placeholder="Access code"
          className="input flex-1 min-w-0"
        />
        <button
          type="submit"
          disabled={busy || !code.trim()}
          className="font-display min-h-11 px-5 rounded-full border border-slate-300 bg-white text-sm font-semibold text-navy-900 hover:bg-cloud disabled:opacity-50"
        >
          {busy ? 'Checking…' : 'Apply code'}
        </button>
        {error && <p role="alert" className="w-full text-sm text-red-600">{error}</p>}
      </form>
    </div>
  )
}
