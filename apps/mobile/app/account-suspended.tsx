import React from 'react';
import { ScrollView, StatusBar, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useUserStore } from '../store/user.store';

const DEFAULT_NOTICE = {
  title: 'Cuenta suspendida',
  reason: 'Cuenta desactivada por moderacion',
  explanation:
    'Tu cuenta fue suspendida y no puede acceder a la app en este momento. Si consideras que fue un error, contacta al restaurante para solicitar una revision.',
  details: null,
  supportHint: 'Contacta al restaurante para revisar tu caso.',
};

export default function AccountSuspendedScreen() {
  const router = useRouter();
  const notice = useUserStore((state) => state.accountSuspension) ?? DEFAULT_NOTICE;
  const setAccountSuspension = useUserStore((state) => state.setAccountSuspension);

  function goToLogin() {
    setAccountSuspension(null);
    router.replace('/(auth)/login' as never);
  }

  return (
    <LinearGradient colors={['#17191E', '#262A31', '#17191E']} style={styles.gradient}>
      <SafeAreaView style={styles.safe}>
        <StatusBar barStyle="light-content" backgroundColor="#17191E" />
        <ScrollView contentContainerStyle={styles.container} showsVerticalScrollIndicator={false}>
          <View style={styles.iconWrap}>
            <Ionicons name="shield-outline" size={40} color="#F4D7A1" />
          </View>

          <Text style={styles.title}>{notice.title}</Text>
          <Text style={styles.subtitle}>No puedes ingresar con esta cuenta.</Text>

          <View style={styles.panel}>
            <InfoRow label="Motivo" value={notice.reason} />
            {notice.details ? <InfoRow label="Descripcion" value={notice.details} /> : null}
            <InfoRow label="Explicacion" value={notice.explanation} />
          </View>

          {notice.supportHint ? <Text style={styles.support}>{notice.supportHint}</Text> : null}

          <TouchableOpacity style={styles.primaryButton} onPress={goToLogin} activeOpacity={0.9}>
            <Text style={styles.primaryLabel}>Volver al inicio</Text>
          </TouchableOpacity>
        </ScrollView>
      </SafeAreaView>
    </LinearGradient>
  );
}

function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.infoRow}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  gradient: { flex: 1 },
  safe: { flex: 1 },
  container: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: 24,
    paddingVertical: 36,
  },
  iconWrap: {
    width: 82,
    height: 82,
    borderRadius: 41,
    alignItems: 'center',
    justifyContent: 'center',
    alignSelf: 'center',
    backgroundColor: 'rgba(244, 215, 161, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(244, 215, 161, 0.32)',
    marginBottom: 22,
  },
  title: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 34,
    lineHeight: 40,
    color: '#F7F0E5',
    textAlign: 'center',
  },
  subtitle: {
    fontFamily: 'Inter_500Medium',
    fontSize: 16,
    lineHeight: 23,
    color: '#C9BDAE',
    textAlign: 'center',
    marginTop: 10,
  },
  panel: {
    marginTop: 30,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.11)',
    backgroundColor: 'rgba(255,255,255,0.07)',
    overflow: 'hidden',
  },
  infoRow: {
    paddingHorizontal: 18,
    paddingVertical: 16,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: 'rgba(255,255,255,0.12)',
  },
  infoLabel: {
    fontFamily: 'Inter_700Bold',
    fontSize: 12,
    lineHeight: 16,
    color: '#F4D7A1',
    textTransform: 'uppercase',
    marginBottom: 6,
  },
  infoValue: {
    fontFamily: 'Inter_500Medium',
    fontSize: 15,
    lineHeight: 22,
    color: '#F3EADD',
  },
  support: {
    fontFamily: 'Inter_500Medium',
    fontSize: 14,
    lineHeight: 21,
    color: '#BFB4A7',
    textAlign: 'center',
    marginTop: 20,
  },
  primaryButton: {
    height: 54,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#E9DDC8',
    marginTop: 28,
  },
  primaryLabel: {
    fontFamily: 'Inter_700Bold',
    fontSize: 16,
    color: '#24272D',
  },
});
