import { useEffect, useState } from 'react'

interface MenuItem { id: string; name: string; base_price: string }
interface CartLine { menuItem: MenuItem; quantity: number }

/**
 * The actual cashier-facing register UI. Functionally mirrors admin-web's
 * POS.tsx (same product, same flow) but drives checkout through the local
 * SQLite outbox + hardware bridge instead of calling the cloud API
 * directly and assuming a live receipt printer in the browser sandbox.
 */
export function RegisterScreen() {
  const [menu, setMenu] = useState<MenuItem[]>([])
  const [cart, setCart] = useState<CartLine[]>([])
  const [placing, setPlacing] = useState(false)

  useEffect(() => {
    window.desktopBridge.getLocalMenu().then((categories) => {
      setMenu(categories.flatMap((c: any) => c.items ?? []))
    })
  }, [])

  function addToCart(item: MenuItem) {
    setCart((prev) => {
      const existing = prev.find((l) => l.menuItem.id === item.id)
      if (existing) return prev.map((l) => l === existing ? { ...l, quantity: l.quantity + 1 } : l)
      return [...prev, { menuItem: item, quantity: 1 }]
    })
  }

  const subtotal = cart.reduce((sum, l) => sum + Number(l.menuItem.base_price) * l.quantity, 0)

  async function checkout() {
    setPlacing(true)
    try {
      const order = {
        order_type: 'takeaway',
        source: 'pos',
        items: cart.map((l) => ({ menu_item_id: l.menuItem.id, quantity: l.quantity })),
      }
      const result = await window.desktopBridge.createOfflineOrder(order)
      await window.desktopBridge.printReceipt({ lines: cart, total: subtotal })
      await window.desktopBridge.openCashDrawer()
      setCart([])
      if (!result.synced) {
        alert('Order saved locally — will sync automatically once back online.')
      }
    } finally {
      setPlacing(false)
    }
  }

  return (
    <div style={{ display: 'flex', height: 'calc(100vh - 32px)' }}>
      <div style={{ flex: 1, padding: 24, display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12, overflowY: 'auto' }}>
        {menu.map((item) => (
          <button key={item.id} onClick={() => addToCart(item)} style={tileStyle}>
            <div style={{ fontWeight: 500 }}>{item.name}</div>
            <div style={{ color: '#8A7F73', fontFamily: 'monospace', fontSize: 13 }}>${Number(item.base_price).toFixed(2)}</div>
          </button>
        ))}
      </div>

      <div style={{ width: 340, background: 'white', borderLeft: '1px solid #00000010', padding: 20, display: 'flex', flexDirection: 'column' }}>
        <h2 style={{ fontFamily: 'Georgia, serif', marginTop: 0 }}>Current order</h2>
        <div style={{ flex: 1, overflowY: 'auto' }}>
          {cart.map((l, i) => (
            <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', fontSize: 14 }}>
              <span>{l.quantity}× {l.menuItem.name}</span>
              <span style={{ fontFamily: 'monospace' }}>${(Number(l.menuItem.base_price) * l.quantity).toFixed(2)}</span>
            </div>
          ))}
        </div>
        <button
          onClick={checkout}
          disabled={cart.length === 0 || placing}
          style={{ background: '#4A3226', color: 'white', border: 'none', borderRadius: 8, padding: 14, fontWeight: 600, cursor: 'pointer', opacity: cart.length === 0 ? 0.4 : 1 }}
        >
          {placing ? 'Processing…' : `Charge $${subtotal.toFixed(2)} & Print`}
        </button>
      </div>
    </div>
  )
}

const tileStyle: React.CSSProperties = {
  background: 'white', border: '1px solid #00000010', borderRadius: 8, padding: 16, textAlign: 'left', cursor: 'pointer',
}
