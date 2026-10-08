const axios = require('axios')
const crypto = require('crypto')

/**
 * Outbox pattern: orders created offline are written to SQLite immediately
 * (instant UI, zero dependency on connectivity), then this service retries
 * pushing them to the cloud API. Each order carries a client-generated
 * idempotency_key, so a retried POST after a flaky connection can never
 * create a duplicate order server-side.
 */
class SyncService {
  constructor(store, apiUrl) {
    this.store = store
    this.client = axios.create({
      baseURL: apiUrl,
      timeout: 8000,
      headers: process.env.TENANT_ID ? { 'X-Tenant-Id': process.env.TENANT_ID } : {},
    })
  }

  async queueOrder(orderPayload) {
    const localId = crypto.randomUUID()
    const payload = { ...orderPayload, idempotency_key: orderPayload.idempotency_key || localId }
    this.store.enqueueOrder(localId, payload)

    // Optimistic best-effort immediate push; if it fails, flushOutbox()
    // will retry it on the next tick — but only if the failure looks like
    // "we're offline," not "the server actively rejected this order."
    try {
      await this.client.post('/orders', payload)
      this.store.markSynced(localId)
      return { localId, synced: true }
    } catch (err) {
      if (this.isRejection(err)) {
        // e.g. 409 insufficient_stock: by the time this offline order
        // reached the cloud, the ingredients it reserved locally may no
        // longer exist. Retrying forever would never succeed and would
        // hide a real problem — flag it for a human instead of silently
        // queuing. The register should surface this distinctly from a
        // normal "saved locally, will sync" message.
        this.store.markRejected(localId, err.response?.data?.message ?? 'Rejected by server')
        return { localId, synced: false, rejected: true, reason: err.response?.data }
      }
      return { localId, synced: false, rejected: false }
    }
  }

  async flushOutbox() {
    const pending = this.store.getPendingOrders()
    for (const row of pending) {
      try {
        await this.client.post('/orders', row.payload)
        this.store.markSynced(row.local_id)
      } catch (err) {
        if (this.isRejection(err)) {
          this.store.markRejected(row.local_id, err.response?.data?.message ?? 'Rejected by server')
        }
        // else: still offline / API down — leave it queued, try again next tick
      }
    }
  }

  /** A real HTTP response with a 4xx means the server actively evaluated
   *  and rejected the order (bad data, insufficient stock, etc.) — that's
   *  fundamentally different from "no response at all," which means we're
   *  offline or the API is down and a retry is the right move. */
  isRejection(err) {
    return err?.response?.status >= 400 && err.response.status < 500
  }
}

module.exports = { SyncService }
