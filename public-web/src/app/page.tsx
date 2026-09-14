import Link from 'next/link'

export default function Home() {
  return (
    <main className="max-w-3xl mx-auto px-6 py-20 text-center">
      <h1 className="text-5xl text-roast-dark leading-tight">Good coffee,<br />ordered ahead.</h1>
      <p className="text-muted mt-4 max-w-md mx-auto">
        Skip the line — browse the menu, pay online, and pick it up when it's ready.
      </p>
      <Link href="/menu" className="inline-block mt-8 bg-roast text-paper px-6 py-3 rounded-md font-medium">
        View menu
      </Link>
    </main>
  )
}
