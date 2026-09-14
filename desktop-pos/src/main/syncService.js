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
    this.client = axios.create({ baseURL: apiUrl, timeout: 8000 })
  }

  async queueOrder(orderPayload) {
    const localId = crypto.randomUUID()
    const payload = { ...orderPayload, idempotency_key: orderPayload.idempotency_key || localId }
    this.store.enqueueOrder(localId, payload)

    // Optimistic best-effort immediate push; if it fails, flushOutbox()
    // will retry it on the next tick regardless.
    try {
      await this.client.post('/orders', payload)
      this.store.markSynced(localId)
      return { localId, synced: true }
    } catch {
      return { localId, synced: false }
    }
  }

  async flushOutbox() {
    const pending = this.store.getPendingOrders()
    for (const row of pending) {
      try {
        await this.client.post('/orders', row.payload)
        this.store.markSynced(row.local_id)
      } catch {
        // still offline / API down — leave it queued, try again next tick
      }
    }
  }
}

module.exports = { SyncService }
