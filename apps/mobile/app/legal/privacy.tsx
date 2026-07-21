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

const PRIVACY_URL = 'https://amarerestaurant.club/aviso-de-privacidad';
const DELETE_URL = 'https://amarerestaurant.club/eliminar-cuenta';

const PRIVACY_SECTIONS = [
  {
    title: 'Datos que usamos',
    body:
      'Cuenta, telefono, correo, fecha de nacimiento, direcciones, ubicacion aproximada o precisa cuando la autorizas, pedidos, pagos, facturacion, fotos, perfil social y tokens de notificaciones.',
  },
  {
    title: 'Para que se usan',
    body:
      'Operar pedidos y mesas, procesar pagos, calcular entregas, mostrar sucursales cercanas, emitir facturas, enviar avisos, administrar recompensas, proteger la cuenta y moderar el modo social.',
  },
  {
    title: 'Proveedores',
    body:
      'Stripe procesa tarjeta, Apple Pay, Google Pay y reembolsos; Amare no almacena numeros completos de tarjeta ni codigos de seguridad. Tambien podemos usar Google/Firebase para inicio de sesion y notificaciones, mapas/ubicacion del sistema y proveedores fiscales para facturacion.',
  },
  {
    title: 'Modo social',
    body:
      'El modo social es opcional. Otros comensales activos de la sucursal pueden ver los datos que decidas publicar. Puedes reportar o bloquear perfiles desde la app.',
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
    <LinearGradient colors={['#17191E', '#23262D', '#17191E']} style={styles.gradient}>
      <SafeAreaView style={styles.safe}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton} onPress={() => router.back()} activeOpacity={0.82}>
            <Ionicons name="chevron-back" size={22} color="#F6F0E6" />
          </TouchableOpacity>
          <View style={styles.headerCopy}>
            <Text style={styles.eyebrow}>PRIVACIDAD</Text>
            <Text style={styles.title}>Tus datos en Amare</Text>
          </View>
        </View>

        <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
          <View style={styles.introCard}>
            <View style={styles.introIcon}>
              <Ionicons name="shield-checkmark-outline" size={24} color="#24272D" />
            </View>
            <Text style={styles.introTitle}>Control y transparencia</Text>
            <Text style={styles.introText}>
              Este resumen te ayuda a entender que datos usa Amare. La version web oficial contiene el aviso completo
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
            <Ionicons name="open-outline" size={18} color="#24272D" />
            <Text style={styles.webButtonText}>Abrir aviso completo</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.secondaryButton} onPress={() => openUrl(DELETE_URL)} activeOpacity={0.88}>
            <Ionicons name="trash-outline" size={18} color="#E9DDC8" />
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
    color: '#CDBFA8',
    fontSize: 10,
    fontWeight: '900',
    letterSpacing: 2,
  },
  title: {
    fontFamily: 'PlayfairDisplay_700Bold',
    color: '#F6F0E6',
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
    backgroundColor: '#E9DDC8',
    marginBottom: 14,
  },
  introTitle: {
    color: '#F6F0E6',
    fontSize: 18,
    fontWeight: '900',
  },
  introText: {
    ...Typography.body,
    color: '#BDB4A5',
    marginTop: 8,
    lineHeight: 21,
  },
  sectionList: { gap: 10 },
  legalSection: {
    borderRadius: 18,
    padding: 16,
    backgroundColor: 'rgba(23,25,30,0.58)',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.11)',
  },
  sectionTitle: {
    color: '#E9DDC8',
    fontSize: 15,
    fontWeight: '900',
    marginBottom: 7,
  },
  sectionBody: {
    color: '#CFC6B8',
    fontSize: 13,
    lineHeight: 20,
    fontWeight: '500',
  },
  webButton: {
    minHeight: 56,
    borderRadius: 18,
    backgroundColor: '#E9DDC8',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
  },
  webButtonText: {
    color: '#24272D',
    fontSize: 15,
    fontWeight: '900',
  },
  secondaryButton: {
    minHeight: 52,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.18)',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
  },
  secondaryButtonText: {
    color: '#E9DDC8',
    fontSize: 14,
    fontWeight: '900',
  },
});
