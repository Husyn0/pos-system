import { createContext, useContext, useState, ReactNode } from 'react'

export interface CartLine { menuItemId: string; name: string; unitPrice: number; quantity: number }

interface CartContextValue {
  lines: CartLine[]
  addItem: (line: Omit<CartLine, 'quantity'>) => void
  updateQty: (id: string, delta: number) => void
  clear: () => void
  subtotal: number
}

const CartContext = createContext<CartContextValue | null>(null)

export function CartProvider({ children }: { children: ReactNode }) {
  const [lines, setLines] = useState<CartLine[]>([])

  function addItem(line: Omit<CartLine, 'quantity'>) {
    setLines((prev) => {
      const existing = prev.find((l) => l.menuItemId === line.menuItemId)
      if (existing) return prev.map((l) => l === existing ? { ...l, quantity: l.quantity + 1 } : l)
      return [...prev, { ...line, quantity: 1 }]
    })
  }

  function updateQty(id: string, delta: number) {
    setLines((prev) => prev.map((l) => l.menuItemId === id ? { ...l, quantity: l.quantity + delta } : l).filter((l) => l.quantity > 0))
  }

  const subtotal = lines.reduce((sum, l) => sum + l.unitPrice * l.quantity, 0)

  return <CartContext.Provider value={{ lines, addItem, updateQty, clear: () => setLines([]), subtotal }}>{children}</CartContext.Provider>
}

export function useCart() {
  const ctx = useContext(CartContext)
  if (!ctx) throw new Error('useCart must be used inside CartProvider')
  return ctx
}
