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

const PRIVACY_URL = 'https://uteqcafeteria.com/aviso-de-privacidad';
const DELETE_URL = 'https://uteqcafeteria.com/eliminar-cuenta';

const PRIVACY_SECTIONS = [
  {
    title: 'Datos que usamos',
    body:
      'Cuenta, teléfono, correo, fecha de nacimiento, pedidos, pagos, facturación, foto de perfil y tokens de notificaciones.',
  },
  {
    title: 'Para que se usan',
    body:
      'Procesar tus pedidos para recoger en cafetería, procesar pagos, emitir facturas, enviarte avisos sobre tus pedidos, administrar recompensas y proteger tu cuenta.',
  },
  {
    title: 'Proveedores',
    body:
      'Stripe procesa tarjeta, Apple Pay, Google Pay y reembolsos; UTEQ Cafetería no almacena numeros completos de tarjeta ni codigos de seguridad. Tambien podemos usar Google/Firebase para inicio de sesion y notificaciones, y proveedores fiscales para facturacion.',
  },
  {
    title: 'Eliminacion y retencion',
    body:
      'Al eliminar tu cuenta borramos o anonimizamos datos personales. Podemos conservar registros de pedidos, pagos y facturas cuando sean necesarios por seguridad, operacion o cumplimiento legal.',
  },
];

export default function PrivacyScreen() {
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
            <Text style={styles.eyebrow}>PRIVACIDAD</Text>
            <Text style={styles.title}>Tus datos en UTEQ Cafetería</Text>
          </View>
        </View>

        <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
          <View style={styles.introCard}>
            <View style={styles.introIcon}>
              <Ionicons name="shield-checkmark-outline" size={24} color="#2B1E14" />
            </View>
            <Text style={styles.introTitle}>Control y transparencia</Text>
            <Text style={styles.introText}>
              Este resumen te ayuda a entender que datos usa UTEQ Cafetería. La version web oficial contiene el aviso completo
              y los medios formales para ejercer tus derechos.
            </Text>
          </View>

          <View style={styles.sectionList}>
            {PRIVACY_SECTIONS.map((section) => (
              <View key={section.title} style={styles.legalSection}>
                <Text style={styles.sectionTitle}>{section.title}</Text>
                <Text style={styles.sectionBody}>{section.body}</Text>
              </View>
            ))}
          </View>

          <TouchableOpacity style={styles.webButton} onPress={() => openUrl(PRIVACY_URL)} activeOpacity={0.88}>
            <Ionicons name="open-outline" size={18} color="#2B1E14" />
            <Text style={styles.webButtonText}>Abrir aviso completo</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.secondaryButton} onPress={() => openUrl(DELETE_URL)} activeOpacity={0.88}>
            <Ionicons name="trash-outline" size={18} color="#D4B384" />
            <Text style={styles.secondaryButtonText}>Solicitud web de eliminacion</Text>
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
  webButton: {
    minHeight: 56,
    borderRadius: 18,
    backgroundColor: '#D4B384',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
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
