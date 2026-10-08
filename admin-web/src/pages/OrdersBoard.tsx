import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/api/client'
import { Topbar } from '@/components/Topbar'
import { useAuthStore } from '@/store/auth'
import { useRealtimeOrders } from '@/hooks/useRealtimeOrders'
import type { Order } from '@/types'

const COLUMNS: { status: Order['status']; label: string }[] = [
  { status: 'pending', label: 'New' },
  { status: 'confirmed', label: 'Confirmed' },
  { status: 'preparing', label: 'Preparing' },
  { status: 'ready', label: 'Ready' },
]

export default function OrdersBoard() {
  const branchId = useAuthStore((s) => s.branchId)
  const queryClient = useQueryClient()
  useRealtimeOrders('current', branchId) // tenant id resolved server-side; swap in real id if multi-tenant UI needs it client-side

  const { data } = useQuery<{ data: Order[] }>({
    queryKey: ['orders', branchId],
    queryFn: async () => (await api.get('/orders', { params: { branch_id: branchId } })).data,
    refetchInterval: 15_000, // belt-and-suspenders in case a websocket event is missed
  })

  const advance = useMutation({
    mutationFn: async ({ id, status }: { id: string; status: string }) =>
      api.patch(`/orders/${id}/status`, { status }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['orders'] }),
  })

  // Cancellation is a dedicated endpoint, not a status PATCH: a paid order
  // requires an explicit auto_refund confirmation before it can cancel —
  // see docs/BUSINESS_RULES.md. This keeps "cancel a paid order" a visible,
  // deliberate action rather than a side effect of a generic status change.
  const cancel = useMutation({
    mutationFn: async (order: Order) => {
      const hasPayments = order.status !== 'pending' // heuristic for the confirm prompt; server is the real authority
      const autoRefund = hasPayments
        ? confirm('This order may have a payment on it. Refund it automatically as part of cancelling?')
        : false
      return api.post(`/orders/${order.id}/cancel`, { reason: 'Cancelled from Orders board', auto_refund: autoRefund })
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['orders'] }),
    onError: (err: any) => alert(err?.response?.data?.message ?? 'Could not cancel this order.'),
  })

  const orders = data?.data ?? []
  const nextStatus: Record<string, string> = {
    pending: 'confirmed',
    confirmed: 'preparing',
    preparing: 'ready',
    ready: 'completed',
  }

  return (
    <div>
      <Topbar title="Orders" />
      <div className="p-8 grid grid-cols-4 gap-5">
        {COLUMNS.map((col) => (
          <div key={col.status}>
            <h3 className="text-sm font-medium text-muted mb-3">{col.label}</h3>
            <div className="space-y-3">
              {orders.filter((o) => o.status === col.status).map((order) => (
                <div key={order.id} className="card p-4">
                  <div className="flex justify-between items-baseline">
                    <span className="font-mono text-sm font-medium">{order.order_number}</span>
                    <span className="font-mono text-sm text-muted">${Number(order.total).toFixed(2)}</span>
                  </div>
                  <ul className="text-xs text-muted mt-2 space-y-0.5">
                    {order.items.map((item) => (
                      <li key={item.id}>{item.quantity}× {item.menu_item?.name}</li>
                    ))}
                  </ul>
                  <div className="flex gap-2 mt-3">
                    <button
                      onClick={() => advance.mutate({ id: order.id, status: nextStatus[order.status] })}
                      className="btn-ghost text-xs flex-1 text-center bg-black/5"
                    >
                      Mark as {nextStatus[order.status]} →
                    </button>
                    <button
                      onClick={() => cancel.mutate(order)}
                      className="btn-ghost text-xs text-brick bg-brick/5"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
