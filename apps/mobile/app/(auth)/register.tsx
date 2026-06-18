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
import {
  mapErrorToFriendly,
  validateName,
  validateOptionalEmail,
  validatePassword,
  validatePhone,
} from '../../services/error.service';

const AuthColors = {
  bg: '#24272D',
  text: '#F2EBDD',
  textSecondary: '#D8CDBB',
  muted: '#B8AC99',
  border: '#4B5058',
  inputBg: '#2A2E35',
  inputFocused: '#30353D',
  accent: '#E9DDC8',
  buttonText: '#24272D',
  error: '#FCA5A5',
  errorBg: '#3A2B2E',
  errorBorder: '#B85C63',
};

export default function RegisterScreen() {
  const router = useRouter();
  const loginStore = useUserStore((s) => s.login);
  const toast = useToast();

  const [nombre, setNombre] = useState('');
  const [telefono, setTelefono] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [nombreError, setNombreError] = useState<string | null>(null);
  const [telefonoError, setTelefonoError] = useState<string | null>(null);
  const [emailError, setEmailError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);

  const fieldTheme = {
    labelStyle: styles.fieldLabel,
    inputWrapperStyle: styles.fieldInput,
    inputStyle: styles.fieldText,
    placeholderTextColor: AuthColors.muted,
    iconColor: AuthColors.muted,
    errorIconColor: AuthColors.error,
    focusedBorderColor: AuthColors.accent,
    focusedBackgroundColor: AuthColors.inputFocused,
    errorInputWrapperStyle: styles.fieldInputError,
    errorTextStyle: styles.fieldErrorText,
  };

  const handleNombreChange = (value: string) => {
    setNombre(value);
    setNombreError(value.trim() ? validateName(value) : null);
  };

  const handleEmailChange = (value: string) => {
    setEmail(value);
    setEmailError(validateOptionalEmail(value));
  };

  const handleTelefonoChange = (value: string) => {
    setTelefono(value);
    setTelefonoError(value.trim() ? validatePhone(value) : null);
  };

  const handlePasswordChange = (value: string) => {
    setPassword(value);
    setPasswordError(value.trim() ? validatePassword(value) : null);
  };

  async function handleRegister() {
    const nombreErr = validateName(nombre);
    const telefonoErr = validatePhone(telefono);
    const emailErr = validateOptionalEmail(email);
    const passwordErr = validatePassword(password);

    setNombreError(nombreErr);
    setTelefonoError(telefonoErr);
    setEmailError(emailErr);
    setPasswordError(passwordErr);

    if (nombreErr || telefonoErr || emailErr || passwordErr) {
      toast.error('Por favor, corrige los errores en el formulario');
      return;
    }

    setLoading(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 500));
      const sesion = await register({
        nombre: nombre.trim(),
        telefono: telefono.trim(),
        email: email.trim() ? email.trim().toLowerCase() : undefined,
        password,
      });
      await loginStore(sesion);
      toast.success('Cuenta creada exitosamente');
    } catch (err: unknown) {
      const friendlyError = mapErrorToFriendly(err);
      toast.error(friendlyError.message, { icon: friendlyError.icon });
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <StatusBar barStyle="light-content" backgroundColor={AuthColors.bg} />
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
        <ScrollView
          contentContainerStyle={styles.container}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <TouchableOpacity
            style={styles.back}
            onPress={() => router.back()}
            accessibilityLabel="Volver atras"
            accessibilityRole="button"
            testID="back-btn"
          >
            <Ionicons name="chevron-back" size={24} color={AuthColors.text} />
          </TouchableOpacity>

          <View style={styles.header}>
            <Text style={styles.title}>Crear cuenta</Text>
            <Text style={styles.subtitle}>Registrate para empezar a ordenar</Text>
          </View>

          <View style={styles.form}>
            <FormField
              {...fieldTheme}
              label="Nombre completo"
              value={nombre}
              onChangeText={handleNombreChange}
              onBlur={() => setNombreError(nombre.trim() ? validateName(nombre) : null)}
              placeholder="Tu nombre"
              error={nombreError}
              autoCapitalize="words"
              icon="person-outline"
              testID="name-input"
              accessibilityLabel="Nombre completo"
              accessibilityHint="Ingresa tu nombre completo"
            />

            <FormField
              {...fieldTheme}
              label="Telefono"
              value={telefono}
              onChangeText={handleTelefonoChange}
              onBlur={() => setTelefonoError(telefono.trim() ? validatePhone(telefono) : 'Telefono es requerido')}
              placeholder="55 1234 5678"
              error={telefonoError}
              keyboardType="phone-pad"
              autoComplete="off"
              icon="call-outline"
              testID="phone-input"
              accessibilityLabel="Telefono"
              accessibilityHint="Ingresa tu numero telefonico"
            />

            <FormField
              {...fieldTheme}
              label="Correo electronico (opcional)"
              value={email}
              onChangeText={handleEmailChange}
              onBlur={() => setEmailError(validateOptionalEmail(email))}
              placeholder="correo@ejemplo.com"
              error={emailError}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              icon="mail-outline"
              testID="email-input"
              accessibilityLabel="Correo electronico"
              accessibilityHint="Ingresa una direccion de correo valida"
            />

            <FormField
              {...fieldTheme}
              label="Contrasena"
              value={password}
              onChangeText={handlePasswordChange}
              onBlur={() => setPasswordError(password.trim() ? validatePassword(password) : null)}
              placeholder="********"
              error={passwordError}
              secureTextEntry={!showPassword}
              onToggleSecure={() => setShowPassword((v) => !v)}
              icon="lock-closed-outline"
              testID="password-input"
              accessibilityLabel="Contrasena"
              accessibilityHint="Ingresa una contrasena de al menos 8 caracteres"
            />

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

            <TouchableOpacity
              style={styles.loginLink}
              onPress={() => router.replace('/(auth)/email-login')}
              accessibilityLabel="Ir a iniciar sesion"
              accessibilityRole="link"
              testID="login-link"
            >
              <Text style={styles.loginText}>
                Ya tienes cuenta? <Text style={styles.loginBold}>Iniciar sesion</Text>
              </Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  flex: {
    flex: 1,
  },
  safe: {
    flex: 1,
    backgroundColor: AuthColors.bg,
  },
  container: {
    flexGrow: 1,
    paddingHorizontal: 24,
    paddingBottom: 40,
    paddingTop: Platform.OS === 'android' ? 10 : 0,
    backgroundColor: AuthColors.bg,
  },
  back: {
    width: 40,
    height: 40,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 20,
    marginLeft: -8,
    borderRadius: 20,
    backgroundColor: 'rgba(233,221,200,0.09)',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.14)',
  },
  header: {
    marginBottom: 40,
  },
  title: {
    fontFamily: Platform.OS === 'ios' ? 'Helvetica Neue' : 'sans-serif-condensed',
    fontWeight: '700',
    fontSize: 34,
    color: AuthColors.text,
    letterSpacing: 0,
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: AuthColors.textSecondary,
    fontWeight: '400',
    letterSpacing: 0.1,
  },
  form: {
    gap: 24,
  },
  fieldLabel: {
    color: AuthColors.text,
  },
  fieldInput: {
    backgroundColor: AuthColors.inputBg,
    borderColor: AuthColors.border,
    borderRadius: 14,
    minHeight: 56,
  },
  fieldInputError: {
    backgroundColor: AuthColors.errorBg,
    borderColor: AuthColors.errorBorder,
  },
  fieldText: {
    color: AuthColors.text,
  },
  fieldErrorText: {
    color: AuthColors.error,
  },
  submitButton: {
    marginTop: 16,
    backgroundColor: AuthColors.accent,
    height: 56,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.22,
    shadowRadius: 14,
    elevation: 6,
  },
  submitButtonText: {
    color: AuthColors.buttonText,
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: 0.2,
  },
  loginLink: {
    alignItems: 'center',
    marginTop: 24,
  },
  loginText: {
    color: AuthColors.textSecondary,
    fontSize: 15,
  },
  loginBold: {
    color: AuthColors.accent,
    fontWeight: '800',
  },
});
