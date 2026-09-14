const axios = require('axios')

/**
 * Talks to the local Spring Boot hardware-bridge service (see
 * /hardware-bridge) over HTTP on the LAN. This is the only thing in the
 * whole system that knows an actual printer/cash-drawer exists.
 */
class HardwareBridgeClient {
  constructor(baseUrl) {
    this.client = axios.create({ baseURL: baseUrl, timeout: 5000 })
  }

  async printReceipt(receipt) {
    try {
      const { data } = await this.client.post('/printers/receipt/print', receipt)
      return { ok: true, data }
    } catch (err) {
      return { ok: false, error: err.message }
    }
  }

  async openCashDrawer() {
    try {
      await this.client.post('/cash-drawer/open')
      return { ok: true }
    } catch (err) {
      return { ok: false, error: err.message }
    }
  }

  async getStatus() {
    try {
      const { data } = await this.client.get('/health')
      return { online: true, ...data }
    } catch {
      return { online: false }
    }
  }
}

module.exports = { HardwareBridgeClient }
