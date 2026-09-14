import { apiFetch } from '@/lib/api'
import { AddToCartButton } from '@/components/AddToCartButton'

interface MenuItem { id: string; name: string; description?: string; base_price: string; is_available: boolean }
interface MenuCategory { id: string; name: string; items: MenuItem[] }

export default async function MenuPage() {
  const categories: MenuCategory[] = await apiFetch('/menu')

  return (
    <main className="max-w-3xl mx-auto px-6 py-12">
      <h1 className="text-3xl text-roast-dark mb-8">Menu</h1>
      {categories.map((category) => (
        <section key={category.id} className="mb-10">
          <h2 className="text-xl text-roast-dark mb-4">{category.name}</h2>
          <div className="divide-y divide-black/5">
            {category.items.map((item) => (
              <div key={item.id} className="flex items-center justify-between py-4">
                <div>
                  <div className="font-medium">{item.name}</div>
                  {item.description && <p className="text-sm text-muted mt-0.5 max-w-md">{item.description}</p>}
                  <div className="text-sm text-muted mt-1 font-mono">${Number(item.base_price).toFixed(2)}</div>
                </div>
                <AddToCartButton
                  menuItemId={item.id}
                  name={item.name}
                  price={Number(item.base_price)}
                  disabled={!item.is_available}
                />
              </div>
            ))}
          </div>
        </section>
      ))}
    </main>
  )
}
