import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/api/client'
import { Topbar } from '@/components/Topbar'
import type { MenuCategory } from '@/types'

export default function MenuManagement() {
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<{ categoryId: string } | null>(null)
  const [form, setForm] = useState({ name: '', base_price: '' })

  const { data: categories } = useQuery<MenuCategory[]>({
    queryKey: ['menu'],
    queryFn: async () => (await api.get('/menu')).data,
  })

  const toggleAvailability = useMutation({
    mutationFn: (id: string) => api.patch(`/menu/items/${id}/toggle-availability`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['menu'] }),
  })

  const createItem = useMutation({
    mutationFn: () => api.post('/menu/items', { category_id: editing?.categoryId, name: form.name, base_price: form.base_price }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['menu'] })
      setEditing(null)
      setForm({ name: '', base_price: '' })
    },
  })

  return (
    <div>
      <Topbar title="Menu" />
      <div className="p-8 space-y-8">
        {categories?.map((category) => (
          <div key={category.id}>
            <div className="flex items-center justify-between mb-3">
              <h2 className="font-display text-lg">{category.name}</h2>
              <button onClick={() => setEditing({ categoryId: category.id })} className="btn-ghost text-sm bg-black/5">
                + Add item
              </button>
            </div>

            <div className="card divide-y divide-black/5">
              {category.items.map((item) => (
                <div key={item.id} className="flex items-center justify-between px-5 py-3">
                  <div>
                    <div className="text-sm text-ink">{item.name}</div>
                    <div className="text-xs text-muted font-mono">${Number(item.base_price).toFixed(2)}</div>
                  </div>
                  <label className="flex items-center gap-2 text-xs text-muted cursor-pointer">
                    <input
                      type="checkbox"
                      checked={item.is_available}
                      onChange={() => toggleAvailability.mutate(item.id)}
                      className="accent-roast"
                    />
                    Available
                  </label>
                </div>
              ))}
              {category.items.length === 0 && (
                <div className="px-5 py-4 text-sm text-muted">No items in this category yet.</div>
              )}
            </div>

            {editing?.categoryId === category.id && (
              <form
                onSubmit={(e) => { e.preventDefault(); createItem.mutate() }}
                className="card p-4 mt-3 flex items-end gap-3"
              >
                <div className="flex-1">
                  <label className="text-xs text-muted">Name</label>
                  <input className="input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
                </div>
                <div className="w-32">
                  <label className="text-xs text-muted">Price</label>
                  <input className="input" type="number" step="0.01" value={form.base_price} onChange={(e) => setForm({ ...form, base_price: e.target.value })} required />
                </div>
                <button className="btn-primary">Save</button>
                <button type="button" onClick={() => setEditing(null)} className="btn-ghost">Cancel</button>
              </form>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}
