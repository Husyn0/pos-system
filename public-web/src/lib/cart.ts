import { create } from 'zustand'

export interface CartLine {
  menuItemId: string
  name: string
  unitPrice: number
  quantity: number
}

interface CartState {
  lines: CartLine[]
  add: (line: Omit<CartLine, 'quantity'>) => void
  updateQty: (menuItemId: string, delta: number) => void
  clear: () => void
  subtotal: () => number
}

// In-memory cart (per session). Swap for a persisted store keyed off a
// guest-session cookie if cart persistence across visits is needed.
export const useCartStore = create<CartState>((set, get) => ({
  lines: [],
  add: (line) => set((state) => {
    const existing = state.lines.find((l) => l.menuItemId === line.menuItemId)
    if (existing) {
      return { lines: state.lines.map((l) => l === existing ? { ...l, quantity: l.quantity + 1 } : l) }
    }
    return { lines: [...state.lines, { ...line, quantity: 1 }] }
  }),
  updateQty: (id, delta) => set((state) => ({
    lines: state.lines.map((l) => l.menuItemId === id ? { ...l, quantity: l.quantity + delta } : l).filter((l) => l.quantity > 0),
  })),
  clear: () => set({ lines: [] }),
  subtotal: () => get().lines.reduce((sum, l) => sum + l.unitPrice * l.quantity, 0),
}))
