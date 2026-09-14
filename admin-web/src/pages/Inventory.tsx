import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle } from 'lucide-react'
import { api } from '@/api/client'
import { Topbar } from '@/components/Topbar'
import { useAuthStore } from '@/store/auth'

interface InventoryItem {
  id: string; name: string; unit: string; quantity_on_hand: string; reorder_level: string
}

export default function Inventory() {
  const branchId = useAuthStore((s) => s.branchId)
  const queryClient = useQueryClient()

  const { data: items } = useQuery<InventoryItem[]>({
    queryKey: ['inventory', branchId],
    queryFn: async () => (await api.get('/inventory', { params: { branch_id: branchId } })).data,
  })

  const adjust = useMutation({
    mutationFn: ({ id, delta }: { id: string; delta: number }) =>
      api.post(`/inventory/${id}/adjust`, { quantity_delta: delta, type: 'adjustment', note: 'Manual adjustment from dashboard' }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['inventory'] }),
  })

  return (
    <div>
      <Topbar title="Inventory" />
      <div className="p-8">
        <div className="card divide-y divide-black/5">
          <div className="grid grid-cols-5 px-5 py-3 text-xs text-muted font-medium">
            <span className="col-span-2">Ingredient</span>
            <span>On hand</span>
            <span>Reorder at</span>
            <span></span>
          </div>
          {items?.map((item) => {
            const low = Number(item.quantity_on_hand) <= Number(item.reorder_level)
            return (
              <div key={item.id} className="grid grid-cols-5 px-5 py-3 items-center text-sm">
                <span className="col-span-2 flex items-center gap-2">
                  {low && <AlertTriangle size={14} className="text-brick" />}
                  {item.name}
                </span>
                <span className="font-mono">{item.quantity_on_hand} {item.unit}</span>
                <span className="font-mono text-muted">{item.reorder_level} {item.unit}</span>
                <div className="flex gap-2 justify-end">
                  <button onClick={() => adjust.mutate({ id: item.id, delta: -1 })} className="btn-ghost text-xs bg-black/5">-1</button>
                  <button onClick={() => adjust.mutate({ id: item.id, delta: 1 })} className="btn-ghost text-xs bg-black/5">+1</button>
                </div>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}
