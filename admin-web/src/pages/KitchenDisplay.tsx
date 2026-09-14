import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/api/client'
import { useAuthStore } from '@/store/auth'
import { useRealtimeOrders } from '@/hooks/useRealtimeOrders'
import type { Order } from '@/types'

/** Meant to run full-screen on a kitchen monitor — large touch targets, high contrast. */
export default function KitchenDisplay() {
  const branchId = useAuthStore((s) => s.branchId)
  const queryClient = useQueryClient()
  useRealtimeOrders('current', branchId)

  const { data } = useQuery<{ data: Order[] }>({
    queryKey: ['orders', branchId, 'kds'],
    queryFn: async () => (await api.get('/orders', { params: { branch_id: branchId, status: 'confirmed' } })).data,
    refetchInterval: 10_000,
  })

  const markReady = useMutation({
    mutationFn: (id: string) => api.patch(`/orders/${id}/status`, { status: 'ready' }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['orders'] }),
  })

  return (
    <div className="min-h-screen bg-roast-dark text-paper p-6">
      <h1 className="font-display text-2xl mb-6">Kitchen — Live Tickets</h1>
      <div className="grid grid-cols-4 gap-4">
        {data?.data.map((order) => (
          <div key={order.id} className="bg-paper/10 rounded-lg p-4">
            <div className="font-mono text-lg mb-2">{order.order_number}</div>
            <ul className="text-sm space-y-1 mb-4">
              {order.items.map((item) => (
                <li key={item.id}>{item.quantity}× {item.menu_item?.name} {item.notes && <em className="text-paper/60">({item.notes})</em>}</li>
              ))}
            </ul>
            <button onClick={() => markReady.mutate(order.id)} className="w-full bg-herb text-paper py-2 rounded font-medium">
              Ready
            </button>
          </div>
        ))}
      </div>
    </div>
  )
}
