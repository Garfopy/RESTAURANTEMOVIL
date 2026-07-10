import React, { useEffect, useRef, useState } from 'react';
import {
  Animated,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Button } from '../../components/ui/Button';
import { FormField } from '../../components/ui/FormField';
import { confirmPasswordReset, requestPasswordReset, verifyPasswordResetCode } from '../../services/auth.service';
import { getApiError } from '../../services/api';
import { validateLoginIdentifier, validatePassword } from '../../services/error.service';
import { useToast } from '../../context/ToastContext';

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

type Step = 'request' | 'code' | 'password' | 'done';

export default function ForgotPasswordScreen() {
  const router = useRouter();
  const toast = useToast();
  const params = useLocalSearchParams<{ identifier?: string }>();
  const [step, setStep] = useState<Step>('request');
  const [identifier, setIdentifier] = useState(typeof params.identifier === 'string' ? params.identifier : '');
  const [code, setCode] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [identifierError, setIdentifierError] = useState<string | null>(null);
  const [codeError, setCodeError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [confirmError, setConfirmError] = useState<string | null>(null);
  const [expiresIn, setExpiresIn] = useState(15);
  const [debugCode, setDebugCode] = useState<string | null>(null);
  const intro = useRef(new Animated.Value(0)).current;
  const card = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.stagger(120, [
      Animated.spring(intro, { toValue: 1, damping: 18, stiffness: 100, useNativeDriver: true }),
      Animated.spring(card, { toValue: 1, damping: 20, stiffness: 110, useNativeDriver: true }),
    ]).start();
  }, [card, intro]);

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

  function handleIdentifierChange(value: string) {
    setIdentifier(value);
    setIdentifierError(value.trim() ? validateLoginIdentifier(value) : null);
  }

  function handleCodeChange(value: string) {
    const next = value.replace(/\D/g, '').slice(0, 6);
    setCode(next);
    setCodeError(next.length > 0 && next.length !== 6 ? 'Ingresa los 6 dígitos' : null);
  }

  async function handleRequestCode() {
    const error = validateLoginIdentifier(identifier);
    setIdentifierError(error);
    if (error) {
      toast.error('Revisa tu correo o teléfono');
      return;
    }

    setLoading(true);
    try {
      const result = await requestPasswordReset(identifier.trim());
      setExpiresIn(result.expiresInMinutes);
      setDebugCode(result.resetCode ?? null);
      setStep('code');
      toast.success('Código enviado');
    } catch (error) {
      toast.error(getApiError(error) || 'No pudimos solicitar el código');
    } finally {
      setLoading(false);
    }
  }

  async function handleVerifyCode() {
    const nextCodeError = code.length === 6 ? null : 'Ingresa los 6 dígitos';
    setCodeError(nextCodeError);

    if (nextCodeError) {
      toast.error('Revisa el código');
      return;
    }

    setLoading(true);
    try {
      await verifyPasswordResetCode({
        identifier: identifier.trim(),
        code,
      });
      setStep('password');
      toast.success('Código verificado');
    } catch (error) {
      toast.error(getApiError(error) || 'El código no es válido');
    } finally {
      setLoading(false);
    }
  }

  async function handleConfirmReset() {
    const nextCodeError = code.length === 6 ? null : 'Ingresa los 6 dígitos';
    const nextPasswordError = validatePassword(newPassword);
    const nextConfirmError = confirmPassword === newPassword ? null : 'Las contraseñas no coinciden';

    setCodeError(nextCodeError);
    setPasswordError(nextPasswordError);
    setConfirmError(nextConfirmError);

    if (nextCodeError || nextPasswordError || nextConfirmError) {
      toast.error('Corrige los campos para continuar');
      return;
    }

    setLoading(true);
    try {
      await confirmPasswordReset({
        identifier: identifier.trim(),
        code,
        newPassword,
      });
      setStep('done');
      toast.success('Contraseña actualizada');
    } catch (error) {
      toast.error(getApiError(error) || 'No pudimos actualizar tu contraseña');
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
          <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
            <Animated.View style={[styles.header, { opacity: intro }]}>
              <TouchableOpacity style={styles.backBtn} onPress={() => router.back()} accessibilityRole="button" accessibilityLabel="Volver">
                <Ionicons name="chevron-back" size={24} color={AuthColors.text} />
              </TouchableOpacity>
              <View style={styles.brandMark}>
                <Ionicons name="key-outline" size={20} color={AuthColors.accent} />
              </View>
            </Animated.View>

            <Animated.View
              style={[
                styles.titleContainer,
                {
                  opacity: intro,
                  transform: [{ translateY: intro.interpolate({ inputRange: [0, 1], outputRange: [18, 0] }) }],
                },
              ]}
            >
              <Text style={styles.title}>{step === 'done' ? 'Listo.' : 'Recupera tu acceso.'}</Text>
              <Text style={styles.subtitle}>
                {step === 'done'
                  ? 'Tu nueva contraseña quedó guardada.'
                  : step === 'password'
                    ? 'Código verificado. Ahora crea una contraseña nueva.'
                    : 'Te enviaremos un código temporal para confirmar que eres tú.'}
              </Text>
            </Animated.View>

            <Animated.View
              style={[
                styles.formCard,
                {
                  opacity: card,
                  transform: [{ translateY: card.interpolate({ inputRange: [0, 1], outputRange: [28, 0] }) }],
                },
              ]}
            >
              {step === 'request' ? (
                <View style={styles.form}>
                  <View style={styles.formHeader}>
                    <View>
                      <Text style={styles.formTitle}>Enviar código</Text>
                      <Text style={styles.formHint}>Usa el correo o teléfono de tu cuenta</Text>
                    </View>
                    <View style={styles.secureBadge}>
                      <Ionicons name="mail-outline" size={16} color={AuthColors.accent} />
                    </View>
                  </View>

                  <FormField
                    {...fieldTheme}
                    label="Correo o teléfono"
                    value={identifier}
                    onChangeText={handleIdentifierChange}
                    onBlur={() => setIdentifierError(identifier.trim() ? validateLoginIdentifier(identifier) : null)}
                    placeholder="correo@ejemplo.com o 55 1234 5678"
                    error={identifierError}
                    autoCapitalize="none"
                    autoComplete="username"
                    icon="person-circle-outline"
                    testID="reset-identifier-input"
                  />

                  <Button
                    label="Enviar código"
                    onPress={handleRequestCode}
                    loading={loading}
                    fullWidth
                    size="lg"
                    style={styles.primaryButton}
                    textStyle={styles.primaryButtonText}
                  />
                </View>
              ) : null}

              {step === 'code' ? (
                <View style={styles.form}>
                  <View style={styles.formHeader}>
                    <View>
                      <Text style={styles.formTitle}>Validar código</Text>
                      <Text style={styles.formHint}>El código expira en {expiresIn} minutos</Text>
                    </View>
                    <TouchableOpacity onPress={handleRequestCode} disabled={loading} accessibilityRole="button">
                      <Text style={styles.inlineAction}>Reenviar</Text>
                    </TouchableOpacity>
                  </View>

                  {debugCode ? (
                    <View style={styles.debugBox}>
                      <Ionicons name="construct-outline" size={16} color={AuthColors.accent} />
                      <Text style={styles.debugText}>Código de prueba: {debugCode}</Text>
                    </View>
                  ) : null}

                  <FormField
                    {...fieldTheme}
                    label="Código"
                    value={code}
                    onChangeText={handleCodeChange}
                    placeholder="000000"
                    error={codeError}
                    keyboardType="numeric"
                    autoComplete="one-time-code"
                    icon="keypad-outline"
                    testID="reset-code-input"
                  />

                  <Button
                    label="Validar código"
                    onPress={handleVerifyCode}
                    loading={loading}
                    fullWidth
                    size="lg"
                    style={styles.primaryButton}
                    textStyle={styles.primaryButtonText}
                  />
                </View>
              ) : null}

              {step === 'password' ? (
                <View style={styles.form}>
                  <View style={styles.formHeader}>
                    <View>
                      <Text style={styles.formTitle}>Nueva contraseña</Text>
                      <Text style={styles.formHint}>Usa al menos 8 caracteres</Text>
                    </View>
                    <View style={styles.verifiedBadge}>
                      <Ionicons name="checkmark-circle" size={18} color={AuthColors.accent} />
                      <Text style={styles.verifiedText}>Verificado</Text>
                    </View>
                  </View>

                  <FormField
                    {...fieldTheme}
                    label="Nueva contraseña"
                    value={newPassword}
                    onChangeText={(value) => {
                      setNewPassword(value);
                      setPasswordError(value ? validatePassword(value) : null);
                    }}
                    placeholder="********"
                    error={passwordError}
                    secureTextEntry={!showPassword}
                    autoComplete="new-password"
                    icon="lock-closed-outline"
                    onToggleSecure={() => setShowPassword((value) => !value)}
                    testID="new-password-input"
                  />

                  <FormField
                    {...fieldTheme}
                    label="Confirmar contraseña"
                    value={confirmPassword}
                    onChangeText={(value) => {
                      setConfirmPassword(value);
                      setConfirmError(value && value !== newPassword ? 'Las contraseñas no coinciden' : null);
                    }}
                    placeholder="********"
                    error={confirmError}
                    secureTextEntry={!showPassword}
                    autoComplete="new-password"
                    icon="lock-closed-outline"
                    testID="confirm-password-input"
                  />

                  <Button
                    label="Cambiar contraseña"
                    onPress={handleConfirmReset}
                    loading={loading}
                    fullWidth
                    size="lg"
                    style={styles.primaryButton}
                    textStyle={styles.primaryButtonText}
                  />
                </View>
              ) : null}

              {step === 'done' ? (
                <View style={styles.doneState}>
                  <View style={styles.doneIcon}>
                    <Ionicons name="checkmark" size={34} color={AuthColors.buttonText} />
                  </View>
                  <Text style={styles.doneTitle}>Ya puedes iniciar sesión</Text>
                  <Text style={styles.doneText}>Usa tu nueva contraseña para entrar a Amare.</Text>
                  <Button
                    label="Ir a iniciar sesión"
                    onPress={() => router.replace('/(auth)/email-login')}
                    fullWidth
                    size="lg"
                    style={styles.primaryButton}
                    textStyle={styles.primaryButtonText}
                  />
                </View>
              ) : null}
            </Animated.View>
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  gradient: { flex: 1 },
  safe: { flex: 1, backgroundColor: 'transparent' },
  flex: { flex: 1 },
  decorations: { ...StyleSheet.absoluteFillObject, overflow: 'hidden' },
  glow: { position: 'absolute', width: 330, height: 330, borderRadius: 165, backgroundColor: 'rgba(198,169,123,0.13)', top: -190, right: -120 },
  gridLineOne: { position: 'absolute', width: 1, top: 0, bottom: 0, left: 28, backgroundColor: 'rgba(255,255,255,0.025)' },
  gridLineTwo: { position: 'absolute', height: 1, left: 0, right: 0, top: 190, backgroundColor: 'rgba(255,255,255,0.025)' },
  container: { flexGrow: 1, paddingHorizontal: 24, paddingBottom: 28, backgroundColor: 'transparent' },
  header: { height: 66, justifyContent: 'space-between', flexDirection: 'row', alignItems: 'center', marginTop: Platform.OS === 'android' ? 10 : 0 },
  backBtn: { width: 40, height: 40, justifyContent: 'center', alignItems: 'center', marginLeft: -8, borderRadius: 20, backgroundColor: 'rgba(233,221,200,0.09)', borderWidth: 1, borderColor: 'rgba(233,221,200,0.14)' },
  brandMark: { width: 38, height: 38, borderRadius: 14, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(233,221,200,0.22)', backgroundColor: 'rgba(233,221,200,0.07)' },
  titleContainer: { marginTop: 24, marginBottom: 28 },
  title: { fontFamily: 'PlayfairDisplay_700Bold', fontSize: 38, color: AuthColors.text },
  subtitle: { fontSize: 16, color: AuthColors.textSecondary, marginTop: 7, lineHeight: 22 },
  formCard: { borderRadius: 28, padding: 18, backgroundColor: 'rgba(255,255,255,0.055)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.09)' },
  formHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 22, gap: 12 },
  formTitle: { color: AuthColors.text, fontSize: 18, fontWeight: '900' },
  formHint: { marginTop: 3, color: '#999286', fontSize: 12 },
  secureBadge: { width: 38, height: 38, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(233,221,200,0.08)' },
  form: { gap: 20 },
  fieldLabel: { color: AuthColors.text },
  fieldInput: { backgroundColor: AuthColors.inputBg, borderColor: AuthColors.border, borderRadius: 16, minHeight: 58 },
  fieldInputError: { backgroundColor: AuthColors.errorBg, borderColor: AuthColors.errorBorder },
  fieldText: { color: AuthColors.text },
  fieldErrorText: { color: AuthColors.error },
  primaryButton: { marginTop: 8, backgroundColor: AuthColors.accent, height: 56, borderRadius: 18 },
  primaryButtonText: { color: AuthColors.buttonText, fontSize: 16, fontWeight: '800' },
  inlineAction: { color: AuthColors.accent, fontSize: 13, fontWeight: '800' },
  verifiedBadge: { flexDirection: 'row', alignItems: 'center', gap: 6, borderRadius: 999, paddingHorizontal: 10, paddingVertical: 7, backgroundColor: 'rgba(233,221,200,0.08)', borderWidth: 1, borderColor: 'rgba(233,221,200,0.13)' },
  verifiedText: { color: AuthColors.accent, fontSize: 12, fontWeight: '800' },
  debugBox: { flexDirection: 'row', alignItems: 'center', gap: 8, borderRadius: 14, padding: 12, backgroundColor: 'rgba(233,221,200,0.08)', borderWidth: 1, borderColor: 'rgba(233,221,200,0.13)' },
  debugText: { color: AuthColors.accent, fontSize: 13, fontWeight: '800' },
  doneState: { alignItems: 'center', gap: 14, paddingVertical: 10 },
  doneIcon: { width: 72, height: 72, borderRadius: 36, alignItems: 'center', justifyContent: 'center', backgroundColor: AuthColors.accent },
  doneTitle: { color: AuthColors.text, fontSize: 20, fontWeight: '900', marginTop: 8 },
  doneText: { color: AuthColors.textSecondary, fontSize: 14, textAlign: 'center', marginBottom: 10 },
});
