import React from 'react';
import {
  Linking,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Typography } from '../../theme';

const TERMS_URL = 'https://uteqcafeteria.com/legal/terminos';
const PRIVACY_URL = 'https://uteqcafeteria.com/aviso-de-privacidad';

const TERMS_SECTIONS = [
  {
    title: 'Uso de la cuenta',
    body:
      'Tu cuenta en UTEQ Cafetería es personal. Debes mantener tus datos actualizados, cuidar tu acceso y avisarnos si detectas uso no autorizado.',
  },
  {
    title: 'Pedidos y recolección',
    body:
      'La app permite consultar el menú, hacer pedidos para recoger en cafetería y dar seguimiento a tus órdenes. Productos, precios y horarios pueden variar.',
  },
  {
    title: 'Pagos y saldo',
    body:
      'Los pagos con tarjeta, Apple Pay y Google Pay se procesan mediante Stripe. El saldo comprado se separa del saldo promocional: se consume primero el promocional y después el comprado. El saldo comprado no utilizado puede solicitarse en reembolso al método de pago original; puntos, cupones y saldo promocional no son transferibles ni reembolsables.',
  },
  {
    title: 'Eliminación de cuenta',
    body:
      'Puedes solicitar la eliminación de tu cuenta desde Perfil. Eliminaremos o anonimizaremos datos personales; algunos registros de pedidos, pagos o facturación pueden conservarse por obligaciones legales.',
  },
];

export default function LegalTermsScreen() {
  const router = useRouter();

  async function openUrl(url: string) {
    await Linking.openURL(url);
  }

  return (
    <LinearGradient colors={['#2B1E14', '#4A3524', '#1A120C']} style={styles.gradient}>
      <SafeAreaView style={styles.safe}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton} onPress={() => router.back()} activeOpacity={0.82}>
            <Ionicons name="chevron-back" size={22} color="#FBF3E8" />
          </TouchableOpacity>
          <View style={styles.headerCopy}>
            <Text style={styles.eyebrow}>AVISO LEGAL</Text>
            <Text style={styles.title}>Terminos y condiciones</Text>
          </View>
        </View>

        <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
          <View style={styles.introCard}>
            <View style={styles.introIcon}>
              <Ionicons name="document-text-outline" size={24} color="#2B1E14" />
            </View>
            <Text style={styles.introTitle}>Resumen para comensales</Text>
            <Text style={styles.introText}>
              Este resumen cubre las reglas principales para usar UTEQ Cafetería. La version web oficial puede complementar
              o actualizar esta informacion.
            </Text>
          </View>

          <View style={styles.sectionList}>
            {TERMS_SECTIONS.map((section) => (
              <View key={section.title} style={styles.legalSection}>
                <Text style={styles.sectionTitle}>{section.title}</Text>
                <Text style={styles.sectionBody}>{section.body}</Text>
              </View>
            ))}
          </View>

          <View style={styles.contactCard}>
            <Text style={styles.contactTitle}>Contacto</Text>
            <Text style={styles.contactText}>
              Para dudas sobre tu cuenta, pedidos, pagos, reembolsos o privacidad escribe a
              contacto@uteqcafeteria.com.
            </Text>
          </View>

          <TouchableOpacity style={styles.webButton} onPress={() => openUrl(TERMS_URL)} activeOpacity={0.88}>
            <Ionicons name="open-outline" size={18} color="#2B1E14" />
            <Text style={styles.webButtonText}>Abrir terminos web</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.secondaryButton} onPress={() => openUrl(PRIVACY_URL)} activeOpacity={0.88}>
            <Ionicons name="shield-checkmark-outline" size={18} color="#D4B384" />
            <Text style={styles.secondaryButtonText}>Abrir aviso de privacidad</Text>
          </TouchableOpacity>
        </ScrollView>
      </SafeAreaView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  gradient: { flex: 1 },
  safe: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    paddingHorizontal: 20,
    paddingTop: 10,
    paddingBottom: 14,
  },
  backButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.08)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
  },
  headerCopy: { flex: 1 },
  eyebrow: {
    color: '#C9AF95',
    fontSize: 10,
    fontWeight: '900',
    letterSpacing: 2,
  },
  title: {
    fontFamily: 'PlayfairDisplay_700Bold',
    color: '#FBF3E8',
    fontSize: 27,
    lineHeight: 34,
    marginTop: 2,
  },
  content: {
    paddingHorizontal: 20,
    paddingBottom: 40,
    gap: 16,
  },
  introCard: {
    borderRadius: 24,
    padding: 18,
    backgroundColor: 'rgba(255,255,255,0.075)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
  },
  introIcon: {
    width: 46,
    height: 46,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#D4B384',
    marginBottom: 14,
  },
  introTitle: {
    color: '#FBF3E8',
    fontSize: 18,
    fontWeight: '900',
  },
  introText: {
    ...Typography.body,
    color: '#C9AF95',
    marginTop: 8,
    lineHeight: 21,
  },
  sectionList: { gap: 10 },
  legalSection: {
    borderRadius: 18,
    padding: 16,
    backgroundColor: 'rgba(23,25,30,0.35)',
    borderWidth: 1,
    borderColor: 'rgba(212,179,132,0.16)',
  },
  sectionTitle: {
    color: '#D4B384',
    fontSize: 15,
    fontWeight: '900',
    marginBottom: 7,
  },
  sectionBody: {
    color: '#E3D2BE',
    fontSize: 13,
    lineHeight: 20,
    fontWeight: '500',
  },
  contactCard: {
    borderRadius: 18,
    padding: 16,
    backgroundColor: 'rgba(212,179,132,0.1)',
    borderWidth: 1,
    borderColor: 'rgba(212,179,132,0.18)',
  },
  contactTitle: {
    color: '#FBF3E8',
    fontSize: 15,
    fontWeight: '900',
  },
  contactText: {
    color: '#C9AF95',
    fontSize: 13,
    lineHeight: 20,
    marginTop: 7,
  },
  webButton: {
    minHeight: 56,
    borderRadius: 18,
    backgroundColor: '#D4B384',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
    marginTop: 2,
  },
  webButtonText: {
    color: '#2B1E14',
    fontSize: 15,
    fontWeight: '900',
  },
  secondaryButton: {
    minHeight: 52,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: 'rgba(212,179,132,0.24)',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
  },
  secondaryButtonText: {
    color: '#D4B384',
    fontSize: 14,
    fontWeight: '900',
  },
});
