import { create } from 'zustand'
import type { AuthUser } from '@/types'

interface AuthState {
  token: string | null
  user: AuthUser | null
  branchId: string | null
  setSession: (token: string, user: AuthUser) => void
  logout: () => void
  hasPermission: (key: string) => boolean
}

// Note: this uses in-memory state only. A real deployment would persist
// the token in an httpOnly cookie set by the backend, or (if using
// localStorage) accept the XSS tradeoff explicitly — never assume it's safe
// by default.
export const useAuthStore = create<AuthState>((set, get) => ({
  token: null,
  user: null,
  branchId: null,
  setSession: (token, user) => set({ token, user, branchId: user.branch_id }),
  logout: () => set({ token: null, user: null, branchId: null }),
  hasPermission: (key) => {
    const user = get().user
    if (!user) return false
    return user.roles.some((r) => r.permissions.some((p) => p.key === key))
  },
}))
