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
import { register } from '../../services/auth.service';
import { useUserStore } from '../../store/user.store';
import { Button } from '../../components/ui/Button';
import { FormField } from '../../components/ui/FormField';
import { useToast } from '../../context/ToastContext';
import { mapErrorToFriendly, validateEmail, validatePassword, validateName } from '../../services/error.service';
import { Colors, Spacing } from '../../theme';

// Definición local de colores para estilo Claro Premium
const PremiumColors = {
  bg: '#FFFFFF',
  text: '#1A1A1A',
  textSecondary: '#666666',
  border: '#E5E5E5',
  inputBg: '#F9F9F9',
  primary: '#000000',
  white: '#FFFFFF',
  error: '#DC2626',
};

export default function RegisterScreen() {
  const router = useRouter();
  const loginStore = useUserStore((s) => s.login);
  const toast = useToast();

  const [nombre, setNombre] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

  // Estados de validación
  const [nombreError, setNombreError] = useState<string | null>(null);
  const [emailError, setEmailError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [focusedInput, setFocusedInput] = useState<'nombre' | 'email' | 'password' | null>(null);

  // Validar nombre en tiempo real
  const handleNombreChange = (value: string) => {
    setNombre(value);
    if (value.trim()) {
      const error = validateName(value);
      setNombreError(error);
    } else {
      setNombreError(null);
    }
  };

  // Validar email en tiempo real
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

  async function handleRegister() {
    // Validar campos antes de enviar
    const nombreErr = validateName(nombre);
    const emailErr = validateEmail(email);
    const passwordErr = validatePassword(password);

    setNombreError(nombreErr);
    setEmailError(emailErr);
    setPasswordError(passwordErr);

    if (nombreErr || emailErr || passwordErr) {
      toast.error('Por favor, corrige los errores en el formulario');
      return;
    }

    setLoading(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 500));
      const sesion = await register({
        nombre: nombre.trim(),
        email: email.trim().toLowerCase(),
        password,
      });
      await loginStore(sesion);
      toast.success('¡Cuenta creada exitosamente!');
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
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={{ flex: 1 }}
      >
        <ScrollView
          contentContainerStyle={styles.container}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          {/* Botón de regreso */}
          <TouchableOpacity
            style={styles.back}
            onPress={() => router.back()}
            accessibilityLabel="Volver atrás"
            accessibilityRole="button"
            testID="back-btn"
          >
            <Ionicons name="chevron-back" size={24} color={PremiumColors.text} />
          </TouchableOpacity>

          {/* Header */}
          <View style={styles.header}>
            <Text style={styles.title}>Crear cuenta</Text>
            <Text style={styles.subtitle}>Regístrate para empezar a ordenar</Text>
          </View>

          {/* Formulario */}
          <View style={styles.form}>
            {/* Nombre */}
            <FormField
              label="Nombre completo"
              value={nombre}
              onChangeText={handleNombreChange}
              onBlur={() => setFocusedInput(null)}
              onFocus={() => setFocusedInput('nombre')}
              placeholder="Tu nombre"
              error={nombreError}
              autoCapitalize="words"
              icon="person-outline"
              testID="name-input"
              accessibilityLabel="Nombre completo"
              accessibilityHint="Ingresa tu nombre completo"
            />

            {/* Email */}
            <FormField
              label="Correo electrónico"
              value={email}
              onChangeText={handleEmailChange}
              onBlur={() => setFocusedInput(null)}
              onFocus={() => setFocusedInput('email')}
              placeholder="correo@ejemplo.com"
              error={emailError}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              icon="mail-outline"
              testID="email-input"
              accessibilityLabel="Correo electrónico"
              accessibilityHint="Ingresa una dirección de correo válida"
            />

            {/* Contraseña */}
            <FormField
              label="Contraseña"
              value={password}
              onChangeText={handlePasswordChange}
              onBlur={() => setFocusedInput(null)}
              onFocus={() => setFocusedInput('password')}
              placeholder="••••••••"
              error={passwordError}
              secureTextEntry={!showPassword}
              onToggleSecure={() => setShowPassword((v) => !v)}
              icon="lock-closed-outline"
              testID="password-input"
              accessibilityLabel="Contraseña"
              accessibilityHint="Ingresa una contraseña de al menos 8 caracteres"
            />

            {/* Botón de Registro */}
            <Button
              label="Crear cuenta"
              onPress={handleRegister}
              loading={loading}
              fullWidth
              size="lg"
              style={styles.submitButton}
              textStyle={styles.submitButtonText}
              accessibilityLabel="Crear cuenta"
              testID="register-btn"
            />

            {/* Link a Login */}
            <TouchableOpacity
              style={styles.loginLink}
              onPress={() => router.replace('/(auth)/email-login')}
              accessibilityLabel="Ir a iniciar sesión"
              accessibilityRole="link"
              testID="login-link"
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
  safe: {
    flex: 1,
    backgroundColor: PremiumColors.bg,
  },
  container: {
    flexGrow: 1,
    paddingHorizontal: 24,
    paddingBottom: 40,
  },
  back: {
    width: 40,
    height: 40,
    justifyContent: 'center',
    alignItems: 'flex-start',
    marginBottom: 20,
  },
  header: {
    marginBottom: 40,
  },
  title: {
    fontFamily: Platform.OS === 'ios' ? 'Helvetica Neue' : 'sans-serif-condensed',
    fontWeight: '700',
    fontSize: 34,
    color: PremiumColors.text,
    letterSpacing: 0.5,
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: PremiumColors.textSecondary,
    fontWeight: '400',
    letterSpacing: 0.1,
  },
  form: {
    gap: 24,
  },
  submitButton: {
    marginTop: 16,
    backgroundColor: PremiumColors.primary,
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
  submitButtonText: {
    color: PremiumColors.white,
    fontSize: 16,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  loginLink: {
    alignItems: 'center',
    marginTop: 24,
  },
  loginText: {
    color: PremiumColors.textSecondary,
    fontSize: 15,
  },
  loginBold: {
    color: PremiumColors.primary,
    fontWeight: '700',
  },
});