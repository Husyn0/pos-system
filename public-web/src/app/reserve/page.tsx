'use client'
import { useState } from 'react'
import { apiFetch } from '@/lib/api'

export default function ReservePage() {
  const [form, setForm] = useState({ guest_name: '', guest_phone: '', party_size: 2, reserved_at: '' })
  const [confirmed, setConfirmed] = useState(false)
  const branchId = process.env.NEXT_PUBLIC_BRANCH_ID

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    await apiFetch('/reservations', {
      method: 'POST',
      body: JSON.stringify({ ...form, branch_id: branchId }),
    })
    setConfirmed(true)
  }

  if (confirmed) {
    return (
      <main className="max-w-md mx-auto px-6 py-16 text-center">
        <h1 className="text-2xl text-roast-dark mb-2">You're booked!</h1>
        <p className="text-muted">We've sent a confirmation to your phone.</p>
      </main>
    )
  }

  return (
    <main className="max-w-md mx-auto px-6 py-12">
      <h1 className="text-3xl text-roast-dark mb-6">Reserve a table</h1>
      <form onSubmit={handleSubmit} className="space-y-4">
        <input required placeholder="Name" className="w-full border border-black/10 rounded-md px-3 py-2"
          value={form.guest_name} onChange={(e) => setForm({ ...form, guest_name: e.target.value })} />
        <input required placeholder="Phone" className="w-full border border-black/10 rounded-md px-3 py-2"
          value={form.guest_phone} onChange={(e) => setForm({ ...form, guest_phone: e.target.value })} />
        <input required type="number" min={1} placeholder="Party size" className="w-full border border-black/10 rounded-md px-3 py-2"
          value={form.party_size} onChange={(e) => setForm({ ...form, party_size: Number(e.target.value) })} />
        <input required type="datetime-local" className="w-full border border-black/10 rounded-md px-3 py-2"
          value={form.reserved_at} onChange={(e) => setForm({ ...form, reserved_at: e.target.value })} />
        <button className="w-full bg-roast text-paper py-3 rounded-md font-medium">Reserve</button>
      </form>
    </main>
  )
}
