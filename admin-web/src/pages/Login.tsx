import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '@/api/client'
import { useAuthStore } from '@/store/auth'

export default function Login() {
  const [email, setEmail] = useState('owner@demo-coffee.test')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const setSession = useAuthStore((s) => s.setSession)
  const navigate = useNavigate()

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError(null)
    try {
      const { data } = await api.post('/auth/login', { email, password, device_name: 'admin-web' })
      setSession(data.token, data.user)
      navigate('/')
    } catch {
      setError('Invalid email or password.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen grid place-items-center bg-paper px-4">
      <form onSubmit={handleSubmit} className="w-full max-w-sm card p-8">
        <div className="font-display text-2xl text-roast-dark mb-1">Brewline</div>
        <p className="text-sm text-muted mb-6">Sign in to manage your store.</p>

        <label className="block text-sm mb-1.5 text-ink/80">Email</label>
        <input className="input mb-4" value={email} onChange={(e) => setEmail(e.target.value)} type="email" required />

        <label className="block text-sm mb-1.5 text-ink/80">Password</label>
        <input className="input mb-2" value={password} onChange={(e) => setPassword(e.target.value)} type="password" required />

        {error && <p className="text-brick text-sm mt-2">{error}</p>}

        <button className="btn-primary w-full mt-6" disabled={loading}>
          {loading ? 'Signing in…' : 'Sign in'}
        </button>
      </form>
    </div>
  )
}
