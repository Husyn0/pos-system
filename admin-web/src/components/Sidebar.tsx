import { NavLink } from 'react-router-dom'
import { LayoutDashboard, Coffee, ClipboardList, Package, MonitorSmartphone, Settings } from 'lucide-react'
import { useAuthStore } from '@/store/auth'

const nav = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/pos', label: 'Register', icon: Coffee },
  { to: '/orders', label: 'Orders', icon: ClipboardList },
  { to: '/menu', label: 'Menu', icon: Coffee },
  { to: '/inventory', label: 'Inventory', icon: Package },
  { to: '/kds', label: 'Kitchen Display', icon: MonitorSmartphone },
  { to: '/settings', label: 'Settings', icon: Settings },
]

export function Sidebar() {
  const user = useAuthStore((s) => s.user)

  return (
    <aside className="w-60 shrink-0 bg-roast-dark text-paper/90 flex flex-col h-screen sticky top-0">
      <div className="px-5 py-6">
        <div className="font-display text-xl text-paper">Brewline</div>
        <div className="text-xs text-paper/50 mt-0.5">Point of Sale</div>
      </div>
      <nav className="flex-1 px-2 space-y-0.5">
        {nav.map(({ to, label, icon: Icon }) => (
          <NavLink
            key={to}
            to={to}
            end={to === '/'}
            className={({ isActive }) =>
              `flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors ${
                isActive ? 'bg-paper/10 text-paper' : 'text-paper/60 hover:bg-paper/5 hover:text-paper/90'
              }`
            }
          >
            <Icon size={17} strokeWidth={1.75} />
            {label}
          </NavLink>
        ))}
      </nav>
      <div className="px-5 py-4 border-t border-paper/10 text-sm">
        <div className="text-paper/90">{user?.name}</div>
        <div className="text-paper/40 text-xs">{user?.roles?.[0]?.name}</div>
      </div>
    </aside>
  )
}
