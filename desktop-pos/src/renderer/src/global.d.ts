export interface DesktopBridge {
  createOfflineOrder: (order: unknown) => Promise<{ localId: string; synced: boolean }>
  getLocalMenu: () => Promise<any[]>
  printReceipt: (receipt: unknown) => Promise<{ ok: boolean }>
  openCashDrawer: () => Promise<{ ok: boolean }>
  getHardwareStatus: () => Promise<{ online: boolean }>
}

declare global {
  interface Window { desktopBridge: DesktopBridge }
}
