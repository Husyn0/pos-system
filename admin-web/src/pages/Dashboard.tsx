import { useQuery } from '@tanstack/react-query'
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, BarChart, Bar } from 'recharts'
import { api } from '@/api/client'
import { Topbar } from '@/components/Topbar'
import { StatCard } from '@/components/StatCard'
import { useAuthStore } from '@/store/auth'

export default function Dashboard() {
  const branchId = useAuthStore((s) => s.branchId)

  const { data } = useQuery({
    queryKey: ['analytics', 'dashboard', branchId],
    queryFn: async () => (await api.get('/analytics/dashboard', { params: { branch_id: branchId } })).data,
  })

  const { data: topItems } = useQuery({
    queryKey: ['analytics', 'top-items', branchId],
    queryFn: async () => (await api.get('/analytics/top-items', { params: { branch_id: branchId } })).data,
  })

  const today = data?.today
  const trend = data?.trend ?? []

  return (
    <div>
      <Topbar title="Dashboard" />
      <div className="p-8 space-y-8">
        <div className="grid grid-cols-4 gap-4">
          <StatCard label="Today's sales" value={`$${Number(today?.gross_sales ?? 0).toFixed(2)}`} />
          <StatCard label="Orders today" value={String(today?.orders_count ?? 0)} />
          <StatCard label="Avg. order value" value={`$${Number(today?.avg_order_value ?? 0).toFixed(2)}`} />
          <StatCard label="Range" value="30 days" sub="Adjustable per report" />
        </div>

        <div className="grid grid-cols-3 gap-6">
          <div className="card p-6 col-span-2">
            <h2 className="text-sm font-medium text-muted mb-4">Sales trend</h2>
            <ResponsiveContainer width="100%" height={260}>
              <LineChart data={trend}>
                <XAxis dataKey="summary_date" tick={{ fontSize: 11 }} tickFormatter={(d) => d?.slice(5)} />
                <YAxis tick={{ fontSize: 11 }} />
                <Tooltip />
                <Line type="monotone" dataKey="gross_sales" stroke="#4A3226" strokeWidth={2} dot={false} />
              </LineChart>
            </ResponsiveContainer>
          </div>

          <div className="card p-6">
            <h2 className="text-sm font-medium text-muted mb-4">Top items (revenue)</h2>
            <ResponsiveContainer width="100%" height={260}>
              <BarChart data={topItems ?? []} layout="vertical" margin={{ left: 24 }}>
                <XAxis type="number" hide />
                <YAxis dataKey="name" type="category" tick={{ fontSize: 11 }} width={90} />
                <Tooltip />
                <Bar dataKey="revenue" fill="#A6763D" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>
    </div>
  )
}
