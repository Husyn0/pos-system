const { contextBridge, ipcRenderer } = require('electron')

// Whitelisted, typed surface exposed to the renderer — the React UI can
// never reach Node/filesystem/hardware APIs directly, only these calls.
contextBridge.exposeInMainWorld('desktopBridge', {
  createOfflineOrder: (order) => ipcRenderer.invoke('orders:createOffline', order),
  getLocalMenu: () => ipcRenderer.invoke('orders:getLocalMenu'),
  printReceipt: (receipt) => ipcRenderer.invoke('hardware:printReceipt', receipt),
  openCashDrawer: () => ipcRenderer.invoke('hardware:openCashDrawer'),
  getHardwareStatus: () => ipcRenderer.invoke('hardware:bridgeStatus'),
})
