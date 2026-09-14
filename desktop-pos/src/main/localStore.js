const Database = require('better-sqlite3')
const path = require('path')
const { app } = require('electron')

/**
 * Local SQLite mirror: menu (read cache, synced from cloud) + an outbox
 * table for orders created while offline. This is what lets the register
 * keep ringing up sales through a WiFi outage.
 */
class LocalStore {
  init() {
    const dbPath = path.join(app.getPath('userData'), 'brewline-local.db')
    this.db = new Database(dbPath)
    this.db.pragma('journal_mode = WAL')

    this.db.exec(`
      CREATE TABLE IF NOT EXISTS menu_cache (
        id TEXT PRIMARY KEY,
        payload TEXT NOT NULL,
        updated_at TEXT NOT NULL
      );
      CREATE TABLE IF NOT EXISTS outbox_orders (
        local_id TEXT PRIMARY KEY,
        payload TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        created_at TEXT NOT NULL,
        synced_at TEXT
      );
    `)
  }

  saveMenu(categories) {
    const stmt = this.db.prepare('INSERT OR REPLACE INTO menu_cache (id, payload, updated_at) VALUES (?, ?, ?)')
    const now = new Date().toISOString()
    const tx = this.db.transaction((rows) => rows.forEach((c) => stmt.run(c.id, JSON.stringify(c), now)))
    tx(categories)
  }

  getMenu() {
    const rows = this.db.prepare('SELECT payload FROM menu_cache').all()
    return rows.map((r) => JSON.parse(r.payload))
  }

  enqueueOrder(localId, payload) {
    this.db.prepare('INSERT INTO outbox_orders (local_id, payload, status, created_at) VALUES (?, ?, ?, ?)')
      .run(localId, JSON.stringify(payload), 'pending', new Date().toISOString())
  }

  getPendingOrders() {
    return this.db.prepare("SELECT * FROM outbox_orders WHERE status = 'pending'").all()
      .map((r) => ({ ...r, payload: JSON.parse(r.payload) }))
  }

  markSynced(localId) {
    this.db.prepare("UPDATE outbox_orders SET status = 'synced', synced_at = ? WHERE local_id = ?")
      .run(new Date().toISOString(), localId)
  }
}

module.exports = { LocalStore }
