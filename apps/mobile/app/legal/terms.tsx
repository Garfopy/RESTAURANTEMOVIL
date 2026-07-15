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
import { Colors, Typography } from '../../theme';

const TERMS_URL = 'https://amarerestaurant.club/legal/terminos';

const TERMS_SECTIONS = [
  {
    title: 'Uso de la cuenta',
    body:
      'La cuenta Amare es personal. El comensal es responsable de mantener actualizada su información, proteger su acceso y avisar si detecta un uso no autorizado.',
  },
  {
    title: 'Pedidos, mesa y consumo',
    body:
      'La app permite consultar menú, hacer pedidos, usar QR de mesa, seleccionar modalidades de consumo y dar seguimiento a órdenes. La disponibilidad de productos, precios y horarios puede variar por sucursal.',
  },
  {
    title: 'Pagos, saldo y recompensas',
    body:
      'Los pagos se procesan mediante proveedores autorizados. Saldo Amare, puntos, cupones y promociones pueden estar sujetos a vigencia, reglas de uso, disponibilidad por sucursal y validaciones antifraude.',
  },
  {
    title: 'Modo social',
    body:
      'El modo social es opcional. Al activarlo, el comensal decide qué datos mostrar, como nombre, fotos, edad, intereses y preferencias sociales. Puede apagarlo o editar su perfil cuando lo necesite.',
  },
  {
    title: 'Promociones de cumpleaños',
    body:
      'La fecha de nacimiento se usa para validar mayoría de edad y preparar beneficios como promociones de cumpleaños. Algunas promociones pueden requerir activación, consumo mínimo o estar limitadas a ciertas sucursales.',
  },
  {
    title: 'Privacidad y datos personales',
    body:
      'Amare usa los datos de la cuenta para operar la app, procesar pedidos, mejorar la experiencia, enviar avisos relevantes y cumplir obligaciones legales. El comensal puede solicitar acceso, corrección o eliminación conforme a la legislación aplicable.',
  },
  {
    title: 'Cambios del servicio',
    body:
      'Amare puede actualizar funciones, condiciones, beneficios o políticas para mejorar el servicio. Cuando el cambio sea relevante, se informará dentro de la app o por los canales disponibles.',
  },
];

export default function LegalTermsScreen() {
  const router = useRouter();

  async function openWebTerms() {
    await Linking.openURL(TERMS_URL);
  }

  return (
    <LinearGradient colors={['#17191E', '#23262D', '#17191E']} style={styles.gradient}>
      <SafeAreaView style={styles.safe}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton} onPress={() => router.back()} activeOpacity={0.82}>
            <Ionicons name="chevron-back" size={22} color="#F6F0E6" />
          </TouchableOpacity>
          <View style={styles.headerCopy}>
            <Text style={styles.eyebrow}>AVISO LEGAL</Text>
            <Text style={styles.title}>Términos y condiciones</Text>
          </View>
        </View>

        <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
          <View style={styles.introCard}>
            <View style={styles.introIcon}>
              <Ionicons name="document-text-outline" size={24} color="#24272D" />
            </View>
            <Text style={styles.introTitle}>Resumen para comensales</Text>
            <Text style={styles.introText}>
              Este aviso resume las reglas principales para usar Amare dentro de la app. La versión web oficial puede
              complementar o actualizar este contenido.
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
              Para dudas sobre tu cuenta, pedidos, privacidad o condiciones del servicio, contacta a Amare desde los
              canales oficiales del restaurante o la versión web.
            </Text>
          </View>

          <TouchableOpacity style={styles.webButton} onPress={openWebTerms} activeOpacity={0.88}>
            <Ionicons name="open-outline" size={18} color="#24272D" />
            <Text style={styles.webButtonText}>Abrir versión web oficial</Text>
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
  contactCard: {
    borderRadius: 18,
    padding: 16,
    backgroundColor: 'rgba(233,221,200,0.08)',
    borderWidth: 1,
    borderColor: 'rgba(233,221,200,0.14)',
  },
  contactTitle: {
    color: '#F6F0E6',
    fontSize: 15,
    fontWeight: '900',
  },
  contactText: {
    color: '#BDB4A5',
    fontSize: 13,
    lineHeight: 20,
    marginTop: 7,
  },
  webButton: {
    minHeight: 56,
    borderRadius: 18,
    backgroundColor: '#E9DDC8',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
    marginTop: 2,
  },
  webButtonText: {
    color: '#24272D',
    fontSize: 15,
    fontWeight: '900',
  },
});
