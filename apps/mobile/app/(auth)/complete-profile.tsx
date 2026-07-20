import React, { useMemo, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { cancelProfileOnboarding, completeProfile } from '../../services/auth.service';
import { getApiError } from '../../services/api';
import { useUserStore } from '../../store/user.store';
import { Colors, Typography } from '../../theme';

const MONTHS = [
  'Enero',
  'Febrero',
  'Marzo',
  'Abril',
  'Mayo',
  'Junio',
  'Julio',
  'Agosto',
  'Septiembre',
  'Octubre',
  'Noviembre',
  'Diciembre',
];

const WEEK_DAYS = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];

function onlyDigits(value: string): string {
  return value.replace(/\D+/g, '');
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

export default function CompleteProfileScreen() {
  const router = useRouter();
  const user = useUserStore((state) => state.user);
  const setUser = useUserStore((state) => state.setUser);
  const logout = useUserStore((state) => state.logout);
  const [phone, setPhone] = useState(() => {
    const current = onlyDigits(user?.telefono ?? '');
    return current.startsWith('52') && current.length === 12 ? current.slice(2) : current;
  });
  const [birthday, setBirthday] = useState(user?.fecha_nacimiento ?? '');
  const [calendarVisible, setCalendarVisible] = useState(false);
  const [calendarMonth, setCalendarMonth] = useState(() => {
    const selected = parseBirthDate(user?.fecha_nacimiento);
    return clampMonth(selected ?? getAgeBounds().max);
  });
  const [termsAccepted, setTermsAccepted] = useState(Boolean(user?.terms_accepted_at));
  const [marketingOptIn, setMarketingOptIn] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [canceling, setCanceling] = useState(false);

  const phoneDigits = useMemo(() => onlyDigits(phone), [phone]);
  const calendarCells = useMemo(() => getCalendarCells(calendarMonth), [calendarMonth]);
  const selectedBirthday = useMemo(() => parseBirthDate(birthday), [birthday]);
  const canSubmit = phoneDigits.length === 10 && isAdultBirthday(birthday) && termsAccepted && !saving;

  function openCalendar() {
    setCalendarMonth(clampMonth(parseBirthDate(birthday) ?? getAgeBounds().max));
    setCalendarVisible(true);
  }

  function selectBirthday(date: Date) {
    setBirthday(toDateString(date));
    setCalendarVisible(false);
    setError(null);
    void Haptics.selectionAsync();
  }

  async function submit() {
    if (!canSubmit) {
      setError('Completa tu teléfono, selecciona una fecha válida y confirma que eres mayor de edad.');
      return;
    }

    try {
      setSaving(true);
      setError(null);
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      const updatedUser = await completeProfile({
        telefono: `52${phoneDigits}`,
        fecha_nacimiento: birthday,
        terms_accepted: true,
        marketing_opt_in: marketingOptIn,
      });
      setUser(updatedUser);
      router.replace('/(tabs)' as never);
    } catch (err) {
      setError(getApiError(err));
    } finally {
      setSaving(false);
    }
  }

  async function cancel() {
    try {
      setCanceling(true);
      setError(null);
      await cancelProfileOnboarding();
    } catch {
      // Si el servidor no puede borrar la cuenta, igual se aborta el flujo local.
    } finally {
      await logout();
      setCanceling(false);
      router.replace('/(auth)/login' as never);
    }
  }

  return (
    <LinearGradient colors={['#17191E', '#23262D', '#17191E']} style={styles.gradient}>
      <SafeAreaView style={styles.safe}>
        <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            keyboardDismissMode="interactive"
            showsVerticalScrollIndicator={false}
          >
            <View style={styles.header}>
              <View style={styles.badge}>
                <Ionicons name="sparkles-outline" size={22} color="#E9DDC8" />
              </View>
              <Text style={styles.title}>Completa tu perfil</Text>
              <Text style={styles.subtitle}>
                Usaremos estos datos para proteger tu cuenta y preparar beneficios como promos de cumpleaños.
              </Text>
            </View>

            <View style={styles.form}>
              <View style={styles.field}>
                <Text style={styles.label}>Teléfono</Text>
                <View style={styles.phoneRow}>
                  <View style={styles.prefix}>
                    <Text style={styles.prefixText}>+52</Text>
                  </View>
                  <TextInput
                    value={phone}
                    onChangeText={(value) => setPhone(onlyDigits(value).slice(0, 10))}
                    keyboardType="phone-pad"
                    textContentType="telephoneNumber"
                    placeholder="10 dígitos"
                    placeholderTextColor="#857D71"
                    style={styles.input}
                    maxLength={10}
                  />
                </View>
              </View>

              <View style={styles.field}>
                <Text style={styles.label}>Cumpleaños</Text>
                <TouchableOpacity style={styles.dateButton} onPress={openCalendar} activeOpacity={0.86}>
                  <View>
                    <Text style={[styles.dateValue, !birthday && styles.datePlaceholder]}>
                      {formatBirthdayLabel(birthday)}
                    </Text>
                    <Text style={styles.dateHint}>Debes tener 18 años o más</Text>
                  </View>
                  <Ionicons name="calendar-outline" size={22} color="#E9DDC8" />
                </TouchableOpacity>
              </View>

              <TouchableOpacity
                style={styles.checkRow}
                onPress={() => setTermsAccepted((current) => !current)}
                activeOpacity={0.85}
              >
                <View style={[styles.checkBox, termsAccepted && styles.checkBoxOn]}>
                  {termsAccepted ? <Ionicons name="checkmark" size={16} color="#24272D" /> : null}
                </View>
                <Text style={styles.checkText}>Acepto términos, condiciones y aviso de privacidad.</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.termsLinkButton}
                onPress={() => router.push('/legal/terms' as never)}
                activeOpacity={0.82}
              >
                <Ionicons name="document-text-outline" size={16} color="#E9DDC8" />
                <Text style={styles.termsLinkText}>Ver términos y aviso legal</Text>
              </TouchableOpacity>

              <View style={styles.switchRow}>
                <View style={styles.switchCopy}>
                  <Text style={styles.switchTitle}>Promos de cumpleaños</Text>
                  <Text style={styles.switchText}>Recibir beneficios, cupones y novedades de Amare.</Text>
                </View>
                <Switch
                  value={marketingOptIn}
                  onValueChange={setMarketingOptIn}
                  trackColor={{ false: '#55515A', true: '#E9DDC8' }}
                  thumbColor={marketingOptIn ? Colors.accent : '#F4F1EA'}
                />
              </View>

              {error ? <Text style={styles.errorText}>{error}</Text> : null}

              <TouchableOpacity
                style={[styles.primaryButton, !canSubmit && styles.primaryButtonDisabled]}
                onPress={submit}
                activeOpacity={0.9}
                disabled={!canSubmit}
              >
                {saving ? (
                  <ActivityIndicator color="#24272D" />
                ) : (
                  <>
                    <Text style={styles.primaryLabel}>Entrar a Amare</Text>
                    <Ionicons name="arrow-forward" size={19} color="#24272D" />
                  </>
                )}
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.exitButton}
                onPress={cancel}
                activeOpacity={0.8}
                disabled={canceling || saving}
              >
                {canceling ? (
                  <ActivityIndicator size="small" color="#BDB4A5" />
                ) : (
                  <Text style={styles.exitText}>Cancelar</Text>
                )}
              </TouchableOpacity>
            </View>
          </ScrollView>

          <Modal visible={calendarVisible} transparent animationType="fade" onRequestClose={() => setCalendarVisible(false)}>
            <View style={styles.modalBackdrop}>
              <View style={styles.calendarSheet}>
                <View style={styles.calendarHeader}>
                  <View>
                    <Text style={styles.calendarTitle}>Fecha de nacimiento</Text>
                    <Text style={styles.calendarSubtitle}>Solo mayores de edad</Text>
                  </View>
                  <TouchableOpacity style={styles.calendarClose} onPress={() => setCalendarVisible(false)} activeOpacity={0.8}>
                    <Ionicons name="close" size={20} color="#F6F0E6" />
                  </TouchableOpacity>
                </View>

                <View style={styles.yearControls}>
                  <TouchableOpacity style={styles.yearButton} onPress={() => setCalendarMonth((current) => moveYear(current, -10))}>
                    <Text style={styles.yearButtonText}>-10</Text>
                  </TouchableOpacity>
                  <TouchableOpacity style={styles.yearButton} onPress={() => setCalendarMonth((current) => moveYear(current, -1))}>
                    <Ionicons name="chevron-back" size={18} color="#E9DDC8" />
                  </TouchableOpacity>
                  <Text style={styles.yearLabel}>{calendarMonth.getFullYear()}</Text>
                  <TouchableOpacity style={styles.yearButton} onPress={() => setCalendarMonth((current) => moveYear(current, 1))}>
                    <Ionicons name="chevron-forward" size={18} color="#E9DDC8" />
                  </TouchableOpacity>
                  <TouchableOpacity style={styles.yearButton} onPress={() => setCalendarMonth((current) => moveYear(current, 10))}>
                    <Text style={styles.yearButtonText}>+10</Text>
                  </TouchableOpacity>
                </View>

                <View style={styles.monthControls}>
                  <TouchableOpacity style={styles.monthArrow} onPress={() => setCalendarMonth((current) => moveMonth(current, -1))}>
                    <Ionicons name="chevron-back" size={18} color="#24272D" />
                  </TouchableOpacity>
                  <Text style={styles.monthLabel}>{MONTHS[calendarMonth.getMonth()]}</Text>
                  <TouchableOpacity style={styles.monthArrow} onPress={() => setCalendarMonth((current) => moveMonth(current, 1))}>
                    <Ionicons name="chevron-forward" size={18} color="#24272D" />
                  </TouchableOpacity>
                </View>

                <View style={styles.weekRow}>
                  {WEEK_DAYS.map((day, index) => (
                    <Text key={`${day}-${index}`} style={styles.weekDay}>
                      {day}
                    </Text>
                  ))}
                </View>

                <View style={styles.daysGrid}>
                  {calendarCells.map((date, index) => {
                    const dateString = date ? toDateString(date) : '';
                    const disabled = !date || !isAdultBirthday(dateString);
                    const selected = Boolean(
                      date &&
                        selectedBirthday &&
                        dateString === toDateString(selectedBirthday)
                    );
                    return (
                      <TouchableOpacity
                        key={date ? dateString : `empty-${index}`}
                        style={[styles.dayCell, selected && styles.dayCellSelected]}
                        disabled={disabled}
                        onPress={() => date && selectBirthday(date)}
                        activeOpacity={0.78}
                      >
                        <Text style={[styles.dayText, disabled && styles.dayTextDisabled, selected && styles.dayTextSelected]}>
                          {date ? date.getDate() : ''}
                        </Text>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </View>
            </View>
          </Modal>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  gradient: { flex: 1 },
  safe: { flex: 1 },
  flex: { flex: 1 },
  content: { flexGrow: 1, justifyContent: 'center', padding: 24, gap: 28 },
  header: { alignItems: 'center' },
  badge: {
    width: 58,
    height: 58,
    borderRadius: 29,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(233,221,200,0.09)',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.16)',
    marginBottom: 16,
  },
  title: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 31,
    lineHeight: 38,
    color: '#F6F0E6',
    textAlign: 'center',
  },
  subtitle: {
    ...Typography.body,
    color: '#BDB4A5',
    textAlign: 'center',
    marginTop: 10,
    maxWidth: 330,
  },
  form: {
    borderRadius: 26,
    padding: 16,
    backgroundColor: 'rgba(255,255,255,0.065)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    gap: 15,
  },
  field: { gap: 8 },
  label: { color: '#E9DDC8', fontSize: 12, fontWeight: '800' },
  phoneRow: {
    flexDirection: 'row',
    minHeight: 56,
    borderRadius: 18,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.16)',
    backgroundColor: 'rgba(23,25,30,0.72)',
  },
  prefix: {
    width: 66,
    alignItems: 'center',
    justifyContent: 'center',
    borderRightWidth: 1,
    borderRightColor: 'rgba(233,221,200,0.12)',
  },
  prefixText: { color: '#E9DDC8', fontSize: 15, fontWeight: '900' },
  input: { flex: 1, color: '#F6F0E6', fontSize: 16, fontWeight: '700', paddingHorizontal: 14 },
  dateButton: {
    minHeight: 56,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.16)',
    backgroundColor: 'rgba(23,25,30,0.72)',
    paddingHorizontal: 14,
    paddingVertical: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  dateValue: { color: '#F6F0E6', fontSize: 16, fontWeight: '800' },
  datePlaceholder: { color: '#857D71' },
  dateHint: { color: '#AAA396', fontSize: 11, lineHeight: 15, marginTop: 2 },
  checkRow: { flexDirection: 'row', alignItems: 'center', gap: 11, paddingVertical: 2 },
  checkBox: {
    width: 24,
    height: 24,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.34)',
  },
  checkBoxOn: { backgroundColor: '#E9DDC8', borderColor: '#E9DDC8' },
  checkText: { flex: 1, color: '#CFC6B8', fontSize: 12, lineHeight: 17 },
  termsLinkButton: {
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    paddingVertical: 2,
  },
  termsLinkText: { color: '#E9DDC8', fontSize: 12, fontWeight: '800' },
  switchRow: {
    minHeight: 76,
    borderRadius: 18,
    backgroundColor: 'rgba(23,25,30,0.55)',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.12)',
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
  },
  switchCopy: { flex: 1 },
  switchTitle: { color: '#F6F0E6', fontSize: 14, fontWeight: '900' },
  switchText: { color: '#AAA396', fontSize: 12, lineHeight: 17, marginTop: 3 },
  errorText: { color: '#FFB4AD', fontSize: 12, lineHeight: 17, textAlign: 'center' },
  primaryButton: {
    minHeight: 58,
    borderRadius: 18,
    backgroundColor: '#E9DDC8',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  primaryButtonDisabled: { opacity: 0.48 },
  primaryLabel: { color: '#24272D', fontSize: 15, fontWeight: '900' },
  exitButton: { minHeight: 38, alignItems: 'center', justifyContent: 'center' },
  exitText: { color: '#BDB4A5', fontSize: 13, fontWeight: '800' },
  modalBackdrop: {
    flex: 1,
    justifyContent: 'center',
    padding: 18,
    backgroundColor: 'rgba(0,0,0,0.58)',
  },
  calendarSheet: {
    borderRadius: 26,
    padding: 16,
    backgroundColor: '#24272D',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.16)',
  },
  calendarHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 14,
  },
  calendarTitle: { color: '#F6F0E6', fontSize: 18, fontWeight: '900' },
  calendarSubtitle: { color: '#AAA396', fontSize: 12, marginTop: 3 },
  calendarClose: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.08)',
  },
  yearControls: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  yearButton: {
    minWidth: 42,
    height: 38,
    borderRadius: 13,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(233,221,200,0.08)',
  },
  yearButtonText: { color: '#E9DDC8', fontSize: 12, fontWeight: '900' },
  yearLabel: { flex: 1, color: '#F6F0E6', fontSize: 22, fontWeight: '900', textAlign: 'center' },
  monthControls: {
    minHeight: 48,
    borderRadius: 16,
    backgroundColor: '#E9DDC8',
    marginTop: 12,
    paddingHorizontal: 8,
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
  monthLabel: { color: '#24272D', fontSize: 16, fontWeight: '900' },
  weekRow: { flexDirection: 'row', marginTop: 14 },
  weekDay: { flex: 1, textAlign: 'center', color: '#BDB4A5', fontSize: 11, fontWeight: '900' },
  daysGrid: { flexDirection: 'row', flexWrap: 'wrap', marginTop: 8 },
  dayCell: {
    width: `${100 / 7}%`,
    aspectRatio: 1,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 13,
  },
  dayCellSelected: { backgroundColor: '#E9DDC8' },
  dayText: { color: '#F6F0E6', fontSize: 14, fontWeight: '800' },
  dayTextDisabled: { color: 'rgba(246,240,230,0.22)' },
  dayTextSelected: { color: '#24272D' },
});
