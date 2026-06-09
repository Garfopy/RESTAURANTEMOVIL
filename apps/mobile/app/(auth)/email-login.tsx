import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  TouchableOpacity,
  StatusBar,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { loginWithEmail } from '../../services/auth.service';
import { useUserStore } from '../../store/user.store';
import { Button } from '../../components/ui/Button';
import { FormField } from '../../components/ui/FormField';
import { useToast } from '../../context/ToastContext';
import { mapErrorToFriendly, validateEmail, validatePassword } from '../../services/error.service';
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
  const toast = useToast();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPass, setShowPass] = useState(false);
  const [loading, setLoading] = useState(false);
  
  // Estados de error/validación
  const [emailError, setEmailError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [focusedInput, setFocusedInput] = useState<'email' | 'password' | null>(null);

  // Validar email en tiempo real (on-change)
  const handleEmailChange = (value: string) => {
    setEmail(value);
    if (value.trim()) {
      const error = validateEmail(value);
      setEmailError(error);
    } else {
      setEmailError(null);
    }
  };

  // Validar contraseña en tiempo real
  const handlePasswordChange = (value: string) => {
    setPassword(value);
    if (value.trim()) {
      const error = validatePassword(value);
      setPasswordError(error);
    } else {
      setPasswordError(null);
    }
  };

  // Validar al perder el foco
  const handleEmailBlur = () => {
    setFocusedInput(null);
    if (email.trim()) {
      const error = validateEmail(email);
      setEmailError(error);
    }
  };

  const handlePasswordBlur = () => {
    setFocusedInput(null);
    if (password.trim()) {
      const error = validatePassword(password);
      setPasswordError(error);
    }
  };

  async function handleLogin() {
    // Validar campos antes de enviar
    const emailErr = validateEmail(email);
    const passwordErr = validatePassword(password);

    setEmailError(emailErr);
    setPasswordError(passwordErr);

    if (emailErr || passwordErr) {
      toast.error('Por favor, corrige los errores en el formulario');
      return;
    }

    setLoading(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 500));
      const sesion = await loginWithEmail({ email: email.trim().toLowerCase(), password });
      await login(sesion);
      // router.replace('/home');
    } catch (err: unknown) {
      const friendlyError = mapErrorToFriendly(err);
      toast.error(friendlyError.message, { icon: friendlyError.icon });
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
            <TouchableOpacity
              style={styles.backBtn}
              onPress={() => router.back()}
              accessibilityLabel="Volver atrás"
              accessibilityRole="button"
              testID="back-btn"
            >
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
            <FormField
              label="Correo electrónico"
              value={email}
              onChangeText={handleEmailChange}
              onBlur={handleEmailBlur}
              onFocus={() => setFocusedInput('email')}
              placeholder="ejemplo@correo.com"
              error={emailError}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              icon="mail-outline"
              testID="email-input"
              accessibilityLabel="Correo electrónico"
              accessibilityHint="Ingresa tu dirección de correo electrónico"
            />

            {/* Input Password */}
            <View style={{ gap: 8 }}>
              <View style={styles.labelRow}>
                <Text style={styles.label}>Contraseña</Text>
                <TouchableOpacity
                  onPress={() => {/* Navegar a recuperar */}}
                  accessibilityLabel="¿Olvidaste tu contraseña?"
                  accessibilityRole="link"
                  testID="forgot-password-link"
                >
                  <Text style={styles.forgotPassword}>¿Olvidaste tu contraseña?</Text>
                </TouchableOpacity>
              </View>
              <FormField
                label=""
                value={password}
                onChangeText={handlePasswordChange}
                onBlur={handlePasswordBlur}
                onFocus={() => setFocusedInput('password')}
                placeholder="••••••••"
                error={passwordError}
                secureTextEntry={!showPass}
                autoComplete="password"
                icon="lock-closed-outline"
                onToggleSecure={() => setShowPass((v) => !v)}
                testID="password-input"
                accessibilityLabel="Contraseña"
                accessibilityHint="Ingresa tu contraseña"
              />
            </View>

            {/* Botón de Acción */}
            <Button
              label="Iniciar sesión"
              onPress={handleLogin}
              loading={loading}
              fullWidth
              size="lg"
              style={styles.signInButton}
              textStyle={styles.signInButtonText}
              accessibilityLabel="Iniciar sesión"
              testID="login-btn"
            />

            {/* Footer opcional */}
            <View style={styles.footer}>
              <Text style={styles.footerText}>¿No tienes una cuenta?</Text>
              <TouchableOpacity
                onPress={() => router.push('/register')}
                accessibilityLabel="Ir a registro"
                accessibilityRole="link"
                testID="signup-link"
              >
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