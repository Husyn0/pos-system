import { createContext, useContext, useEffect, useState, ReactNode } from 'react'
import * as SecureStore from 'expo-secure-store'
import { api } from '@/api/client'

interface AuthUser { id: string; name: string; role: 'staff' | 'customer' }
interface AuthContextValue {
  user: AuthUser | null
  loading: boolean
  loginStaff: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    // Tokens live in SecureStore (Keychain/Keystore-backed), never AsyncStorage,
    // since this may hold a staff session with order/refund permissions.
    SecureStore.getItemAsync('auth_token').then((token) => {
      if (token) api.get('/auth/me').then((res) => setUser({ ...res.data, role: 'staff' })).catch(() => {})
      setLoading(false)
    })
  }, [])

  async function loginStaff(email: string, password: string) {
    const { data } = await api.post('/auth/login', { email, password, device_name: 'mobile-app' })
    await SecureStore.setItemAsync('auth_token', data.token)
    setUser({ ...data.user, role: 'staff' })
  }

  async function logout() {
    await SecureStore.deleteItemAsync('auth_token')
    setUser(null)
  }

  return <AuthContext.Provider value={{ user, loading, loginStaff, logout }}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider')
  return ctx
}
