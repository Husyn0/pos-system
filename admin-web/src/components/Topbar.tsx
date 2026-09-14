interface Props { title: string; actions?: React.ReactNode }

export function Topbar({ title, actions }: Props) {
  return (
    <header className="flex items-center justify-between px-8 py-5 border-b border-black/5 bg-paper/80 backdrop-blur sticky top-0 z-10">
      <h1 className="text-2xl font-display text-ink">{title}</h1>
      <div className="flex items-center gap-3">{actions}</div>
    </header>
  )
}
