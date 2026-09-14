import { View, Text, FlatList, TouchableOpacity, StyleSheet } from 'react-native'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/api/client'
import { useCart } from '@/context/CartContext'

interface MenuItem { id: string; name: string; base_price: string; is_available: boolean }
interface MenuCategory { id: string; name: string; items: MenuItem[] }

/** Customer-facing "order ahead" screen — same API as public-web, native UI. */
export default function MenuScreen() {
  const { addItem } = useCart()
  const { data: categories } = useQuery<MenuCategory[]>({
    queryKey: ['menu'],
    queryFn: async () => (await api.get('/menu')).data,
  })

  return (
    <FlatList
      contentContainerStyle={{ padding: 16 }}
      data={categories ?? []}
      keyExtractor={(c) => c.id}
      renderItem={({ item: category }) => (
        <View style={{ marginBottom: 20 }}>
          <Text style={styles.categoryTitle}>{category.name}</Text>
          {category.items.map((item) => (
            <TouchableOpacity
              key={item.id}
              style={styles.row}
              disabled={!item.is_available}
              onPress={() => addItem({ menuItemId: item.id, name: item.name, unitPrice: Number(item.base_price) })}
            >
              <Text style={styles.itemName}>{item.name}</Text>
              <Text style={styles.itemPrice}>${Number(item.base_price).toFixed(2)}</Text>
            </TouchableOpacity>
          ))}
        </View>
      )}
    />
  )
}

const styles = StyleSheet.create({
  categoryTitle: { fontSize: 18, fontWeight: '600', color: '#2E1F17', marginBottom: 8 },
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 12, borderBottomWidth: 1, borderColor: '#00000010' },
  itemName: { fontSize: 15, color: '#241C17' },
  itemPrice: { fontSize: 14, color: '#8A7F73' },
})
