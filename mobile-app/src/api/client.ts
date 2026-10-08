import axios from 'axios'
import * as SecureStore from 'expo-secure-store'

export const api = axios.create({
  baseURL: process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000/api',
})

api.interceptors.request.use(async (config) => {
  const token = await SecureStore.getItemAsync('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  const tenantId = process.env.EXPO_PUBLIC_TENANT_ID
  if (tenantId) config.headers['X-Tenant-Id'] = tenantId
  return config
})
