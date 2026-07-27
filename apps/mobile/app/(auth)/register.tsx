import React, { useEffect, useMemo, useRef, useState } from 'react';
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
import { useLocalSearchParams, useRouter } from 'expo-router';
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
} from '../../services/error.service';
import { finishAuthFlow, saveAuthReturnTo } from '../../services/auth-gate.service';

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
  { iso: 'MX', name: 'México', dialCode: '52', flag: '🇲🇽', example: '55 1234 5678' },
  { iso: 'US', name: 'Estados Unidos', dialCode: '1', flag: '🇺🇸', example: '201 555 0123' },
  { iso: 'CA', name: 'Canadá', dialCode: '1', flag: '🇨🇦', example: '416 555 0123' },
  { iso: 'CO', name: 'Colombia', dialCode: '57', flag: '🇨🇴', example: '300 123 4567' },
  { iso: 'AR', name: 'Argentina', dialCode: '54', flag: '🇦🇷', example: '11 2345 6789' },
  { iso: 'CL', name: 'Chile', dialCode: '56', flag: '🇨🇱', example: '9 1234 5678' },
  { iso: 'PE', name: 'Perú', dialCode: '51', flag: '🇵🇪', example: '912 345 678' },
  { iso: 'BR', name: 'Brasil', dialCode: '55', flag: '🇧🇷', example: '11 91234 5678' },
  { iso: 'ES', name: 'España', dialCode: '34', flag: '🇪🇸', example: '612 345 678' },
  { iso: 'GT', name: 'Guatemala', dialCode: '502', flag: '🇬🇹', example: '5123 4567' },
  { iso: 'SV', name: 'El Salvador', dialCode: '503', flag: '🇸🇻', example: '7123 4567' },
];

const DEFAULT_COUNTRY = COUNTRY_CODES[0];
const MONTHS = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const WEEK_DAYS = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];

function digitsOnly(value: string): string {
  return value.replace(/\D/g, '');
}

