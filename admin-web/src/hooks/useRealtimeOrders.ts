import { useEffect } from 'react'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '@/store/auth'

let echoInstance: Echo | null = null

function getEcho(token: string) {
  if (echoInstance) return echoInstance
  ;(window as any).Pusher = Pusher
  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${import.meta.env.VITE_API_URL}/broadcasting/auth`,
    auth: { headers: { Authorization: `Bearer ${token}` } },
  })
  return echoInstance
}

/** Subscribes to live order events for a branch and invalidates the orders
 *  query cache so the Orders board / KDS update instantly, no polling. */
export function useRealtimeOrders(tenantId: string, branchId: string | null) {
  const token = useAuthStore((s) => s.token)
  const queryClient = useQueryClient()

  useEffect(() => {
    if (!token || !branchId) return
    const echo = getEcho(token)
    const channelName = `tenant.${tenantId}.branch.${branchId}.orders`

    const channel = echo.private(channelName)
    channel.listen('.order.created', () => queryClient.invalidateQueries({ queryKey: ['orders'] }))
    channel.listen('.order.status_changed', () => queryClient.invalidateQueries({ queryKey: ['orders'] }))

    return () => { echo.leave(channelName) }
  }, [token, tenantId, branchId, queryClient])
}
