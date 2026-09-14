import { Routes, Route } from 'react-router-dom'
import Login from '@/pages/Login'
import Dashboard from '@/pages/Dashboard'
import POS from '@/pages/POS'
import OrdersBoard from '@/pages/OrdersBoard'
import MenuManagement from '@/pages/MenuManagement'
import Inventory from '@/pages/Inventory'
import KitchenDisplay from '@/pages/KitchenDisplay'
import Settings from '@/pages/Settings'
import { ProtectedRoute } from '@/components/ProtectedRoute'

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/kds" element={<KitchenDisplay />} />

      <Route element={<ProtectedRoute />}>
        <Route path="/" element={<Dashboard />} />
        <Route path="/pos" element={<POS />} />
        <Route path="/orders" element={<OrdersBoard />} />
        <Route path="/menu" element={<MenuManagement />} />
        <Route path="/inventory" element={<Inventory />} />
        <Route path="/settings" element={<Settings />} />
      </Route>
    </Routes>
  )
}