function toDateString(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function parseBirthDate(value?: string | null): Date | null {
  if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
  const [year, month, day] = value.split('-').map(Number);
  const date = new Date(year, month - 1, day, 12, 0, 0);
  if (date.getFullYear() !== year || date.getMonth() + 1 !== month || date.getDate() !== day) return null;
  return date;
}

function addYears(date: Date, years: number): Date {
  const next = new Date(date);
  next.setFullYear(next.getFullYear() + years);
  return next;
}

function getAgeBounds() {
  const today = new Date();
  const max = addYears(today, -18);
  const min = addYears(today, -120);
  max.setHours(23, 59, 59, 999);
  min.setHours(0, 0, 0, 0);
  return { min, max };
}

function isAdultBirthday(value: string): boolean {
  const date = parseBirthDate(value);
  if (!date) return false;
  const { min, max } = getAgeBounds();
  return date >= min && date <= max;
}

function validateBirthday(value: string): string | null {
  if (!value) return 'Cumpleaños es requerido';
  if (!isAdultBirthday(value)) return 'Debes ser mayor de edad para crear una cuenta';
  return null;
}

function formatBirthdayLabel(value: string): string {
  const date = parseBirthDate(value);
  if (!date) return 'Selecciona tu fecha';
  return `${date.getDate()} de ${MONTHS[date.getMonth()].toLowerCase()} de ${date.getFullYear()}`;
}

function clampMonth(date: Date): Date {
  const { min, max } = getAgeBounds();
  const month = new Date(date.getFullYear(), date.getMonth(), 1, 12, 0, 0);
  const minMonth = new Date(min.getFullYear(), min.getMonth(), 1, 12, 0, 0);
  const maxMonth = new Date(max.getFullYear(), max.getMonth(), 1, 12, 0, 0);
  if (month < minMonth) return minMonth;
  if (month > maxMonth) return maxMonth;
  return month;
}

function moveMonth(date: Date, amount: number): Date {
  return clampMonth(new Date(date.getFullYear(), date.getMonth() + amount, 1, 12, 0, 0));
}

function moveYear(date: Date, amount: number): Date {
  return clampMonth(new Date(date.getFullYear() + amount, date.getMonth(), 1, 12, 0, 0));
}

function getCalendarCells(monthDate: Date) {
  const year = monthDate.getFullYear();
  const month = monthDate.getMonth();
  const firstDay = new Date(year, month, 1, 12, 0, 0).getDay();
  const daysInMonth = new Date(year, month + 1, 0, 12, 0, 0).getDate();
  const cells: Array<Date | null> = Array.from({ length: firstDay }, () => null);
  for (let day = 1; day <= daysInMonth; day += 1) {
    cells.push(new Date(year, month, day, 12, 0, 0));
  }
  while (cells.length % 7 !== 0) cells.push(null);
  return cells;
}

function validateLocalPhone10(value: string): string | null {
  const digits = digitsOnly(value);
  if (!digits) {
    return 'Teléfono es requerido';
  }
  if (digits.length !== 10) {
    return 'Teléfono debe tener exactamente 10 dígitos';
  }
  return null;
}

export default function RegisterScreen() {
  const router = useRouter();
  const { returnTo } = useLocalSearchParams<{ returnTo?: string }>();
  const loginStore = useUserStore((s) => s.login);
  const toast = useToast();

  const [nombre, setNombre] = useState('');
  const [telefono, setTelefono] = useState('');
  const [selectedCountry, setSelectedCountry] = useState<CountryCodeOption>(DEFAULT_COUNTRY);
  const [countryModalVisible, setCountryModalVisible] = useState(false);
  const [detectingCountry, setDetectingCountry] = useState(false);
  const [birthday, setBirthday] = useState('');
  const [calendarVisible, setCalendarVisible] = useState(false);
  const [calendarMonth, setCalendarMonth] = useState(() => getAgeBounds().max);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [nombreError, setNombreError] = useState<string | null>(null);
  const [telefonoError, setTelefonoError] = useState<string | null>(null);
  const [birthdayError, setBirthdayError] = useState<string | null>(null);
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

  const calendarCells = useMemo(() => getCalendarCells(calendarMonth), [calendarMonth]);
  const selectedBirthday = useMemo(() => parseBirthDate(birthday), [birthday]);

  const handleNombreChange = (value: string) => {
    setNombre(value);
    setNombreError(value.trim() ? validateName(value) : null);
  };

  const handleEmailChange = (value: string) => {
    setEmail(value);
    setEmailError(validateOptionalEmail(value));
  };

  const handleTelefonoChange = (value: string) => {
    const next = digitsOnly(value).slice(0, 10);
    setTelefono(next);
    setTelefonoError(next ? validateLocalPhone10(next) : null);
  };

  function openCalendar() {
    setCalendarMonth(clampMonth(parseBirthDate(birthday) ?? getAgeBounds().max));
    setCalendarVisible(true);
  }

  function selectBirthday(date: Date) {
    const next = toDateString(date);
    setBirthday(next);
    setBirthdayError(validateBirthday(next));
    setCalendarVisible(false);
  }

  const handlePasswordChange = (value: string) => {
    setPassword(value);
    setPasswordError(value.trim() ? validatePassword(value) : null);
  };

  function handleCountrySelect(country: CountryCodeOption) {
    setSelectedCountry(country);
    setCountryModalVisible(false);
    setTelefonoError(telefono.trim() ? validateLocalPhone10(telefono) : null);
  }

  async function detectCountryFromLocation() {
    try {
      setDetectingCountry(true);
      const permission = await Location.requestForegroundPermissionsAsync();
      if (permission.status !== 'granted') {
        toast.warning('No se pudo acceder a tu ubicación. Dejamos México como lada.');
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
        toast.info('No encontramos ese país en la lista. Dejamos México como lada.');
      }
    } catch {
      toast.error('No se pudo detectar tu país.');
    } finally {
      setDetectingCountry(false);
    }
  }

  async function handleRegister() {
    const localPhone = digitsOnly(telefono);
    const fullPhone = `${selectedCountry.dialCode}${localPhone}`;
    const nombreErr = validateName(nombre);
    const telefonoErr = validateLocalPhone10(localPhone);
    const birthdayErr = validateBirthday(birthday);
    const emailErr = validateOptionalEmail(email);
    const passwordErr = validatePassword(password);

    setNombreError(nombreErr);
    setTelefonoError(telefonoErr);
    setBirthdayError(birthdayErr);
    setEmailError(emailErr);
    setPasswordError(passwordErr);

    if (nombreErr || telefonoErr || birthdayErr || emailErr || passwordErr) {
      toast.error('Por favor, corrige los errores en el formulario');
      return;
    }

    setLoading(true);
    try {
      const sesion = await register({
        nombre: nombre.trim(),
        telefono: fullPhone,
        fecha_nacimiento: birthday,
        email: email.trim() ? email.trim().toLowerCase() : undefined,
        password,
      });
      await loginStore(sesion);
      toast.success('Cuenta creada exitosamente');
      await finishAuthFlow(router);
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
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={styles.flex}>
        <ScrollView
          contentContainerStyle={styles.container}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="interactive"
          showsVerticalScrollIndicator={false}
        >
          <TouchableOpacity
            style={styles.back}
            onPress={() => router.back()}
            accessibilityLabel="Volver atrás"
            accessibilityRole="button"
            testID="back-btn"
          >
            <Ionicons name="chevron-back" size={24} color={AuthColors.text} />
          </TouchableOpacity>

          <Animated.View style={[styles.header, {
            opacity: intro,
            transform: [{ translateY: intro.interpolate({ inputRange: [0, 1], outputRange: [16, 0] }) }],
          }]}>
            <Text style={styles.title}>Crear cuenta</Text>
            <Text style={styles.subtitle}>Todo Amare, en un solo lugar.</Text>
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
                <Text style={styles.formIntroTitle}>Cuéntanos sobre ti</Text>
                <Text style={styles.formIntroText}>Te tomará menos de un minuto.</Text>
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
              <Text style={styles.fieldLabel}>Teléfono</Text>
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
                  onBlur={() => setTelefonoError(validateLocalPhone10(telefono))}
                  placeholder={selectedCountry.example}
                  placeholderTextColor={AuthColors.muted}
                  keyboardType="phone-pad"
                  autoComplete="off"
                  style={styles.phoneInput}
                  testID="phone-input"
                  accessibilityLabel="Teléfono"
                  accessibilityHint="Ingresa tu número telefónico"
                />
              </View>
              {telefonoError ? (
                <View style={styles.errorContainer}>
                  <Ionicons name="alert-circle" size={14} color={AuthColors.error} />
                  <Text style={styles.fieldErrorText}>{telefonoError}</Text>
                </View>
              ) : null}
            </View>

            <View style={styles.birthdayField}>
              <Text style={styles.fieldLabel}>Cumpleaños</Text>
              <TouchableOpacity
                style={[styles.dateButton, birthdayError && styles.fieldInputError]}
                onPress={openCalendar}
                activeOpacity={0.86}
                accessibilityLabel="Seleccionar fecha de cumpleaños"
                accessibilityRole="button"
                testID="birthday-input"
              >
                <View style={styles.dateCopy}>
                  <Text style={[styles.dateValue, !birthday && styles.datePlaceholder]}>
                    {formatBirthdayLabel(birthday)}
                  </Text>
                  <Text style={styles.dateHint}>Debes tener 18 años o más</Text>
                </View>
                <Ionicons name="calendar-outline" size={22} color={AuthColors.accent} />
              </TouchableOpacity>
              {birthdayError ? (
                <View style={styles.errorContainer}>
                  <Ionicons name="alert-circle" size={14} color={AuthColors.error} />
                  <Text style={styles.fieldErrorText}>{birthdayError}</Text>
                </View>
              ) : null}
            </View>

            <FormField
              {...fieldTheme}
              label="Correo electrónico (opcional)"
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
              accessibilityLabel="Correo electrónico"
              accessibilityHint="Ingresa una dirección de correo válida"
            />

            <FormField
              {...fieldTheme}
              label="Contraseña"
              value={password}
              onChangeText={handlePasswordChange}
              onBlur={() => setPasswordError(password.trim() ? validatePassword(password) : null)}
              placeholder="********"
              error={passwordError}
              secureTextEntry={!showPassword}
              onToggleSecure={() => setShowPassword((v) => !v)}
              icon="lock-closed-outline"
              testID="password-input"
              accessibilityLabel="Contraseña"
              accessibilityHint="Ingresa una contraseña de al menos 8 caracteres"
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
              onPress={() => {
                if (returnTo) void saveAuthReturnTo(returnTo);
                router.replace({ pathname: '/(auth)/email-login', params: returnTo ? { returnTo } : undefined } as never);
              }}
              accessibilityLabel="Ir a iniciar sesión"
              accessibilityRole="link"
              testID="login-link"
            >
              <Text style={styles.loginText}>
                ¿Ya tienes cuenta? <Text style={styles.loginBold}>Iniciar sesión</Text>
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
                <Text style={styles.countrySheetSubtitle}>México queda seleccionado por defecto.</Text>
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

      <Modal visible={calendarVisible} transparent animationType="fade" onRequestClose={() => setCalendarVisible(false)}>
        <Pressable style={styles.calendarBackdrop} onPress={() => setCalendarVisible(false)}>
          <Pressable style={styles.calendarSheet} onPress={(event) => event.stopPropagation()}>
            <View style={styles.calendarHeader}>
              <View>
                <Text style={styles.calendarTitle}>Cumpleaños</Text>
                <Text style={styles.calendarSubtitle}>Selecciona una fecha válida.</Text>
              </View>
              <TouchableOpacity
                style={styles.calendarClose}
                onPress={() => setCalendarVisible(false)}
                accessibilityLabel="Cerrar calendario"
                accessibilityRole="button"
              >
                <Ionicons name="close" size={20} color={AuthColors.text} />
              </TouchableOpacity>
            </View>

            <View style={styles.yearControls}>
              <TouchableOpacity style={styles.yearButton} onPress={() => setCalendarMonth((date) => moveYear(date, -10))}>
                <Text style={styles.yearButtonText}>-10</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.yearButton} onPress={() => setCalendarMonth((date) => moveYear(date, -1))}>
                <Ionicons name="chevron-back" size={18} color={AuthColors.accent} />
              </TouchableOpacity>
              <Text style={styles.yearLabel}>{calendarMonth.getFullYear()}</Text>
              <TouchableOpacity style={styles.yearButton} onPress={() => setCalendarMonth((date) => moveYear(date, 1))}>
                <Ionicons name="chevron-forward" size={18} color={AuthColors.accent} />
              </TouchableOpacity>
              <TouchableOpacity style={styles.yearButton} onPress={() => setCalendarMonth((date) => moveYear(date, 10))}>
                <Text style={styles.yearButtonText}>+10</Text>
              </TouchableOpacity>
            </View>

            <View style={styles.monthControls}>
              <TouchableOpacity style={styles.monthArrow} onPress={() => setCalendarMonth((date) => moveMonth(date, -1))}>
                <Ionicons name="chevron-back" size={20} color={AuthColors.text} />
              </TouchableOpacity>
              <Text style={styles.monthLabel}>{MONTHS[calendarMonth.getMonth()]}</Text>
              <TouchableOpacity style={styles.monthArrow} onPress={() => setCalendarMonth((date) => moveMonth(date, 1))}>
                <Ionicons name="chevron-forward" size={20} color={AuthColors.text} />
              </TouchableOpacity>
            </View>

            <View style={styles.weekRow}>
              {WEEK_DAYS.map((day, index) => (
                <Text key={`${day}-${index}`} style={styles.weekDay}>{day}</Text>
              ))}
            </View>

            <View style={styles.daysGrid}>
              {calendarCells.map((date, index) => {
                const disabled = !date || !isAdultBirthday(toDateString(date));
                const selected = Boolean(
                  date &&
                    selectedBirthday &&
                    date.getFullYear() === selectedBirthday.getFullYear() &&
                    date.getMonth() === selectedBirthday.getMonth() &&
                    date.getDate() === selectedBirthday.getDate()
                );

                return (
                  <TouchableOpacity
                    key={date ? toDateString(date) : `empty-${index}`}
                    style={[styles.dayCell, selected && styles.dayCellSelected]}
                    onPress={() => date && selectBirthday(date)}
                    disabled={disabled}
                    activeOpacity={0.82}
                  >
                    <Text style={[styles.dayText, disabled && styles.dayTextDisabled, selected && styles.dayTextSelected]}>
                      {date ? date.getDate() : ''}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>
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
  birthdayField: {
    gap: 8,
  },
  dateButton: {
    minHeight: 64,
    borderRadius: 16,
    borderWidth: 1.5,
    borderColor: AuthColors.border,
    backgroundColor: AuthColors.inputBg,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    gap: 12,
  },
  dateCopy: {
    flex: 1,
    gap: 4,
  },
  dateValue: {
    color: AuthColors.text,
    fontSize: 16,
    fontWeight: '900',
  },
  datePlaceholder: {
    color: AuthColors.muted,
  },
  dateHint: {
    color: AuthColors.textSecondary,
    fontSize: 12,
    fontWeight: '600',
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
  calendarBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.62)',
    justifyContent: 'center',
    paddingHorizontal: 18,
  },
  calendarSheet: {
    borderRadius: 24,
    backgroundColor: '#202329',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.14)',
    padding: 18,
    gap: 14,
  },
  calendarHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  calendarTitle: {
    color: AuthColors.text,
    fontSize: 22,
    fontWeight: '900',
  },
  calendarSubtitle: {
    marginTop: 2,
    color: AuthColors.textSecondary,
    fontSize: 13,
    fontWeight: '600',
  },
  calendarClose: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(233,221,200,0.08)',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.12)',
  },
  yearControls: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  yearButton: {
    minWidth: 44,
    height: 38,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: AuthColors.inputBg,
    borderWidth: 1,
    borderColor: AuthColors.border,
  },
  yearButtonText: {
    color: AuthColors.accent,
    fontSize: 13,
    fontWeight: '900',
  },
  yearLabel: {
    flex: 1,
    textAlign: 'center',
    color: AuthColors.text,
    fontSize: 18,
    fontWeight: '900',
  },
  monthControls: {
    minHeight: 46,
    borderRadius: 16,
    paddingHorizontal: 8,
    backgroundColor: AuthColors.inputBg,
    borderWidth: 1,
    borderColor: AuthColors.border,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  monthArrow: {
    width: 38,
    height: 38,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  monthLabel: {
    color: AuthColors.text,
    fontSize: 16,
    fontWeight: '900',
  },
  weekRow: {
    flexDirection: 'row',
  },
  weekDay: {
    flex: 1,
    textAlign: 'center',
    color: AuthColors.muted,
    fontSize: 12,
    fontWeight: '900',
  },
  daysGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  dayCell: {
    width: `${100 / 7}%`,
    aspectRatio: 1,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 14,
  },
  dayCellSelected: {
    backgroundColor: AuthColors.accent,
  },
  dayText: {
    color: AuthColors.text,
    fontSize: 14,
    fontWeight: '800',
  },
  dayTextDisabled: {
    color: 'rgba(216,205,187,0.24)',
  },
  dayTextSelected: {
    color: AuthColors.buttonText,
    fontWeight: '900',
  },
});
