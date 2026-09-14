interface Props { label: string; value: string; sub?: string }

export function StatCard({ label, value, sub }: Props) {
  return (
    <div className="card p-5">
      <div className="text-sm text-muted">{label}</div>
      <div className="stat-number text-roast-dark mt-1">{value}</div>
      {sub && <div className="text-xs text-muted mt-1">{sub}</div>}
    </div>
  )
}
