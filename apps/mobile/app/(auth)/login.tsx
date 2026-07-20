import React, { useEffect, useRef } from 'react';
import {
  Animated,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Image,
  Platform,
  StatusBar,
  useWindowDimensions,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { GoogleGIcon } from '../../components/ui/GoogleGIcon';

const BENEFITS = [
  { icon: 'sparkles-outline' as const, label: 'Experiencias' },
  { icon: 'restaurant-outline' as const, label: 'Mesa y delivery' },
  { icon: 'gift-outline' as const, label: 'Momentos' },
];

const LOGIN_LOGO = require('../../assets/amare_logo_login.png');

export default function LoginScreen() {
  const router = useRouter();
  const { height } = useWindowDimensions();
  const hero = useRef(new Animated.Value(0)).current;
  const actions = useRef(new Animated.Value(0)).current;
  const glow = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.stagger(170, [
      Animated.spring(hero, { toValue: 1, damping: 18, stiffness: 90, useNativeDriver: true }),
      Animated.spring(actions, { toValue: 1, damping: 18, stiffness: 100, useNativeDriver: true }),
    ]).start();
    Animated.loop(
      Animated.sequence([
        Animated.timing(glow, { toValue: 1, duration: 2600, useNativeDriver: true }),
        Animated.timing(glow, { toValue: 0, duration: 2600, useNativeDriver: true }),
      ])
    ).start();
  }, [actions, glow, hero]);

  function navigate(path: '/(auth)/email-login' | '/(auth)/register' | '/(auth)/google-auth') {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    router.push(path);
  }

  const compact = height < 740;
  const showGoogleSignIn = true;

  return (
    <LinearGradient colors={['#17191E', '#23262D', '#17191E']} style={styles.gradient}>
      <SafeAreaView style={styles.safe}>
        <StatusBar barStyle="light-content" backgroundColor="#17191E" />
        <View pointerEvents="none" style={styles.decorations}>
          <Animated.View
            style={[
              styles.glow,
              styles.glowTop,
              { opacity: glow.interpolate({ inputRange: [0, 1], outputRange: [0.25, 0.5] }) },
            ]}
          />
          <View style={[styles.glow, styles.glowBottom]} />
          <View style={styles.fineLine} />
        </View>

        <View style={[styles.container, compact && styles.containerCompact]}>
          <Animated.View
            style={[
              styles.hero,
              {
                opacity: hero,
                transform: [
                  { translateY: hero.interpolate({ inputRange: [0, 1], outputRange: [26, 0] }) },
                  { scale: hero.interpolate({ inputRange: [0, 1], outputRange: [0.96, 1] }) },
                ],
              },
            ]}
          >
            <View style={styles.eyebrowRow}>
              <View style={styles.eyebrowLine} />
              <Text style={styles.eyebrow}>RESTAURANT CONNECTING CLUB</Text>
              <View style={styles.eyebrowLine} />
            </View>

            <View style={[styles.logoHalo, compact && styles.logoHaloCompact]}>
              <LinearGradient colors={['rgba(242,235,221,0.16)', 'rgba(242,235,221,0.03)']} style={styles.logoHaloInner}>
                <Image source={LOGIN_LOGO} style={styles.logoImage} resizeMode="contain" />
              </LinearGradient>
            </View>

            <Text style={styles.tagline}>La mesa es solo el comienzo.</Text>

            <View style={styles.benefits}>
              {BENEFITS.map((benefit) => (
                <View key={benefit.label} style={styles.benefit}>
                  <View style={styles.benefitIcon}>
                    <Ionicons name={benefit.icon} size={15} color="#E9DDC8" />
                  </View>
                  <Text style={styles.benefitText}>{benefit.label}</Text>
                </View>
              ))}
            </View>
          </Animated.View>

          <Animated.View
            style={[
              styles.actionsCard,
              {
                opacity: actions,
                transform: [{ translateY: actions.interpolate({ inputRange: [0, 1], outputRange: [34, 0] }) }],
              },
            ]}
          >
            {showGoogleSignIn ? (
              <TouchableOpacity style={styles.googleButton} onPress={() => navigate('/(auth)/google-auth')} activeOpacity={0.9}>
                <View style={styles.googleIcon}>
                  <GoogleGIcon size={20} />
                </View>
                <Text style={styles.googleLabel}>Continuar con Google</Text>
                <Ionicons name="arrow-forward" size={19} color="#24272D" />
              </TouchableOpacity>
            ) : null}

            <TouchableOpacity style={styles.primaryButton} onPress={() => navigate('/(auth)/email-login')} activeOpacity={0.9}>
              <View style={styles.primaryIcon}>
                <Ionicons name="mail-outline" size={19} color="#24272D" />
              </View>
              <Text style={styles.primaryLabel}>Continuar con correo</Text>
              <Ionicons name="arrow-forward" size={19} color="#24272D" />
            </TouchableOpacity>

            <TouchableOpacity style={styles.secondaryButton} onPress={() => navigate('/(auth)/register')} activeOpacity={0.86}>
              <Text style={styles.secondaryLabel}>Crear una cuenta</Text>
            </TouchableOpacity>

            <Text style={styles.legal}>Al continuar aceptas nuestros términos y aviso de privacidad.</Text>
            <TouchableOpacity
              style={styles.legalLinkButton}
              onPress={() => router.push('/legal/terms' as never)}
              activeOpacity={0.82}
            >
              <Text style={styles.legalLink}>Ver términos y aviso legal</Text>
            </TouchableOpacity>
          </Animated.View>
        </View>
      </SafeAreaView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  gradient: { flex: 1 },
  safe: { flex: 1 },
  decorations: { ...StyleSheet.absoluteFillObject, overflow: 'hidden' },
  glow: { position: 'absolute', borderRadius: 999, backgroundColor: '#C6A97B' },
  glowTop: { width: 340, height: 340, top: -210, right: -100 },
  glowBottom: { width: 260, height: 260, bottom: -170, left: -120, opacity: 0.1 },
  fineLine: { position: 'absolute', top: 86, left: 28, right: 28, height: 1, backgroundColor: 'rgba(242,235,221,0.08)' },
  container: { flex: 1, justifyContent: 'space-between', paddingHorizontal: 24, paddingTop: 30, paddingBottom: 18 },
  containerCompact: { paddingTop: 12 },
  hero: { alignItems: 'center', flex: 1, justifyContent: 'center' },
  eyebrowRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 12 },
  eyebrowLine: { width: 24, height: 1, backgroundColor: 'rgba(233,221,200,0.45)' },
  eyebrow: { color: '#CDBFA8', fontSize: 10, fontWeight: '800', letterSpacing: 2.4 },
  logoHalo: { width: 210, height: 210, borderRadius: 105, padding: 1, borderWidth: 1, borderColor: 'rgba(233,221,200,0.16)' },
  logoHaloCompact: { width: 174, height: 174, borderRadius: 87 },
  logoHaloInner: { flex: 1, borderRadius: 999, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  logoImage: { width: '92%', height: '92%' },
  appName: { marginTop: 16, fontFamily: 'PlayfairDisplay_700Bold', fontSize: 42, color: '#F6F0E6', letterSpacing: 7 },
  tagline: { marginTop: 5, fontFamily: 'PlayfairDisplay_700Bold', fontSize: 21, color: '#E9DDC8', textAlign: 'center' },
  description: { maxWidth: 330, marginTop: 10, color: '#AAA396', fontSize: 13, lineHeight: 19, textAlign: 'center' },
  benefits: { flexDirection: 'row', marginTop: 20, gap: 9 },
  benefit: { alignItems: 'center', gap: 6, minWidth: 84 },
  benefitIcon: { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(233,221,200,0.08)', borderWidth: 1, borderColor: 'rgba(233,221,200,0.12)' },
  benefitText: { color: '#BDB4A5', fontSize: 10, fontWeight: '700' },
  actionsCard: { borderRadius: 26, padding: 10, backgroundColor: 'rgba(255,255,255,0.055)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.09)' },
  googleButton: { minHeight: 58, borderRadius: 18, backgroundColor: '#FFFFFF', flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, gap: 12, marginBottom: 9 },
  googleIcon: { width: 34, height: 34, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(36,39,45,0.06)' },
  googleLabel: { flex: 1, color: '#24272D', fontSize: 15, fontWeight: '900' },
  primaryButton: { minHeight: 58, borderRadius: 18, backgroundColor: '#E9DDC8', flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, gap: 12 },
  primaryIcon: { width: 34, height: 34, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(36,39,45,0.08)' },
  primaryLabel: { flex: 1, color: '#24272D', fontSize: 15, fontWeight: '900' },
  secondaryButton: { minHeight: 50, alignItems: 'center', justifyContent: 'center' },
  secondaryLabel: { color: '#F2EBDD', fontSize: 14, fontWeight: '800' },
  legal: { color: '#77746E', fontSize: 10, textAlign: 'center' },
  legalLinkButton: { alignSelf: 'center', paddingTop: 4, paddingBottom: 2, paddingHorizontal: 8 },
  legalLink: { color: '#E9DDC8', fontSize: 11, fontWeight: '800', textAlign: 'center' },
});
