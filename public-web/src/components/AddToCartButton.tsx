'use client'
import { useCartStore } from '@/lib/cart'

export function AddToCartButton({ menuItemId, name, price, disabled }: { menuItemId: string; name: string; price: number; disabled?: boolean }) {
  const add = useCartStore((s) => s.add)
  return (
    <button
      onClick={() => add({ menuItemId, name, unitPrice: price })}
      disabled={disabled}
      className="text-sm px-4 py-1.5 rounded-full border border-roast/30 text-roast-dark hover:bg-roast hover:text-paper transition-colors disabled:opacity-30"
    >
      {disabled ? 'Sold out' : 'Add'}
    </button>
  )
}
