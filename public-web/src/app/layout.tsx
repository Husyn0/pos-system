import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'Brewline Coffee — Order Online',
  description: 'Order ahead for pickup, delivery, or dine-in.',
}

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <head>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
      </head>
      <body className="bg-paper text-ink font-sans">
        <header className="border-b border-black/5 px-6 py-4 flex items-center justify-between">
          <a href="/" className="font-display text-xl text-roast-dark">Brewline</a>
          <nav className="flex gap-6 text-sm text-ink/70">
            <a href="/menu">Menu</a>
            <a href="/reserve">Reserve a table</a>
            <a href="/cart">Cart</a>
          </nav>
        </header>
        {children}
      </body>
    </html>
  )
}
