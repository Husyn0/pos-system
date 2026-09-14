import { View, Text, FlatList, TouchableOpacity, StyleSheet } from 'react-native'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/api/client'
import { useAuth } from '@/context/AuthContext'

interface DiningTable { id: string; label: string; status: string; capacity: number }

/** Waiter mode: pick a table to open an order for. Requires staff login. */
export default function WaiterTablesScreen({ navigation }: any) {
  const { data: tables } = useQuery<DiningTable[]>({
    queryKey: ['tables'],
    queryFn: async () => (await api.get('/tables')).data, // add a lightweight index route on DiningTable if not present
  })

  const statusColor: Record<string, string> = {
    available: '#3F6E52',
    occupied: '#A63D2F',
    reserved: '#B8862E',
  }

  return (
    <FlatList
      contentContainerStyle={{ padding: 16 }}
      numColumns={3}
      data={tables ?? []}
      keyExtractor={(t) => t.id}
      renderItem={({ item }) => (
        <TouchableOpacity
          style={[styles.tile, { borderColor: statusColor[item.status] ?? '#ccc' }]}
          onPress={() => navigation.navigate('WaiterOrderEntry', { tableId: item.id, tableLabel: item.label })}
        >
          <Text style={styles.tileLabel}>{item.label}</Text>
          <Text style={styles.tileCapacity}>{item.capacity} seats</Text>
        </TouchableOpacity>
      )}
    />
  )
}

const styles = StyleSheet.create({
  tile: { flex: 1, margin: 6, aspectRatio: 1, borderWidth: 2, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  tileLabel: { fontSize: 18, fontWeight: '600', color: '#241C17' },
  tileCapacity: { fontSize: 11, color: '#8A7F73' },
})
