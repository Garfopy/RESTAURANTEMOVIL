import React, { useEffect, useRef, useState } from 'react';
import {
  Animated,
  View,
  Text,
  StyleSheet,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  TouchableOpacity,
  StatusBar,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
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
  const intro = useRef(new Animated.Value(0)).current;
  const formReveal = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.stagger(130, [
      Animated.spring(intro, { toValue: 1, damping: 18, stiffness: 100, useNativeDriver: true }),
      Animated.spring(formReveal, { toValue: 1, damping: 20, stiffness: 110, useNativeDriver: true }),
    ]).start();
  }, [formReveal, intro]);

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
    <LinearGradient colors={['#181A1F', '#252830', '#1C1E24']} style={styles.gradient}>
    <SafeAreaView style={styles.safe}>
      <StatusBar barStyle="light-content" backgroundColor={AuthColors.bg} />
      <View pointerEvents="none" style={styles.decorations}>
        <View style={styles.glow} />
        <View style={styles.gridLineOne} />
        <View style={styles.gridLineTwo} />
      </View>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={styles.flex}>
        <ScrollView
          contentContainerStyle={styles.container}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <Animated.View style={[styles.header, { opacity: intro }]}>
            <TouchableOpacity
              style={styles.backBtn}
              onPress={() => router.back()}
              accessibilityLabel="Volver atrás"
              accessibilityRole="button"
              testID="back-btn"
            >
              <Ionicons name="chevron-back" size={24} color={AuthColors.text} />
            </TouchableOpacity>
            <View style={styles.brandMark}>
              <Text style={styles.brandLetter}>A</Text>
            </View>
          </Animated.View>

          <Animated.View style={[styles.titleContainer, {
            opacity: intro,
            transform: [{ translateY: intro.interpolate({ inputRange: [0, 1], outputRange: [18, 0] }) }],
          }]}>
            <Text style={styles.title}>Qué gusto verte.</Text>
            <Text style={styles.subtitle}>Ingresa para continuar tu experiencia Amare.</Text>
          </Animated.View>

          <Animated.View style={[styles.formCard, {
            opacity: formReveal,
            transform: [{ translateY: formReveal.interpolate({ inputRange: [0, 1], outputRange: [28, 0] }) }],
          }]}>
            <View style={styles.formHeader}>
              <View>
                <Text style={styles.formTitle}>Iniciar sesión</Text>
                <Text style={styles.formHint}>Usa tu correo o número de teléfono</Text>
              </View>
              <View style={styles.secureBadge}>
                <Ionicons name="shield-checkmark-outline" size={16} color={AuthColors.accent} />
              </View>
            </View>
            <View style={styles.form}>
            <FormField
              {...fieldTheme}
              label="Correo o teléfono"
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
              accessibilityLabel="Correo o teléfono"
              accessibilityHint="Ingresa tu correo electrónico o teléfono"
            />

            <View style={styles.passwordBlock}>
              <View style={styles.labelRow}>
                <Text style={styles.label}>Contraseña</Text>
                <View style={styles.secureLabel}>
                  <Ionicons name="lock-closed-outline" size={12} color={AuthColors.muted} />
                  <Text style={styles.forgotPassword}>Acceso seguro</Text>
                </View>
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
                accessibilityLabel="Contraseña"
                accessibilityHint="Ingresa tu contraseña"
              />
            </View>

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

            <View style={styles.dividerRow}>
              <View style={styles.divider} />
              <Text style={styles.dividerText}>NUEVO EN AMARE</Text>
              <View style={styles.divider} />
            </View>

            <View style={styles.footer}>
              <Text style={styles.footerText}>No tienes una cuenta?</Text>
              <TouchableOpacity
                onPress={() => router.push('/(auth)/register')}
                accessibilityLabel="Ir a registro"
                accessibilityRole="link"
                testID="signup-link"
              >
                <Text style={styles.signUpLink}> Regístrate</Text>
              </TouchableOpacity>
            </View>
            </View>
          </Animated.View>

          <Animated.View style={[styles.securityNote, { opacity: formReveal }]}>
            <Ionicons name="lock-closed-outline" size={13} color="#8E887D" />
            <Text style={styles.securityText}>Tu información está protegida.</Text>
          </Animated.View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  gradient: { flex: 1 },
  flex: {
    flex: 1,
  },
  safe: {
    flex: 1,
    backgroundColor: 'transparent',
  },
  decorations: { ...StyleSheet.absoluteFillObject, overflow: 'hidden' },
  glow: { position: 'absolute', width: 330, height: 330, borderRadius: 165, backgroundColor: 'rgba(198,169,123,0.13)', top: -190, right: -120 },
  gridLineOne: { position: 'absolute', width: 1, top: 0, bottom: 0, left: 28, backgroundColor: 'rgba(255,255,255,0.025)' },
  gridLineTwo: { position: 'absolute', height: 1, left: 0, right: 0, top: 190, backgroundColor: 'rgba(255,255,255,0.025)' },
  container: {
    flexGrow: 1,
    paddingHorizontal: 24,
    paddingBottom: 28,
    backgroundColor: 'transparent',
  },
  header: {
    height: 66,
    justifyContent: 'space-between',
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: Platform.OS === 'android' ? 10 : 0,
  },
  brandMark: { width: 38, height: 38, borderRadius: 14, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(233,221,200,0.22)', backgroundColor: 'rgba(233,221,200,0.07)' },
  brandLetter: { fontFamily: 'PlayfairDisplay_700Bold', color: AuthColors.accent, fontSize: 21 },
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
    marginTop: 24,
    marginBottom: 28,
  },
  eyebrow: { color: '#BFAE91', fontSize: 10, fontWeight: '900', letterSpacing: 2.1, marginBottom: 8 },
  title: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 38,
    color: AuthColors.text,
    letterSpacing: -0.7,
  },
  subtitle: {
    fontSize: 16,
    color: AuthColors.textSecondary,
    marginTop: 7,
    fontWeight: '400',
    letterSpacing: 0.1,
  },
  formCard: { borderRadius: 28, padding: 18, backgroundColor: 'rgba(255,255,255,0.055)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.09)' },
  formHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 22 },
  formTitle: { color: AuthColors.text, fontSize: 18, fontWeight: '900' },
  formHint: { marginTop: 3, color: '#999286', fontSize: 12 },
  secureBadge: { width: 38, height: 38, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(233,221,200,0.08)' },
  form: { gap: 20 },
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
    color: AuthColors.muted,
    fontWeight: '600',
  },
  secureLabel: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  fieldLabel: {
    color: AuthColors.text,
  },
  fieldInput: {
    backgroundColor: AuthColors.inputBg,
    borderColor: AuthColors.border,
    borderRadius: 16,
    minHeight: 58,
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
    marginTop: 8,
    backgroundColor: AuthColors.accent,
    height: 56,
    borderRadius: 18,
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
  dividerRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 2 },
  divider: { flex: 1, height: 1, backgroundColor: 'rgba(233,221,200,0.12)' },
  dividerText: { color: '#777168', fontSize: 9, fontWeight: '900', letterSpacing: 1.3 },
  footer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 0,
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
  securityNote: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, marginTop: 18 },
  securityText: { color: '#8E887D', fontSize: 11, fontWeight: '600' },
});
