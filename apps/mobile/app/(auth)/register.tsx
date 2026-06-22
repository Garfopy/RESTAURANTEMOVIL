import React, { useEffect, useRef, useState } from 'react';
import {
  Animated,
  ActivityIndicator,
  Modal,
  View,
  Text,
  StyleSheet,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Pressable,
  TextInput,
  TouchableOpacity,
  StatusBar,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
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

type CountryCodeOption = {
  iso: string;
  name: string;
  dialCode: string;
  flag: string;
  example: string;
};

const COUNTRY_CODES: CountryCodeOption[] = [
  { iso: 'MX', name: 'Mexico', dialCode: '52', flag: '🇲🇽', example: '55 1234 5678' },
  { iso: 'US', name: 'Estados Unidos', dialCode: '1', flag: '🇺🇸', example: '201 555 0123' },
  { iso: 'CA', name: 'Canada', dialCode: '1', flag: '🇨🇦', example: '416 555 0123' },
  { iso: 'CO', name: 'Colombia', dialCode: '57', flag: '🇨🇴', example: '300 123 4567' },
  { iso: 'AR', name: 'Argentina', dialCode: '54', flag: '🇦🇷', example: '11 2345 6789' },
  { iso: 'CL', name: 'Chile', dialCode: '56', flag: '🇨🇱', example: '9 1234 5678' },
  { iso: 'PE', name: 'Peru', dialCode: '51', flag: '🇵🇪', example: '912 345 678' },
  { iso: 'BR', name: 'Brasil', dialCode: '55', flag: '🇧🇷', example: '11 91234 5678' },
  { iso: 'ES', name: 'Espana', dialCode: '34', flag: '🇪🇸', example: '612 345 678' },
  { iso: 'GT', name: 'Guatemala', dialCode: '502', flag: '🇬🇹', example: '5123 4567' },
  { iso: 'SV', name: 'El Salvador', dialCode: '503', flag: '🇸🇻', example: '7123 4567' },
];

const DEFAULT_COUNTRY = COUNTRY_CODES[0];

function digitsOnly(value: string): string {
  return value.replace(/\D/g, '');
}

export default function RegisterScreen() {
  const router = useRouter();
  const loginStore = useUserStore((s) => s.login);
  const toast = useToast();

  const [nombre, setNombre] = useState('');
  const [telefono, setTelefono] = useState('');
  const [selectedCountry, setSelectedCountry] = useState<CountryCodeOption>(DEFAULT_COUNTRY);
  const [countryModalVisible, setCountryModalVisible] = useState(false);
  const [detectingCountry, setDetectingCountry] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [nombreError, setNombreError] = useState<string | null>(null);
  const [telefonoError, setTelefonoError] = useState<string | null>(null);
  const [emailError, setEmailError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const intro = useRef(new Animated.Value(0)).current;
  const formReveal = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.stagger(120, [
      Animated.spring(intro, { toValue: 1, damping: 18, stiffness: 100, useNativeDriver: true }),
      Animated.spring(formReveal, { toValue: 1, damping: 20, stiffness: 105, useNativeDriver: true }),
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

  const handleNombreChange = (value: string) => {
    setNombre(value);
    setNombreError(value.trim() ? validateName(value) : null);
  };

  const handleEmailChange = (value: string) => {
    setEmail(value);
    setEmailError(validateOptionalEmail(value));
  };

  const handleTelefonoChange = (value: string) => {
    const next = digitsOnly(value).slice(0, 14);
    setTelefono(next);
    const fullPhone = `${selectedCountry.dialCode}${next}`;
    setTelefonoError(next ? validatePhone(fullPhone) : null);
  };

  const handlePasswordChange = (value: string) => {
    setPassword(value);
    setPasswordError(value.trim() ? validatePassword(value) : null);
  };

  function handleCountrySelect(country: CountryCodeOption) {
    setSelectedCountry(country);
    setCountryModalVisible(false);
    const fullPhone = `${country.dialCode}${digitsOnly(telefono)}`;
    setTelefonoError(telefono.trim() ? validatePhone(fullPhone) : null);
  }

  async function detectCountryFromLocation() {
    try {
      setDetectingCountry(true);
      const permission = await Location.requestForegroundPermissionsAsync();
      if (permission.status !== 'granted') {
        toast.warning('No se pudo acceder a tu ubicacion. Dejamos Mexico como lada.');
        return;
      }

      const current = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
      const [place] = await Location.reverseGeocodeAsync({
        latitude: current.coords.latitude,
        longitude: current.coords.longitude,
      });
      const iso = String((place as { isoCountryCode?: string }).isoCountryCode ?? '').toUpperCase();
      const match = COUNTRY_CODES.find((country) => country.iso === iso);
      if (match) {
        handleCountrySelect(match);
        toast.success(`Lada detectada: ${match.flag} +${match.dialCode}`);
      } else {
        toast.info('No encontramos ese pais en la lista. Dejamos Mexico como lada.');
      }
    } catch {
      toast.error('No se pudo detectar tu pais.');
    } finally {
      setDetectingCountry(false);
    }
  }

  async function handleRegister() {
    const localPhone = digitsOnly(telefono);
    const fullPhone = `${selectedCountry.dialCode}${localPhone}`;
    const nombreErr = validateName(nombre);
    const telefonoErr = validatePhone(fullPhone);
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
        telefono: fullPhone,
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
    <LinearGradient colors={['#181A1F', '#252830', '#1C1E24']} style={styles.gradient}>
    <SafeAreaView style={styles.safe}>
      <StatusBar barStyle="light-content" backgroundColor={AuthColors.bg} />
      <View pointerEvents="none" style={styles.decorations}>
        <View style={styles.glowTop} />
        <View style={styles.glowBottom} />
      </View>
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

          <Animated.View style={[styles.header, {
            opacity: intro,
            transform: [{ translateY: intro.interpolate({ inputRange: [0, 1], outputRange: [16, 0] }) }],
          }]}>
            <Text style={styles.eyebrow}>NUEVA EXPERIENCIA</Text>
            <Text style={styles.title}>Crear cuenta</Text>
            <Text style={styles.subtitle}>Todo Amare, en un solo lugar.</Text>
            <View style={styles.progressRow}>
              <View style={styles.progressActive} />
              <View style={styles.progressDot} />
              <View style={styles.progressDot} />
              <Text style={styles.progressText}>Tus datos</Text>
            </View>
          </Animated.View>

          <Animated.View style={[styles.form, {
            opacity: formReveal,
            transform: [{ translateY: formReveal.interpolate({ inputRange: [0, 1], outputRange: [28, 0] }) }],
          }]}>
            <View style={styles.formIntro}>
              <View style={styles.formIntroIcon}>
                <Ionicons name="person-add-outline" size={18} color={AuthColors.accent} />
              </View>
              <View style={styles.formIntroCopy}>
                <Text style={styles.formIntroTitle}>Cuentanos sobre ti</Text>
                <Text style={styles.formIntroText}>Te tomara menos de un minuto.</Text>
              </View>
              <Ionicons name="shield-checkmark-outline" size={18} color={AuthColors.muted} />
            </View>
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

            <View style={styles.phoneField}>
              <Text style={styles.fieldLabel}>Telefono</Text>
              <View style={[styles.phoneInputRow, telefonoError && styles.fieldInputError]}>
                <TouchableOpacity
                  style={styles.countryButton}
                  onPress={() => setCountryModalVisible(true)}
                  activeOpacity={0.82}
                  accessibilityLabel="Seleccionar lada"
                  accessibilityRole="button"
                >
                  <Text style={styles.countryFlag}>{selectedCountry.flag}</Text>
                  <Text style={styles.countryDial}>+{selectedCountry.dialCode}</Text>
                  <Ionicons name="chevron-down" size={16} color={AuthColors.muted} />
                </TouchableOpacity>
                <View style={styles.phoneDivider} />
                <TextInput
                  value={telefono}
                  onChangeText={handleTelefonoChange}
                  onBlur={() => setTelefonoError(telefono.trim() ? validatePhone(`${selectedCountry.dialCode}${digitsOnly(telefono)}`) : 'Telefono es requerido')}
                  placeholder={selectedCountry.example}
                  placeholderTextColor={AuthColors.muted}
                  keyboardType="phone-pad"
                  autoComplete="off"
                  style={styles.phoneInput}
                  testID="phone-input"
                  accessibilityLabel="Telefono"
                  accessibilityHint="Ingresa tu numero telefonico"
                />
              </View>
              {telefonoError ? (
                <View style={styles.errorContainer}>
                  <Ionicons name="alert-circle" size={14} color={AuthColors.error} />
                  <Text style={styles.fieldErrorText}>{telefonoError}</Text>
                </View>
              ) : null}
            </View>

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

            <View style={styles.passwordHint}>
              <Ionicons name="information-circle-outline" size={14} color={AuthColors.muted} />
              <Text style={styles.passwordHintText}>Usa al menos 8 caracteres para proteger tu cuenta.</Text>
            </View>

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
          </Animated.View>
        </ScrollView>
      </KeyboardAvoidingView>

      <Modal visible={countryModalVisible} transparent animationType="slide" onRequestClose={() => setCountryModalVisible(false)}>
        <Pressable style={styles.countryOverlay} onPress={() => setCountryModalVisible(false)}>
          <Pressable style={styles.countrySheet} onPress={(event) => event.stopPropagation()}>
            <View style={styles.countrySheetHandle} />
            <View style={styles.countrySheetHeader}>
              <View>
                <Text style={styles.countrySheetTitle}>Selecciona tu lada</Text>
                <Text style={styles.countrySheetSubtitle}>Mexico queda seleccionado por defecto.</Text>
              </View>
              <TouchableOpacity style={styles.detectButton} onPress={detectCountryFromLocation} disabled={detectingCountry}>
                {detectingCountry ? (
                  <ActivityIndicator color={AuthColors.buttonText} size="small" />
                ) : (
                  <Ionicons name="location-outline" size={17} color={AuthColors.buttonText} />
                )}
                <Text style={styles.detectButtonText}>Detectar</Text>
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={styles.countryList} showsVerticalScrollIndicator={false}>
              {COUNTRY_CODES.map((country) => {
                const active = country.iso === selectedCountry.iso;
                return (
                  <TouchableOpacity
                    key={country.iso}
                    style={[styles.countryOption, active && styles.countryOptionActive]}
                    onPress={() => handleCountrySelect(country)}
                    activeOpacity={0.85}
                  >
                    <Text style={styles.countryOptionFlag}>{country.flag}</Text>
                    <View style={styles.countryOptionCopy}>
                      <Text style={styles.countryOptionName}>{country.name}</Text>
                      <Text style={styles.countryOptionDial}>+{country.dialCode}</Text>
                    </View>
                    <Ionicons
                      name={active ? 'checkmark-circle' : 'ellipse-outline'}
                      size={22}
                      color={active ? AuthColors.accent : AuthColors.muted}
                    />
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          </Pressable>
        </Pressable>
      </Modal>
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
  glowTop: { position: 'absolute', width: 320, height: 320, borderRadius: 160, top: -210, right: -130, backgroundColor: 'rgba(198,169,123,0.13)' },
  glowBottom: { position: 'absolute', width: 250, height: 250, borderRadius: 125, bottom: -170, left: -140, backgroundColor: 'rgba(198,169,123,0.07)' },
  container: {
    flexGrow: 1,
    paddingHorizontal: 24,
    paddingBottom: 40,
    paddingTop: Platform.OS === 'android' ? 10 : 0,
    backgroundColor: 'transparent',
  },
  back: {
    width: 40,
    height: 40,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 14,
    marginLeft: -8,
    borderRadius: 20,
    backgroundColor: 'rgba(233,221,200,0.09)',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.14)',
  },
  header: {
    marginBottom: 24,
  },
  eyebrow: { color: '#BFAE91', fontSize: 10, fontWeight: '900', letterSpacing: 2.1, marginBottom: 7 },
  title: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 37,
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
  progressRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 16 },
  progressActive: { width: 34, height: 5, borderRadius: 3, backgroundColor: AuthColors.accent },
  progressDot: { width: 7, height: 5, borderRadius: 3, backgroundColor: 'rgba(233,221,200,0.18)' },
  progressText: { marginLeft: 5, color: AuthColors.muted, fontSize: 11, fontWeight: '700' },
  form: {
    gap: 19,
    borderRadius: 28,
    padding: 18,
    backgroundColor: 'rgba(255,255,255,0.055)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.09)',
  },
  formIntro: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingBottom: 3 },
  formIntroIcon: { width: 40, height: 40, borderRadius: 14, backgroundColor: 'rgba(233,221,200,0.08)', alignItems: 'center', justifyContent: 'center' },
  formIntroCopy: { flex: 1 },
  formIntroTitle: { color: AuthColors.text, fontSize: 15, fontWeight: '900' },
  formIntroText: { color: AuthColors.muted, fontSize: 11, marginTop: 2 },
  phoneField: {
    gap: 8,
  },
  fieldLabel: {
    color: AuthColors.text,
    fontSize: 14,
    fontWeight: '600',
    marginLeft: 4,
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
  phoneInputRow: {
    minHeight: 58,
    borderRadius: 16,
    borderWidth: 1.5,
    borderColor: AuthColors.border,
    backgroundColor: AuthColors.inputBg,
    flexDirection: 'row',
    alignItems: 'center',
    overflow: 'hidden',
  },
  countryButton: {
    minWidth: 112,
    alignSelf: 'stretch',
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
  },
  countryFlag: {
    fontSize: 21,
  },
  countryDial: {
    color: AuthColors.text,
    fontSize: 15,
    fontWeight: '800',
  },
  phoneDivider: {
    width: 1,
    alignSelf: 'stretch',
    backgroundColor: AuthColors.border,
  },
  phoneInput: {
    flex: 1,
    minHeight: 54,
    paddingHorizontal: 14,
    color: AuthColors.text,
    fontSize: 15,
    fontWeight: '700',
  },
  errorContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginLeft: 4,
  },
  fieldErrorText: {
    color: AuthColors.error,
    fontSize: 13,
    fontWeight: '500',
  },
  passwordHint: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: -11 },
  passwordHintText: { flex: 1, color: AuthColors.muted, fontSize: 10, lineHeight: 14 },
  submitButton: {
    marginTop: 4,
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
  submitButtonText: {
    color: AuthColors.buttonText,
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: 0.2,
  },
  loginLink: {
    alignItems: 'center',
    marginTop: 2,
  },
  loginText: {
    color: AuthColors.textSecondary,
    fontSize: 15,
  },
  loginBold: {
    color: AuthColors.accent,
    fontWeight: '800',
  },
  countryOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
  },
  countrySheet: {
    maxHeight: '78%',
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    backgroundColor: '#202329',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.12)',
    paddingHorizontal: 18,
    paddingTop: 10,
    paddingBottom: 18,
  },
  countrySheetHandle: {
    alignSelf: 'center',
    width: 44,
    height: 5,
    borderRadius: 999,
    backgroundColor: AuthColors.border,
    marginBottom: 16,
  },
  countrySheetHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    marginBottom: 14,
  },
  countrySheetTitle: {
    color: AuthColors.text,
    fontSize: 20,
    fontWeight: '900',
  },
  countrySheetSubtitle: {
    marginTop: 3,
    color: AuthColors.textSecondary,
    fontSize: 13,
    fontWeight: '600',
  },
  detectButton: {
    minHeight: 42,
    borderRadius: 14,
    paddingHorizontal: 12,
    backgroundColor: AuthColors.accent,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  detectButtonText: {
    color: AuthColors.buttonText,
    fontSize: 13,
    fontWeight: '900',
  },
  countryList: {
    gap: 8,
    paddingBottom: 6,
  },
  countryOption: {
    minHeight: 58,
    borderRadius: 16,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: AuthColors.border,
    backgroundColor: AuthColors.inputBg,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  countryOptionActive: {
    borderColor: AuthColors.accent,
    backgroundColor: AuthColors.inputFocused,
  },
  countryOptionFlag: {
    fontSize: 26,
    width: 34,
    textAlign: 'center',
  },
  countryOptionCopy: {
    flex: 1,
  },
  countryOptionName: {
    color: AuthColors.text,
    fontSize: 15,
    fontWeight: '800',
  },
  countryOptionDial: {
    marginTop: 2,
    color: AuthColors.textSecondary,
    fontSize: 13,
    fontWeight: '700',
  },
});
