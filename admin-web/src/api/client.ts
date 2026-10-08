import axios from 'axios'
import { useAuthStore } from '@/store/auth'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
})

// Attach the bearer token (and resolve the tenant by subdomain in prod;
// locally we just hit the API host directly).
api.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token
  if (token) config.headers.Authorization = `Bearer ${token}`
  // Native/API clients identify their tenant explicitly rather than via
  // subdomain (see backend ResolveTenant middleware docblock) — this is
  // resolved once at deploy/config time per storefront.
  const tenantId = import.meta.env.VITE_TENANT_ID
  if (tenantId) config.headers['X-Tenant-Id'] = tenantId
  return config
})

// A 401 anywhere means the token is dead — bounce to login rather than
// showing a confusing half-broken screen.
api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      useAuthStore.getState().logout()
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)
