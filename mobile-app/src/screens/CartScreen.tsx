import { View, Text, FlatList, TouchableOpacity, StyleSheet, Alert } from 'react-native'
import { useCart } from '@/context/CartContext'
import { api } from '@/api/client'

export default function CartScreen({ navigation }: any) {
  const { lines, updateQty, subtotal, clear } = useCart()
  const branchId = process.env.EXPO_PUBLIC_BRANCH_ID

  async function placeOrder() {
    try {
      const { data: order } = await api.post('/orders', {
        branch_id: branchId,
        order_type: 'online_pickup',
        source: 'mobile',
        idempotency_key: `${Date.now()}-${Math.random()}`,
        items: lines.map((l) => ({ menu_item_id: l.menuItemId, quantity: l.quantity })),
      })
      clear()
      navigation.navigate('OrderTracking', { orderNumber: order.order_number })
    } catch {
      Alert.alert('Something went wrong', 'Please try again.')
    }
  }

  return (
    <View style={{ flex: 1, padding: 16 }}>
      <FlatList
        data={lines}
        keyExtractor={(l) => l.menuItemId}
        renderItem={({ item }) => (
          <View style={styles.row}>
            <Text>{item.name}</Text>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
              <TouchableOpacity onPress={() => updateQty(item.menuItemId, -1)}><Text style={styles.qtyBtn}>–</Text></TouchableOpacity>
              <Text>{item.quantity}</Text>
              <TouchableOpacity onPress={() => updateQty(item.menuItemId, 1)}><Text style={styles.qtyBtn}>+</Text></TouchableOpacity>
            </View>
          </View>
        )}
        ListEmptyComponent={<Text style={{ color: '#8A7F73', textAlign: 'center', marginTop: 40 }}>Your cart is empty.</Text>}
      />
      {lines.length > 0 && (
        <TouchableOpacity style={styles.checkoutBtn} onPress={placeOrder}>
          <Text style={styles.checkoutText}>Place order — ${subtotal.toFixed(2)}</Text>
        </TouchableOpacity>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 12, borderBottomWidth: 1, borderColor: '#00000010' },
  qtyBtn: { fontSize: 18, width: 24, textAlign: 'center', color: '#4A3226' },
  checkoutBtn: { backgroundColor: '#4A3226', padding: 16, borderRadius: 8, alignItems: 'center', marginTop: 12 },
  checkoutText: { color: 'white', fontWeight: '600' },
})
