import React from 'react';
import { Linking, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Colors, Spacing } from '../../theme';

const SUPPORT_EMAIL = 'soporte@amarerestaurant.club';
const SUPPORT_URL = 'https://amarerestaurant.club/soporte';

const FAQS = [
  {
    title: 'Pago en proceso',
    body: 'Si Stripe aprobo el cobro pero se corto la conexion, no repitas el pago. Abre el pedido y espera la conciliacion automatica.',
  },
  {
    title: 'Saldo Amare y reembolsos',
    body: 'El saldo promocional se usa primero. Puedes solicitar el reembolso del saldo comprado que no hayas utilizado; puntos y promociones no son reembolsables.',
  },
  {
    title: 'Eliminar mi cuenta',
    body: 'Ve a Perfil > Eliminar cuenta. Si tienes saldo comprado disponible, Amare inicia su reembolso antes de completar la eliminacion.',
  },
  {
    title: 'Reportes y bloqueo',
    body: 'Desde cada perfil social puedes reportar o bloquear. El equipo de moderacion revisa los reportes y puede suspender cuentas.',
  },
];

export default function HelpScreen() {
  const router = useRouter();

  function emailSupport() {
    void Linking.openURL(`mailto:${SUPPORT_EMAIL}?subject=Ayuda%20con%20Amare`);
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.iconButton} onPress={() => router.back()} accessibilityLabel="Volver">
          <Ionicons name="chevron-back" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.title}>Centro de ayuda</Text>
        <View style={styles.iconButton} />
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.contactSection}>
          <Text style={styles.contactTitle}>Estamos para ayudarte</Text>
          <Text style={styles.contactText}>{SUPPORT_EMAIL}</Text>
          <TouchableOpacity style={styles.primaryButton} onPress={emailSupport} activeOpacity={0.85}>
            <Ionicons name="mail-outline" size={20} color="#FFFFFF" />
            <Text style={styles.primaryButtonText}>Escribir a soporte</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.secondaryButton} onPress={() => void Linking.openURL(SUPPORT_URL)} activeOpacity={0.85}>
            <Ionicons name="open-outline" size={19} color={Colors.text} />
            <Text style={styles.secondaryButtonText}>Abrir soporte web</Text>
          </TouchableOpacity>
        </View>

        <Text style={styles.sectionTitle}>Preguntas frecuentes</Text>
        <View style={styles.faqList}>
          {FAQS.map((faq) => (
            <View key={faq.title} style={styles.faqItem}>
              <Text style={styles.faqTitle}>{faq.title}</Text>
              <Text style={styles.faqBody}>{faq.body}</Text>
            </View>
          ))}
        </View>

        <View style={styles.linksRow}>
          <TouchableOpacity onPress={() => router.push('/legal/terms' as never)}>
            <Text style={styles.link}>Terminos</Text>
          </TouchableOpacity>
          <TouchableOpacity onPress={() => router.push('/legal/privacy' as never)}>
            <Text style={styles.link}>Privacidad</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    minHeight: 58,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  iconButton: { width: 40, height: 40, alignItems: 'center', justifyContent: 'center' },
  title: { fontSize: 19, fontWeight: '800', color: Colors.text },
  content: { padding: Spacing.base, paddingBottom: 40, gap: 20 },
  contactSection: { gap: 11 },
  contactTitle: { fontSize: 22, fontWeight: '800', color: Colors.text },
  contactText: { fontSize: 14, color: Colors.textSecondary },
  primaryButton: {
    minHeight: 52,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
    backgroundColor: Colors.primary,
    borderRadius: 8,
  },
  primaryButtonText: { color: '#FFFFFF', fontSize: 15, fontWeight: '800' },
  secondaryButton: {
    minHeight: 50,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: 8,
  },
  secondaryButtonText: { color: Colors.text, fontSize: 14, fontWeight: '700' },
  sectionTitle: { fontSize: 16, fontWeight: '800', color: Colors.text },
  faqList: { borderTopWidth: 1, borderTopColor: Colors.border },
  faqItem: { paddingVertical: 16, borderBottomWidth: 1, borderBottomColor: Colors.border },
  faqTitle: { fontSize: 15, fontWeight: '800', color: Colors.text },
  faqBody: { marginTop: 6, fontSize: 13, lineHeight: 20, color: Colors.textSecondary },
  linksRow: { flexDirection: 'row', justifyContent: 'center', gap: 28, paddingTop: 4 },
  link: { color: Colors.primary, fontSize: 14, fontWeight: '700' },
});
