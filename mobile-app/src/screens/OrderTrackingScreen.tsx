import { View, Text, StyleSheet } from 'react-native'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/api/client'

const STEPS = ['pending', 'confirmed', 'preparing', 'ready']

export default function OrderTrackingScreen({ route }: any) {
  const { orderNumber } = route.params
  const branchId = process.env.EXPO_PUBLIC_BRANCH_ID

  const { data: order } = useQuery({
    queryKey: ['track', orderNumber],
    queryFn: async () => (await api.get(`/orders/track/${orderNumber}`, { params: { branch_id: branchId } })).data,
    refetchInterval: 8000,
  })

  const currentStep = STEPS.indexOf(order?.status)

  return (
    <View style={styles.container}>
      <Text style={styles.orderNumber}>{orderNumber}</Text>
      <View style={styles.stepsRow}>
        {STEPS.map((step, i) => (
          <View key={step} style={{ alignItems: 'center', flex: 1 }}>
            <View style={[styles.dot, i <= currentStep && styles.dotActive]} />
            <Text style={styles.stepLabel}>{step}</Text>
          </View>
        ))}
      </View>
      <Text style={styles.status}>
        {order?.status === 'ready' ? "It's ready for pickup! 🎉" : 'Getting your order ready…'}
      </Text>
    </View>
  )
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24, alignItems: 'center', paddingTop: 60, backgroundColor: '#F7F5F1' },
  orderNumber: { fontSize: 32, fontWeight: '700', color: '#2E1F17', marginBottom: 32 },
  stepsRow: { flexDirection: 'row', width: '100%', marginBottom: 32 },
  dot: { width: 20, height: 20, borderRadius: 10, backgroundColor: '#00000015', marginBottom: 6 },
  dotActive: { backgroundColor: '#3F6E52' },
  stepLabel: { fontSize: 11, color: '#8A7F73', textTransform: 'capitalize' },
  status: { color: '#8A7F73', textAlign: 'center' },
})
