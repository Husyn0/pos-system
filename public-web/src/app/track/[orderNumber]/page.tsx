import { apiFetch } from '@/lib/api'

const STEPS = ['pending', 'confirmed', 'preparing', 'ready', 'completed']

export default async function TrackOrderPage({
  params, searchParams,
}: {
  params: { orderNumber: string }
  searchParams: { branch_id?: string }
}) {
  const order = await apiFetch(`/orders/track/${params.orderNumber}?branch_id=${searchParams.branch_id ?? ''}`)
  const currentStep = STEPS.indexOf(order.status)

  return (
    <main className="max-w-lg mx-auto px-6 py-16 text-center">
      <p className="text-muted text-sm">Order</p>
      <h1 className="text-4xl font-mono text-roast-dark mb-8">{order.order_number}</h1>

      <div className="flex justify-between mb-10">
        {STEPS.slice(0, 4).map((step, i) => (
          <div key={step} className="flex-1 flex flex-col items-center">
            <div className={`w-8 h-8 rounded-full mb-2 ${i <= currentStep ? 'bg-herb' : 'bg-black/10'}`} />
            <span className="text-xs capitalize text-muted">{step}</span>
          </div>
        ))}
      </div>

      <p className="text-muted">
        {order.status === 'ready'
          ? "It's ready for pickup!"
          : "We'll have it ready shortly — this page updates automatically."}
      </p>
    </main>
  )
}

export const revalidate = 10
