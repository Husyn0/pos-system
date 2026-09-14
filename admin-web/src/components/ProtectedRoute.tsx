import { Navigate, Outlet } from 'react-router-dom'
import { useAuthStore } from '@/store/auth'
import { Sidebar } from './Sidebar'

export function ProtectedRoute() {
  const token = useAuthStore((s) => s.token)
  if (!token) return <Navigate to="/login" replace />

  return (
    <div className="flex">
      <Sidebar />
      <main className="flex-1 min-h-screen">
        <Outlet />
      </main>
    </div>
  )
}
