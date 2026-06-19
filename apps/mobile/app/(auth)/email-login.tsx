import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  TouchableOpacity,
  StatusBar,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { loginWithEmail } from '../../services/auth.service';
import { useUserStore } from '../../store/user.store';
import { Button } from '../../components/ui/Button';
import { FormField } from '../../components/ui/FormField';
import { useToast } from '../../context/ToastContext';
import { mapErrorToFriendly, validateLoginIdentifier, validatePassword } from '../../services/error.service';

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

export default function EmailLoginScreen() {
  const router = useRouter();
  const login = useUserStore((s) => s.login);
  const toast = useToast();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPass, setShowPass] = useState(false);
  const [loading, setLoading] = useState(false);
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

  const handleEmailChange = (value: string) => {
    setEmail(value);
    setEmailError(value.trim() ? validateLoginIdentifier(value) : null);
  };

  const handlePasswordChange = (value: string) => {
    setPassword(value);
    setPasswordError(value.trim() ? validatePassword(value) : null);
  };

  const handleEmailBlur = () => {
    if (email.trim()) setEmailError(validateLoginIdentifier(email));
  };

  const handlePasswordBlur = () => {
    if (password.trim()) setPasswordError(validatePassword(password));
  };

  async function handleLogin() {
    const emailErr = validateLoginIdentifier(email);
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
      const identifier = email.trim();
      const sesion = await loginWithEmail({ email: identifier.includes('@') ? identifier.toLowerCase() : identifier, password });
      await login(sesion);
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
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={styles.flex}>
        <ScrollView
          contentContainerStyle={styles.container}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.header}>
            <TouchableOpacity
              style={styles.backBtn}
              onPress={() => router.back()}
              accessibilityLabel="Volver atras"
              accessibilityRole="button"
              testID="back-btn"
            >
              <Ionicons name="chevron-back" size={24} color={AuthColors.text} />
            </TouchableOpacity>
          </View>

          <View style={styles.titleContainer}>
            <Text style={styles.title}>Bienvenido</Text>
            <Text style={styles.subtitle}>Ingresa tus credenciales para continuar</Text>
          </View>

          <View style={styles.form}>
            <FormField
              {...fieldTheme}
              label="Correo o telefono"
              value={email}
              onChangeText={handleEmailChange}
              onBlur={handleEmailBlur}
              placeholder="correo@ejemplo.com o 55 1234 5678"
              error={emailError}
              keyboardType="default"
              autoCapitalize="none"
              autoComplete="username"
              icon="person-circle-outline"
              testID="email-input"
              accessibilityLabel="Correo o telefono"
              accessibilityHint="Ingresa tu correo electronico o telefono"
            />

            <View style={styles.passwordBlock}>
              <View style={styles.labelRow}>
                <Text style={styles.label}>Contrasena</Text>
                <TouchableOpacity
                  onPress={() => {}}
                  accessibilityLabel="Olvidaste tu contrasena"
                  accessibilityRole="link"
                  testID="forgot-password-link"
                >
                  <Text style={styles.forgotPassword}>Olvidaste tu contrasena?</Text>
                </TouchableOpacity>
              </View>
              <FormField
                {...fieldTheme}
                label=""
                value={password}
                onChangeText={handlePasswordChange}
                onBlur={handlePasswordBlur}
                placeholder="********"
                error={passwordError}
                secureTextEntry={!showPass}
                autoComplete="password"
                icon="lock-closed-outline"
                onToggleSecure={() => setShowPass((v) => !v)}
                testID="password-input"
                accessibilityLabel="Contrasena"
                accessibilityHint="Ingresa tu contrasena"
              />
            </View>

            <Button
              label="Iniciar sesion"
              onPress={handleLogin}
              loading={loading}
              fullWidth
              size="lg"
              style={styles.signInButton}
              textStyle={styles.signInButtonText}
              accessibilityLabel="Iniciar sesion"
              testID="login-btn"
            />

            <View style={styles.footer}>
              <Text style={styles.footerText}>No tienes una cuenta?</Text>
              <TouchableOpacity
                onPress={() => router.push('/(auth)/register')}
                accessibilityLabel="Ir a registro"
                accessibilityRole="link"
                testID="signup-link"
              >
                <Text style={styles.signUpLink}> Registrate</Text>
              </TouchableOpacity>
            </View>
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
    backgroundColor: AuthColors.bg,
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
    alignItems: 'center',
    marginLeft: -8,
    borderRadius: 20,
    backgroundColor: 'rgba(233,221,200,0.09)',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.14)',
  },
  titleContainer: {
    marginTop: 20,
    marginBottom: 40,
  },
  title: {
    fontFamily: 'Inter_700Bold',
    fontWeight: '700',
    fontSize: 34,
    color: AuthColors.text,
    letterSpacing: 0,
  },
  subtitle: {
    fontSize: 16,
    color: AuthColors.textSecondary,
    marginTop: 8,
    fontWeight: '400',
    letterSpacing: 0.1,
  },
  form: {
    gap: 24,
  },
  passwordBlock: {
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
    color: AuthColors.text,
    letterSpacing: 0.2,
    marginLeft: 4,
  },
  forgotPassword: {
    fontSize: 13,
    color: AuthColors.accent,
    fontWeight: '600',
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
  signInButton: {
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
  signInButtonText: {
    color: AuthColors.buttonText,
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: 0.2,
  },
  footer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 20,
  },
  footerText: {
    fontSize: 15,
    color: AuthColors.textSecondary,
  },
  signUpLink: {
    fontSize: 15,
    color: AuthColors.accent,
    fontWeight: '800',
  },
});
