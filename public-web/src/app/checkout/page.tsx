'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { useCartStore } from '@/lib/cart'
import { apiFetch } from '@/lib/api'

export default function CheckoutPage() {
  const { lines, subtotal, clear } = useCartStore()
  const [orderType, setOrderType] = useState<'takeaway' | 'delivery'>('takeaway')
  const [placing, setPlacing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const router = useRouter()
  const branchId = process.env.NEXT_PUBLIC_BRANCH_ID

  async function handlePlaceOrder() {
    setPlacing(true)
    setError(null)
    try {
      const order = await apiFetch('/orders', {
        method: 'POST',
        body: JSON.stringify({
          branch_id: branchId,
          order_type: orderType === 'takeaway' ? 'online_pickup' : 'delivery',
          source: 'web',
          idempotency_key: crypto.randomUUID(),
          items: lines.map((l) => ({ menu_item_id: l.menuItemId, quantity: l.quantity })),
        }),
      })
      // In production: collect card details via the payment gateway's hosted
      // fields/SDK here, then confirm the PaymentIntent before this redirect.
      clear()
      router.push(`/track/${order.order_number}?branch_id=${branchId}`)
    } catch {
      setError('Something went wrong placing your order. Please try again.')
    } finally {
      setPlacing(false)
    }
  }

  return (
    <main className="max-w-lg mx-auto px-6 py-12">
      <h1 className="text-3xl text-roast-dark mb-6">Checkout</h1>

      <div className="flex gap-3 mb-6">
        {(['takeaway', 'delivery'] as const).map((type) => (
          <button
            key={type}
            onClick={() => setOrderType(type)}
            className={`px-4 py-2 rounded-full text-sm capitalize ${orderType === type ? 'bg-roast text-paper' : 'bg-black/5'}`}
          >
            {type}
          </button>
        ))}
      </div>

      <div className="border border-black/10 rounded-lg p-4 mb-6 text-sm text-muted">
        Card details are collected securely by our payment provider's hosted
        checkout — they never pass through this site's servers.
      </div>

      <div className="flex justify-between font-medium mb-6">
        <span>Total due</span>
        <span className="font-mono">${subtotal().toFixed(2)}</span>
      </div>

      {error && <p className="text-red-600 text-sm mb-4">{error}</p>}

      <button onClick={handlePlaceOrder} disabled={placing || lines.length === 0} className="w-full bg-roast text-paper py-3 rounded-md font-medium disabled:opacity-40">
        {placing ? 'Placing order…' : `Place order — $${subtotal().toFixed(2)}`}
      </button>
    </main>
  )
}
