const { app, BrowserWindow, ipcMain } = require('electron')
const path = require('path')
const { LocalStore } = require('./localStore')
const { SyncService } = require('./syncService')
const { HardwareBridgeClient } = require('./hardwareBridgeClient')

let mainWindow
const store = new LocalStore()
const bridge = new HardwareBridgeClient(process.env.HARDWARE_BRIDGE_URL || 'http://localhost:8088')
const sync = new SyncService(store, process.env.API_URL || 'http://localhost:8000/api')

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  })

  const devServerUrl = process.env.VITE_DEV_SERVER_URL
  if (devServerUrl) {
    mainWindow.loadURL(devServerUrl)
  } else {
    mainWindow.loadFile(path.join(__dirname, '../../dist/index.html'))
  }
}

app.whenReady().then(() => {
  store.init()
  createWindow()
  // Background sync loop: push queued offline orders every 10s once online.
  setInterval(() => sync.flushOutbox().catch(() => {}), 10_000)
})

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit()
})

/* ---- IPC bridge: renderer (React UI) never touches hardware/SQLite
   directly — it goes through these typed, validated channels. ---- */

ipcMain.handle('orders:createOffline', async (_event, orderPayload) => {
  // Note: insufficient-stock (409) and payment validation errors can only be
  // detected once this order actually reaches the cloud API — by design, the
  // local SQLite outbox doesn't know current stock levels. If the immediate
  // push in queueOrder() fails for that reason (vs. simply being offline),
  // the renderer should treat 'synced: false' + a 4xx as "needs attention"
  // rather than silently queuing it as a network retry. See syncService.js.
  return sync.queueOrder(orderPayload)
})

ipcMain.handle('orders:getLocalMenu', async () => {
  return store.getMenu()
})

ipcMain.handle('hardware:printReceipt', async (_event, receipt) => {
  return bridge.printReceipt(receipt)
})

ipcMain.handle('hardware:openCashDrawer', async () => {
  return bridge.openCashDrawer()
})

ipcMain.handle('hardware:bridgeStatus', async () => {
  return bridge.getStatus()
})
