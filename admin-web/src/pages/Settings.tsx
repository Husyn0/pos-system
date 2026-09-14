import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { api } from '@/api/client'
import { Topbar } from '@/components/Topbar'
import { useAuthStore } from '@/store/auth'

/** Device pairing: generates a code the physical terminal/hardware-bridge
 *  exchanges (via POST /devices/claim) for its permanent access token. */
export default function Settings() {
  const branchId = useAuthStore((s) => s.branchId)
  const [deviceName, setDeviceName] = useState('')
  const [pairingCode, setPairingCode] = useState<string | null>(null)

  const createCode = useMutation({
    mutationFn: () => api.post('/devices/pairing-code', { branch_id: branchId, name: deviceName, type: 'pos_terminal' }),
    onSuccess: (res) => setPairingCode(res.data.pairing_code),
  })

  return (
    <div>
      <Topbar title="Settings" />
      <div className="p-8 max-w-lg space-y-8">
        <section className="card p-6">
          <h2 className="font-display text-lg mb-1">Pair a new device</h2>
          <p className="text-sm text-muted mb-4">
            Generate a code to connect a POS terminal, kitchen display, or kiosk.
            Enter it on the device (or in the desktop app / hardware bridge config) to finish setup.
          </p>
          <div className="flex gap-3">
            <input
              className="input"
              placeholder="e.g. Front Register"
              value={deviceName}
              onChange={(e) => setDeviceName(e.target.value)}
            />
            <button onClick={() => createCode.mutate()} className="btn-primary whitespace-nowrap" disabled={!deviceName}>
              Generate code
            </button>
          </div>
          {pairingCode && (
            <div className="mt-4 text-center">
              <div className="text-xs text-muted mb-1">Pairing code (expires after use)</div>
              <div className="font-mono text-3xl tracking-widest text-roast-dark">{pairingCode}</div>
            </div>
          )}
        </section>
      </div>
    </div>
  )
}
