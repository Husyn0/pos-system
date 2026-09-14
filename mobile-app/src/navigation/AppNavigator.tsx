import { NavigationContainer } from '@react-navigation/native'
import { createNativeStackNavigator } from '@react-navigation/native-stack'
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs'
import { useAuth } from '@/context/AuthContext'
import LoginScreen from '@/screens/LoginScreen'
import MenuScreen from '@/screens/MenuScreen'
import CartScreen from '@/screens/CartScreen'
import OrderTrackingScreen from '@/screens/OrderTrackingScreen'
import WaiterTablesScreen from '@/screens/WaiterTablesScreen'
import WaiterOrderEntryScreen from '@/screens/WaiterOrderEntryScreen'

const Stack = createNativeStackNavigator()
const Tab = createBottomTabNavigator()

/** Customer mode: browse/order/track — no login required (guest checkout). */
function CustomerTabs() {
  return (
    <Tab.Navigator screenOptions={{ headerShown: false }}>
      <Tab.Screen name="Menu" component={MenuScreen} />
      <Tab.Screen name="Cart" component={CartScreen} />
    </Tab.Navigator>
  )
}

/** Staff mode: waiter table management, unlocked after login. */
function StaffStack() {
  return (
    <Stack.Navigator>
      <Stack.Screen name="Tables" component={WaiterTablesScreen} options={{ title: 'Tables' }} />
      <Stack.Screen name="WaiterOrderEntry" component={WaiterOrderEntryScreen} options={{ title: 'New order' }} />
    </Stack.Navigator>
  )
}

/**
 * One app binary serves both customers (default, no auth) and staff
 * (after signing in from a hidden "Staff login" entry point) — this avoids
 * shipping two separate apps for what is fundamentally the same ordering
 * domain, while keeping the two experiences cleanly separated in code.
 */
export default function AppNavigator() {
  const { user } = useAuth()

  return (
    <NavigationContainer>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {user ? (
          <Stack.Screen name="Staff" component={StaffStack} />
        ) : (
          <>
            <Stack.Screen name="CustomerTabs" component={CustomerTabs} />
            <Stack.Screen name="OrderTracking" component={OrderTrackingScreen} options={{ headerShown: true, title: 'Your order' }} />
            <Stack.Screen name="StaffLogin" component={LoginScreen} options={{ headerShown: true, title: 'Staff sign in' }} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  )
}
