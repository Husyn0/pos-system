import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Minus, Trash2, Printer } from 'lucide-react'
import { api } from '@/api/client'
import { useAuthStore } from '@/store/auth'
import type { MenuCategory, MenuItem, CartLine } from '@/types'

/**
 * The in-browser register screen. This is what runs on a tablet/terminal
 * that doesn't need physical hardware. For sites with a receipt printer /
 * cash drawer, the Electron desktop-pos app wraps this same flow and adds
 * calls to the local hardware bridge on checkout.
 */
export default function POS() {
  const branchId = useAuthStore((s) => s.branchId)
  const queryClient = useQueryClient()
  const [cart, setCart] = useState<CartLine[]>([])
  const [activeCategory, setActiveCategory] = useState<string | null>(null)

  const { data: categories } = useQuery<MenuCategory[]>({
    queryKey: ['menu'],
    queryFn: async () => (await api.get('/menu')).data,
  })

  const placeOrder = useMutation({
    mutationFn: async () => {
      const payload = {
        branch_id: branchId,
        order_type: 'takeaway',
        source: 'pos',
        idempotency_key: crypto.randomUUID(),
        items: cart.map((line) => ({
          menu_item_id: line.menuItem.id,
          item_variant_id: line.variantId,
          quantity: line.quantity,
          modifier_ids: line.modifierIds,
          notes: line.notes,
        })),
      }
      const { data: order } = await api.post('/orders', payload)
      // Cash tender for demo purposes; a card tender would come from the
      // hardware bridge / payment terminal SDK instead.
      await api.post(`/orders/${order.id}/payments`, { method: 'cash', amount: order.total })
      return order
    },
    onSuccess: () => {
      setCart([])
      queryClient.invalidateQueries({ queryKey: ['orders'] })
    },
  })

  function addToCart(item: MenuItem) {
    setCart((prev) => {
      const existing = prev.find((l) => l.menuItem.id === item.id && !l.variantId)
      if (existing) {
        return prev.map((l) => (l === existing ? { ...l, quantity: l.quantity + 1 } : l))
      }
      return [...prev, { menuItem: item, quantity: 1, modifierIds: [], unitPrice: Number(item.base_price) }]
    })
  }

  function updateQty(index: number, delta: number) {
    setCart((prev) =>
      prev
        .map((l, i) => (i === index ? { ...l, quantity: l.quantity + delta } : l))
        .filter((l) => l.quantity > 0)
    )
  }

  const activeCategoryData = categories?.find((c) => c.id === activeCategory) ?? categories?.[0]
  const subtotal = cart.reduce((sum, l) => sum + l.unitPrice * l.quantity, 0)

  return (
    <div className="flex h-screen">
      {/* Menu grid */}
      <div className="flex-1 flex flex-col">
        <div className="flex gap-2 px-6 pt-6 overflow-x-auto">
          {categories?.map((cat) => (
            <button
              key={cat.id}
              onClick={() => setActiveCategory(cat.id)}
              className={`px-4 py-2 rounded-full text-sm whitespace-nowrap transition-colors ${
                (activeCategoryData?.id === cat.id) ? 'bg-roast text-paper' : 'bg-black/5 text-ink/70 hover:bg-black/10'
              }`}
            >
              {cat.name}
            </button>
          ))}
        </div>

        <div className="grid grid-cols-4 gap-4 p-6 overflow-y-auto">
          {activeCategoryData?.items.map((item) => (
            <button
              key={item.id}
              onClick={() => addToCart(item)}
              disabled={!item.is_available}
              className="card p-4 text-left hover:border-roast/40 hover:shadow-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <div className="font-medium text-ink">{item.name}</div>
              <div className="text-muted text-sm mt-1 font-mono">${Number(item.base_price).toFixed(2)}</div>
              {!item.is_available && <div className="text-brick text-xs mt-1">86'd</div>}
            </button>
          ))}
        </div>
      </div>

      {/* Cart panel */}
      <div className="w-96 shrink-0 bg-white border-l border-black/5 flex flex-col">
        <div className="px-6 py-5 border-b border-black/5">
          <h2 className="font-display text-lg">Current order</h2>
        </div>

        <div className="flex-1 overflow-y-auto px-6 py-4 space-y-3">
          {cart.length === 0 && <p className="text-muted text-sm">Tap an item to add it.</p>}
          {cart.map((line, i) => (
            <div key={i} className="flex items-center justify-between gap-2">
              <div className="flex-1">
                <div className="text-sm text-ink">{line.menuItem.name}</div>
                <div className="text-xs text-muted font-mono">${(line.unitPrice * line.quantity).toFixed(2)}</div>
              </div>
              <div className="flex items-center gap-1">
                <button onClick={() => updateQty(i, -1)} className="p-1 rounded hover:bg-black/5"><Minus size={14} /></button>
                <span className="w-5 text-center text-sm tabular-nums">{line.quantity}</span>
                <button onClick={() => updateQty(i, 1)} className="p-1 rounded hover:bg-black/5"><Plus size={14} /></button>
              </div>
            </div>
          ))}
        </div>

        <div className="px-6 py-5 border-t border-black/5 space-y-3">
          <div className="flex justify-between text-sm text-muted">
            <span>Subtotal</span>
            <span className="font-mono">${subtotal.toFixed(2)}</span>
          </div>
          <button
            onClick={() => placeOrder.mutate()}
            disabled={cart.length === 0 || placeOrder.isPending}
            className="btn-primary w-full flex items-center justify-center gap-2 disabled:opacity-40"
          >
            <Printer size={16} />
            {placeOrder.isPending ? 'Placing order…' : `Charge $${subtotal.toFixed(2)}`}
          </button>
        </div>
      </div>
    </div>
  )
}
