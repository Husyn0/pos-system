import { useState } from 'react'
import { View, Text, FlatList, TouchableOpacity, StyleSheet, Alert } from 'react-native'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/api/client'

interface MenuItem { id: string; name: string; base_price: string }
interface MenuCategory { id: string; name: string; items: MenuItem[] }

/** Waiter builds an order tableside; submits directly to the kitchen (status: confirmed). */
export default function WaiterOrderEntryScreen({ route, navigation }: any) {
  const { tableId, tableLabel } = route.params
  const [selected, setSelected] = useState<{ id: string; name: string; price: number; qty: number }[]>([])
  const branchId = process.env.EXPO_PUBLIC_BRANCH_ID

  const { data: categories } = useQuery<MenuCategory[]>({
    queryKey: ['menu'],
    queryFn: async () => (await api.get('/menu')).data,
  })

  function addItem(item: MenuItem) {
    setSelected((prev) => {
      const existing = prev.find((l) => l.id === item.id)
      if (existing) return prev.map((l) => l.id === item.id ? { ...l, qty: l.qty + 1 } : l)
      return [...prev, { id: item.id, name: item.name, price: Number(item.base_price), qty: 1 }]
    })
  }

  async function submitOrder() {
    try {
      await api.post('/orders', {
        branch_id: branchId,
        order_type: 'dine_in',
        source: 'mobile',
        table_id: tableId,
        idempotency_key: `${Date.now()}-${tableId}`,
        items: selected.map((l) => ({ menu_item_id: l.id, quantity: l.qty })),
      })
      Alert.alert('Sent to kitchen', `Order for ${tableLabel} placed.`)
      navigation.goBack()
    } catch {
      Alert.alert('Error', 'Could not submit the order.')
    }
  }

  const allItems = categories?.flatMap((c) => c.items) ?? []
  const total = selected.reduce((sum, l) => sum + l.price * l.qty, 0)

  return (
    <View style={{ flex: 1 }}>
      <Text style={styles.header}>{tableLabel}</Text>
      <FlatList
        contentContainerStyle={{ padding: 16 }}
        data={allItems}
        keyExtractor={(i) => i.id}
        renderItem={({ item }) => (
          <TouchableOpacity style={styles.row} onPress={() => addItem(item)}>
            <Text>{item.name}</Text>
            <Text style={{ color: '#8A7F73' }}>${Number(item.base_price).toFixed(2)}</Text>
          </TouchableOpacity>
        )}
      />
      {selected.length > 0 && (
        <TouchableOpacity style={styles.submitBtn} onPress={submitOrder}>
          <Text style={styles.submitText}>Send to kitchen — ${total.toFixed(2)}</Text>
        </TouchableOpacity>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  header: { fontSize: 20, fontWeight: '600', padding: 16, color: '#2E1F17' },
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 12, borderBottomWidth: 1, borderColor: '#00000010' },
  submitBtn: { backgroundColor: '#4A3226', padding: 16, alignItems: 'center' },
  submitText: { color: 'white', fontWeight: '600' },
})
