import { useState } from 'react'
import { View, Text, TextInput, TouchableOpacity, StyleSheet } from 'react-native'
import { useAuth } from '@/context/AuthContext'

export default function LoginScreen() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const { loginStaff } = useAuth()

  async function handleLogin() {
    try {
      await loginStaff(email, password)
    } catch {
      setError('Invalid credentials.')
    }
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Brewline Staff</Text>
      <TextInput style={styles.input} placeholder="Email" autoCapitalize="none" value={email} onChangeText={setEmail} />
      <TextInput style={styles.input} placeholder="Password" secureTextEntry value={password} onChangeText={setPassword} />
      {error && <Text style={styles.error}>{error}</Text>}
      <TouchableOpacity style={styles.button} onPress={handleLogin}>
        <Text style={styles.buttonText}>Sign in</Text>
      </TouchableOpacity>
    </View>
  )
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', padding: 24, backgroundColor: '#F7F5F1' },
  title: { fontSize: 28, fontWeight: '600', color: '#2E1F17', marginBottom: 24, textAlign: 'center' },
  input: { backgroundColor: 'white', borderRadius: 8, padding: 14, marginBottom: 12, borderWidth: 1, borderColor: '#00000015' },
  button: { backgroundColor: '#4A3226', borderRadius: 8, padding: 16, alignItems: 'center', marginTop: 8 },
  buttonText: { color: 'white', fontWeight: '600' },
  error: { color: '#A63D2F', marginBottom: 8, textAlign: 'center' },
})
