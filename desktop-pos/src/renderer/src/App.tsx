import { useEffect, useState } from 'react'
import { RegisterScreen } from './pages/RegisterScreen'

export default function App() {
  const [hardwareOnline, setHardwareOnline] = useState<boolean | null>(null)

  useEffect(() => {
    const check = () => window.desktopBridge.getHardwareStatus().then((s) => setHardwareOnline(s.online))
    check()
    const interval = setInterval(check, 15000)
    return () => clearInterval(interval)
  }, [])

  return (
    <div style={{ fontFamily: 'Inter, sans-serif', height: '100vh', background: '#F7F5F1' }}>
      <div style={statusBarStyle(hardwareOnline)}>
        {hardwareOnline === null ? 'Checking hardware…' : hardwareOnline ? '● Printer & drawer connected' : '○ Hardware bridge offline — printing disabled'}
      </div>
      <RegisterScreen />
    </div>
  )
}

function statusBarStyle(online: boolean | null): React.CSSProperties {
  return {
    padding: '6px 16px',
    fontSize: 12,
    color: online ? '#3F6E52' : '#A63D2F',
    background: online ? '#3F6E5210' : '#A63D2F10',
  }
}
