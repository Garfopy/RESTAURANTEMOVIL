import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  StyleSheet,
  SafeAreaView,
  KeyboardAvoidingView,
  Platform,
  Alert,
  ScrollView,
  TouchableOpacity,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { register } from '../../services/auth.service';
import { useUserStore } from '../../store/user.store';
import { Button } from '../../components/ui/Button';
import { Colors, Spacing } from '../../theme';

export default function RegisterScreen() {
  const router = useRouter();
  const loginStore = useUserStore((s) => s.login);

  const [nombre, setNombre] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleRegister() {
    if (!nombre || !email || !password) {
      Alert.alert('Campos requeridos', 'Por favor completa todos los campos.');
      return;
    }
    if (password.length < 8) {
      Alert.alert('Contraseña débil', 'Usa al menos 8 caracteres.');
      return;
    }
    setLoading(true);
    try {
      const sesion = await register({
        nombre: nombre.trim(),
        email: email.trim().toLowerCase(),
        password,
      });
      await loginStore(sesion);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'No se pudo crear la cuenta.';
      Alert.alert('Error', msg);
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={{ flex: 1 }}
      >
        <ScrollView
          contentContainerStyle={styles.container}
          keyboardShouldPersistTaps="handled"
        >
          <TouchableOpacity style={styles.back} onPress={() => router.back()}>
            <Ionicons name="arrow-back" size={24} color={Colors.white} />
          </TouchableOpacity>

          <Text style={styles.title}>Crear cuenta</Text>
          <Text style={styles.subtitle}>Regístrate para empezar a ordenar</Text>

          <View style={styles.form}>
            <View style={styles.inputGroup}>
              <Text style={styles.label}>Nombre completo</Text>
              <TextInput
                style={styles.input}
                value={nombre}
                onChangeText={setNombre}
                autoCapitalize="words"
                placeholderTextColor={Colors.textMuted}
                placeholder="Tu nombre"
              />
            </View>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>Correo electrónico</Text>
              <TextInput
                style={styles.input}
                value={email}
                onChangeText={setEmail}
                keyboardType="email-address"
                autoCapitalize="none"
                autoComplete="email"
                placeholderTextColor={Colors.textMuted}
                placeholder="correo@ejemplo.com"
              />
            </View>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>Contraseña</Text>
              <TextInput
                style={styles.input}
                value={password}
                onChangeText={setPassword}
                secureTextEntry
                placeholderTextColor={Colors.textMuted}
                placeholder="Mínimo 8 caracteres"
              />
            </View>

            <Button
              label="Crear cuenta"
              onPress={handleRegister}
              loading={loading}
              fullWidth
              size="lg"
              style={{ marginTop: Spacing.sm }}
            />

            <TouchableOpacity
              style={styles.loginLink}
              onPress={() => router.replace('/(auth)/email-login')}
            >
              <Text style={styles.loginText}>
                ¿Ya tienes cuenta?{' '}
                <Text style={styles.loginBold}>Iniciar sesión</Text>
              </Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.primary },
  container: {
    flexGrow: 1,
    paddingHorizontal: Spacing['2xl'],
    paddingTop: Spacing.base,
    paddingBottom: Spacing['3xl'],
  },
  back: { marginBottom: Spacing.xl },
  title: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 32,
    color: Colors.white,
    marginBottom: 6,
  },
  subtitle: { fontSize: 15, color: 'rgba(255,255,255,0.6)', marginBottom: Spacing.xl },
  form: { gap: Spacing.base },
  inputGroup: { gap: 6 },
  label: { fontSize: 13, fontWeight: '600', color: 'rgba(255,255,255,0.7)' },
  input: {
    backgroundColor: 'rgba(255,255,255,0.1)',
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 14,
    fontSize: 15,
    color: Colors.white,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.15)',
  },
  loginLink: { alignItems: 'center', marginTop: Spacing.sm },
  loginText: { color: 'rgba(255,255,255,0.6)', fontSize: 14 },
  loginBold: { color: Colors.accent, fontWeight: '700' },
});
