import React, { useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { apiClient, getApiError } from '../../services/api';
import { getMe } from '../../services/auth.service';
import { validateEmail, validatePhone } from '../../services/error.service';
import { useUserStore } from '../../store/user.store';
import { Colors, Shadows } from '../../theme';

type StepState = {
  key: 'email' | 'phone';
  title: string;
  value: string;
  done: boolean;
  icon: keyof typeof Ionicons.glyphMap;
};

export default function AccountValidationScreen() {
  const router = useRouter();
  const user = useUserStore((state) => state.user);
  const setUser = useUserStore((state) => state.setUser);
  const [email, setEmail] = useState(user?.email ?? '');
  const [phone, setPhone] = useState(user?.telefono ?? '');
  const [saving, setSaving] = useState(false);

  const cleanEmail = email.trim().toLowerCase();
  const cleanPhone = phone.replace(/\D/g, '');
  const emailRegistered = Boolean(cleanEmail);
  const phoneRegistered = Boolean(cleanPhone);
  const steps = useMemo<StepState[]>(
    () => [
      {
        key: 'email',
        title: 'Correo agregado',
        value: cleanEmail || 'Pendiente',
        done: emailRegistered,
        icon: 'mail-outline',
      },
      {
        key: 'phone',
        title: 'Teléfono registrado',
        value: cleanPhone || 'Pendiente',
        done: phoneRegistered,
        icon: 'call-outline',
      },
    ],
    [cleanEmail, cleanPhone, emailRegistered, phoneRegistered]
  );
  const completed = steps.filter((step) => step.done).length;
  const progress = completed / steps.length;
  const complete = completed === steps.length;

  async function handleSave() {
    const emailError = validateEmail(cleanEmail);
    const phoneError = validatePhone(cleanPhone);

    if (emailError || phoneError) {
      Alert.alert('Revisa tus datos', emailError || phoneError || 'Completa la información.');
      return;
    }

    setSaving(true);
    try {
      await apiClient.put('/profile', {
        email: cleanEmail,
        telefono: cleanPhone,
      });
      const updated = await getMe();
      setUser(updated);
      Alert.alert('Datos guardados', 'Tu cuenta ya muestra el progreso actualizado.');
    } catch (error) {
      Alert.alert('No se pudo guardar', getApiError(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <KeyboardAvoidingView
        style={styles.keyboard}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton} onPress={() => router.back()} activeOpacity={0.8}>
            <Ionicons name="chevron-back" size={22} color="#111827" />
          </TouchableOpacity>
          <View style={styles.headerCopy}>
            <Text style={styles.kicker}>Mi cuenta</Text>
            <Text style={styles.title}>Validar cuenta</Text>
          </View>
        </View>

        <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
          <View style={styles.progressCard}>
            <View style={styles.progressTop}>
              <View>
                <Text style={styles.progressLabel}>{complete ? 'Cuenta completa' : 'Faltan datos'}</Text>
                <Text style={styles.progressTitle}>{completed} de 2 pasos</Text>
              </View>
              <View style={[styles.progressBadge, complete && styles.progressBadgeDone]}>
                <Ionicons
                  name={complete ? 'shield-checkmark' : 'shield-outline'}
                  size={24}
                  color={complete ? '#047857' : '#B45309'}
                />
              </View>
            </View>
            <View style={styles.progressTrack}>
              <View style={[styles.progressFill, { width: `${Math.max(8, progress * 100)}%` }]} />
            </View>
            <Text style={styles.progressText}>
              Completa tu correo y teléfono para que podamos identificar tu cuenta y contactarte si hay un pedido o factura pendiente.
            </Text>
          </View>

          <View style={styles.stepsCard}>
            {steps.map((step, index) => (
              <View key={step.key} style={[styles.stepRow, index < steps.length - 1 && styles.stepDivider]}>
                <View style={[styles.stepIcon, step.done && styles.stepIconDone]}>
                  <Ionicons name={step.done ? 'checkmark' : step.icon} size={18} color={step.done ? '#FFFFFF' : '#64748B'} />
                </View>
                <View style={styles.stepCopy}>
                  <Text style={styles.stepTitle}>{step.title}</Text>
                  <Text style={styles.stepValue} numberOfLines={1}>{step.value}</Text>
                </View>
                <Text style={[styles.stepStatus, step.done && styles.stepStatusDone]}>
                  {step.done ? 'Listo' : 'Pendiente'}
                </Text>
              </View>
            ))}
          </View>

          <View style={styles.formCard}>
            <Text style={styles.formTitle}>Datos de contacto</Text>
            <View style={styles.field}>
              <Text style={styles.fieldLabel}>Correo electrónico</Text>
              <TextInput
                value={email}
                onChangeText={setEmail}
                placeholder="correo@ejemplo.com"
                placeholderTextColor="#94A3B8"
                keyboardType="email-address"
                autoCapitalize="none"
                autoComplete="email"
                style={styles.input}
              />
            </View>
            <View style={styles.field}>
              <Text style={styles.fieldLabel}>Teléfono</Text>
              <TextInput
                value={phone}
                onChangeText={setPhone}
                placeholder="55 1234 5678"
                placeholderTextColor="#94A3B8"
                keyboardType="phone-pad"
                autoComplete="tel"
                style={styles.input}
              />
            </View>
            <TouchableOpacity style={styles.saveButton} onPress={handleSave} disabled={saving} activeOpacity={0.88}>
              {saving ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.saveButtonText}>Guardar datos</Text>}
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
    backgroundColor: Colors.background || '#F4F6F8',
  },
  keyboard: {
    flex: 1,
  },
  header: {
    paddingHorizontal: 20,
    paddingTop: 10,
    paddingBottom: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  backButton: {
    width: 42,
    height: 42,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E1E7EF',
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerCopy: {
    flex: 1,
  },
  kicker: {
    color: '#64748B',
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  title: {
    color: '#111827',
    fontSize: 28,
    fontWeight: '900',
  },
  content: {
    padding: 20,
    paddingTop: 4,
    paddingBottom: 36,
    gap: 14,
  },
  progressCard: {
    borderRadius: 8,
    backgroundColor: '#111827',
    padding: 16,
    gap: 14,
    ...Shadows.sm,
  },
  progressTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  progressLabel: {
    color: '#CBD5E1',
    fontSize: 12,
    fontWeight: '800',
  },
  progressTitle: {
    color: '#FFFFFF',
    fontSize: 26,
    fontWeight: '900',
    marginTop: 2,
  },
  progressBadge: {
    width: 48,
    height: 48,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FFF7ED',
  },
  progressBadgeDone: {
    backgroundColor: '#ECFDF5',
  },
  progressTrack: {
    height: 8,
    borderRadius: 4,
    backgroundColor: 'rgba(255,255,255,0.14)',
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    borderRadius: 4,
    backgroundColor: '#10B981',
  },
  progressText: {
    color: '#D1D5DB',
    fontSize: 13,
    lineHeight: 19,
    fontWeight: '700',
  },
  stepsCard: {
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E1E7EF',
    overflow: 'hidden',
  },
  stepRow: {
    minHeight: 72,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  stepDivider: {
    borderBottomWidth: 1,
    borderBottomColor: '#F1F5F9',
  },
  stepIcon: {
    width: 38,
    height: 38,
    borderRadius: 8,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepIconDone: {
    backgroundColor: '#10B981',
  },
  stepCopy: {
    flex: 1,
    minWidth: 0,
  },
  stepTitle: {
    color: '#111827',
    fontSize: 15,
    fontWeight: '900',
  },
  stepValue: {
    color: '#64748B',
    fontSize: 13,
    fontWeight: '700',
    marginTop: 2,
  },
  stepStatus: {
    color: '#B45309',
    fontSize: 12,
    fontWeight: '900',
  },
  stepStatusDone: {
    color: '#047857',
  },
  formCard: {
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E1E7EF',
    padding: 16,
    gap: 14,
  },
  formTitle: {
    color: '#111827',
    fontSize: 18,
    fontWeight: '900',
  },
  field: {
    gap: 7,
  },
  fieldLabel: {
    color: '#475569',
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  input: {
    minHeight: 52,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#CBD5E1',
    backgroundColor: '#F8FAFC',
    paddingHorizontal: 14,
    color: '#111827',
    fontSize: 15,
    fontWeight: '800',
  },
  saveButton: {
    minHeight: 52,
    borderRadius: 8,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  saveButtonText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '900',
  },
});
