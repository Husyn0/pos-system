export interface MenuItem {
  id: string
  category_id: string
  name: string
  description?: string
  base_price: string
  is_available: boolean
  variants?: { id: string; name: string; price_delta: string }[]
  modifier_groups?: ModifierGroup[]
}

export interface MenuCategory {
  id: string
  name: string
  sort_order: number
  items: MenuItem[]
}

export interface ModifierGroup {
  id: string
  name: string
  min_select: number
  max_select: number
  modifiers: { id: string; name: string; price_delta: string }[]
}

export interface OrderItem {
  id: string
  menu_item_id: string
  menu_item?: MenuItem
  quantity: number
  unit_price: string
  line_total: string
  status: string
  notes?: string
}

export interface Order {
  id: string
  order_number: string
  status: 'pending' | 'confirmed' | 'preparing' | 'ready' | 'completed' | 'cancelled'
  order_type: string
  subtotal: string
  discount_total: string
  tax_total: string
  total: string
  placed_at: string
  items: OrderItem[]
  table?: { id: string; label: string } | null
}

export interface CartLine {
  menuItem: MenuItem
  variantId?: string
  variantLabel?: string
  quantity: number
  modifierIds: string[]
  unitPrice: number
  notes?: string
}

export interface AuthUser {
  id: string
  name: string
  email: string
  branch_id: string | null
  roles: { name: string; permissions: { key: string }[] }[]
}
