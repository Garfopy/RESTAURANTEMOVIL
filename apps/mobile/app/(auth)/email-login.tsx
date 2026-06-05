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
  StatusBar,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
// Asumiendo que estos imports existen, si no, ajusta las rutas
import { loginWithEmail } from '../../services/auth.service';
import { useUserStore } from '../../store/user.store';
import { Button } from '../../components/ui/Button';
// Importamos Colors y Spacing, pero definiremos unos locales para el estilo claro premium
// si tu tema actual no los tiene.
import { Colors, Spacing } from '../../theme';

// Definición local de colores para estilo Claro Premium (puedes mover esto a tu theme.ts)
const PremiumColors = {
  bg: '#FFFFFF',
  text: '#1A1A1A', // Casi negro, más suave
  textSecondary: '#666666',
  border: '#E5E5E5',
  inputBg: '#F9F9F9',
  primary: '#000000', // O tu color de marca, pero negro/dorado/azul marino suelen verse premium
  white: '#FFFFFF',
  error: '#DC2626',
};

export default function EmailLoginScreen() {
  const router = useRouter();
  const login = useUserStore((s) => s.login);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPass, setShowPass] = useState(false);
  const [loading, setLoading] = useState(false);
  // Estado para manejar el foco y cambiar el estilo del input
  const [focusedInput, setFocusedInput] = useState<'email' | 'password' | null>(null);

  async function handleLogin() {
    if (!email || !password) {
      Alert.alert('Campos requeridos', 'Por favor, ingresa tu correo y contraseña.');
      return;
    }
    setLoading(true);
    try {
      // Pequeño delay para mejorar UX de carga
      await new Promise(resolve => setTimeout(resolve, 500));
      const sesion = await loginWithEmail({ email: email.trim().toLowerCase(), password });
      await login(sesion);
      // router.replace('/home'); // Generalmente navegas después del login exitoso
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Credenciales incorrectas.';
      Alert.alert('Error al iniciar sesión', msg);
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <StatusBar barStyle="dark-content" />
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={{ flex: 1 }}
      >
        <ScrollView
          contentContainerStyle={styles.container}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          {/* Header con botón atrás */}
          <View style={styles.header}>
            <TouchableOpacity style={styles.backBtn} onPress={() => router.back()}>
              <Ionicons name="chevron-back" size={24} color={PremiumColors.text} />
            </TouchableOpacity>
          </View>

          {/* Títulos */}
          <View style={styles.titleContainer}>
            <Text style={styles.title}>Bienvenido</Text>
            <Text style={styles.subtitle}>Ingresa tus credenciales para continuar</Text>
          </View>

          {/* Formulario */}
          <View style={styles.form}>
            {/* Input Email */}
            <View style={styles.inputWrapper}>
              <Text style={styles.label}>Correo electrónico</Text>
              <View style={[
                styles.inputContainer,
                focusedInput === 'email' && styles.inputContainerFocused
              ]}>
                <Ionicons name="mail-outline" size={20} color={focusedInput === 'email' ? PremiumColors.primary : PremiumColors.textSecondary} style={styles.inputIcon} />
                <TextInput
                  style={styles.input}
                  value={email}
                  onChangeText={setEmail}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoComplete="email"
                  placeholderTextColor="#A3A3A3"
                  placeholder="ejemplo@correo.com"
                  onFocus={() => setFocusedInput('email')}
                  onBlur={() => setFocusedInput(null)}
                />
              </View>
            </View>

            {/* Input Password */}
            <View style={styles.inputWrapper}>
              <View style={styles.labelRow}>
                <Text style={styles.label}>Contraseña</Text>
                <TouchableOpacity onPress={() => {/* Navegar a recuperar */}}>
                  <Text style={styles.forgotPassword}>¿Olvidaste tu contraseña?</Text>
                </TouchableOpacity>
              </View>
              <View style={[
                styles.inputContainer,
                focusedInput === 'password' && styles.inputContainerFocused
              ]}>
                <Ionicons name="lock-closed-outline" size={20} color={focusedInput === 'password' ? PremiumColors.primary : PremiumColors.textSecondary} style={styles.inputIcon} />
                <TextInput
                  style={[styles.input, styles.passwordInput]}
                  value={password}
                  onChangeText={setPassword}
                  secureTextEntry={!showPass}
                  autoComplete="password"
                  placeholderTextColor="#A3A3A3"
                  placeholder="••••••••"
                  onFocus={() => setFocusedInput('password')}
                  onBlur={() => setFocusedInput(null)}
                />
                <TouchableOpacity
                  style={styles.eyeBtn}
                  onPress={() => setShowPass((v) => !v)}
                  hitSlop={{top: 10, bottom: 10, left: 10, right: 10}}
                >
                  <Ionicons
                    name={showPass ? 'eye-off-outline' : 'eye-outline'}
                    size={20}
                    color={PremiumColors.textSecondary}
                  />
                </TouchableOpacity>
              </View>
            </View>

            {/* Botón de Acción - Asumiendo que tu componente Button acepta estas props */}
            <Button
              label="Iniciar sesión"
              onPress={handleLogin}
              loading={loading}
              fullWidth
              size="lg"
              // Sobreescribimos estilos para que sea Premium (Negro sólido)
              style={styles.signInButton}
              textStyle={styles.signInButtonText}
            />

            {/* Footer opcional */}
            <View style={styles.footer}>
              <Text style={styles.footerText}>¿No tienes una cuenta?</Text>
              <TouchableOpacity onPress={() => router.push('/register')}>
                <Text style={styles.signUpLink}> Regístrate</Text>
              </TouchableOpacity>
            </View>

          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: PremiumColors.bg,
  },
  container: {
    flexGrow: 1,
    paddingHorizontal: 24, // Spacing['2xl'] aproximado
    paddingBottom: 40,
  },
  header: {
    height: 60,
    justifyContent: 'center',
    marginTop: Platform.OS === 'android' ? 10 : 0,
  },
  backBtn: {
    width: 40,
    height: 40,
    justifyContent: 'center',
    alignItems: 'flex-start',
    marginLeft: -5, // Ajuste visual para alinear icono
  },
  titleContainer: {
    marginTop: 20,
    marginBottom: 40,
  },
  title: {
    // Si PlayfairDisplay no carga bien en claro, una sans-serif bold también se ve premium
    fontFamily: Platform.OS === 'ios' ? 'Helvetica Neue' : 'sans-serif-condensed',
    fontWeight: '700',
    fontSize: 34,
    color: PremiumColors.text,
    letterSpacing: 0.5,
  },
  subtitle: {
    fontSize: 16,
    color: PremiumColors.textSecondary,
    marginTop: 8,
    fontWeight: '400',
    letterSpacing: 0.1,
  },
  form: {
    gap: 24, // Aumentamos el espacio entre elementos
  },
  inputWrapper: {
    gap: 8,
  },
  labelRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: PremiumColors.text,
    letterSpacing: 0.2,
  },
  forgotPassword: {
    fontSize: 13,
    color: PremiumColors.textSecondary,
    fontWeight: '500',
  },
  inputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: PremiumColors.inputBg,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: PremiumColors.border,
    paddingHorizontal: 16,
    height: 56, // Altura fija ligeramente mayor para sensación premium
    // Sutil sombra en iOS para profundidad
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.03,
    shadowRadius: 2,
    // Elevación en Android
    elevation: 1,
  },
  inputContainerFocused: {
    borderColor: PremiumColors.primary,
    backgroundColor: PremiumColors.white,
    borderWidth: 1.5,
  },
  inputIcon: {
    marginRight: 12,
  },
  input: {
    flex: 1,
    fontSize: 16,
    color: PremiumColors.text,
    height: '100%',
    fontWeight: '400',
  },
  passwordInput: {
    paddingRight: 40,
  },
  eyeBtn: {
    position: 'absolute',
    right: 16,
    height: '100%',
    justifyContent: 'center',
    alignItems: 'center',
  },
  signInButton: {
    marginTop: 16,
    backgroundColor: PremiumColors.primary, // Negro
    height: 56,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: PremiumColors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 6,
    elevation: 3,
  },
  signInButtonText: {
    color: PremiumColors.white,
    fontSize: 16,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  footer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 20,
  },
  footerText: {
    fontSize: 15,
    color: PremiumColors.textSecondary,
  },
  signUpLink: {
    fontSize: 15,
    color: PremiumColors.primary,
    fontWeight: '700',
  },
});