'use client'
import Link from 'next/link'
import { useCartStore } from '@/lib/cart'

export default function CartPage() {
  const { lines, updateQty, subtotal } = useCartStore()

  if (lines.length === 0) {
    return (
      <main className="max-w-lg mx-auto px-6 py-16 text-center">
        <p className="text-muted">Your cart is empty.</p>
        <Link href="/menu" className="text-roast-dark underline mt-2 inline-block">Browse the menu</Link>
      </main>
    )
  }

  return (
    <main className="max-w-lg mx-auto px-6 py-12">
      <h1 className="text-3xl text-roast-dark mb-6">Your order</h1>
      <div className="divide-y divide-black/5">
        {lines.map((line) => (
          <div key={line.menuItemId} className="flex items-center justify-between py-3">
            <span>{line.name}</span>
            <div className="flex items-center gap-3">
              <button onClick={() => updateQty(line.menuItemId, -1)} className="w-7 h-7 rounded-full border border-black/10">-</button>
              <span className="w-4 text-center">{line.quantity}</span>
              <button onClick={() => updateQty(line.menuItemId, 1)} className="w-7 h-7 rounded-full border border-black/10">+</button>
              <span className="font-mono w-16 text-right">${(line.unitPrice * line.quantity).toFixed(2)}</span>
            </div>
          </div>
        ))}
      </div>
      <div className="flex justify-between py-4 font-medium">
        <span>Subtotal</span>
        <span className="font-mono">${subtotal().toFixed(2)}</span>
      </div>
      <Link href="/checkout" className="block text-center bg-roast text-paper py-3 rounded-md font-medium mt-4">
        Checkout
      </Link>
    </main>
  )
}
