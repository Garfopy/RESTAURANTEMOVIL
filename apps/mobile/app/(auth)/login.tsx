import React, { useEffect, useRef } from 'react';
import {
  Animated,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Image,
  ImageBackground,
  StatusBar,
  ScrollView,
  useWindowDimensions,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { GoogleGIcon } from '../../components/ui/GoogleGIcon';
import { AppleSignInButton } from '../../components/auth/AppleSignInButton';
import { GOOGLE_SIGNIN_ENABLED, APPLE_SIGNIN_ENABLED } from '../../constants/features';

const BENEFITS = [
  { icon: 'restaurant-outline' as const, label: 'Menú del día' },
  { icon: 'bag-handle-outline' as const, label: 'Pedido para recoger' },
  { icon: 'gift-outline' as const, label: 'Puntos y recompensas' },
];

const LOGIN_LOGO = require('../../assets/amare_logo_login.png');
const LOGIN_BACKGROUND = require('../../assets/cafeteria_bg.png');

export default function LoginScreen() {
  const router = useRouter();
  const { returnTo } = useLocalSearchParams<{ returnTo?: string }>();
  const { height } = useWindowDimensions();
  const hero = useRef(new Animated.Value(0)).current;
  const actions = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.stagger(170, [
      Animated.spring(hero, { toValue: 1, damping: 18, stiffness: 90, useNativeDriver: true }),
      Animated.spring(actions, { toValue: 1, damping: 18, stiffness: 100, useNativeDriver: true }),
    ]).start();
  }, [actions, hero]);

  function navigate(path: '/(auth)/email-login' | '/(auth)/register' | '/(auth)/google-auth') {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    router.push({ pathname: path, params: returnTo ? { returnTo } : undefined } as never);
  }

  const compact = height < 740;
  const showGoogleSignIn = GOOGLE_SIGNIN_ENABLED;

  return (
    <ImageBackground source={LOGIN_BACKGROUND} style={styles.background} resizeMode="cover">
      <LinearGradient
        colors={['rgba(10,8,6,0.25)', 'rgba(10,8,6,0.55)', 'rgba(8,6,4,0.92)']}
        locations={[0, 0.5, 1]}
        style={StyleSheet.absoluteFill}
      />
      <SafeAreaView style={styles.safe}>
        <StatusBar barStyle="light-content" translucent backgroundColor="transparent" />

        <ScrollView
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
          bounces={false}
        >
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
              <View style={styles.logoHaloRing} />
              <View style={styles.logoHaloInner}>
                <Image source={LOGIN_LOGO} style={styles.logoImage} resizeMode="contain" />
              </View>
            </View>

            <Text style={styles.tagline}>La mesa es solo el comienzo.</Text>

            <View style={styles.benefits}>
              {BENEFITS.map((benefit) => (
                <BlurView key={benefit.label} intensity={30} tint="dark" style={styles.benefit}>
                  <View style={styles.benefitIcon}>
                    <Ionicons name={benefit.icon} size={15} color="#FFFFFF" />
                  </View>
                  <Text style={styles.benefitText}>{benefit.label}</Text>
                </BlurView>
              ))}
            </View>
          </Animated.View>

          <Animated.View
            style={[
              styles.actionsWrap,
              {
                opacity: actions,
                transform: [{ translateY: actions.interpolate({ inputRange: [0, 1], outputRange: [34, 0] }) }],
              },
            ]}
          >
            <View style={styles.actionsCard}>
              {showGoogleSignIn ? (
                <TouchableOpacity style={styles.googleButton} onPress={() => navigate('/(auth)/google-auth')} activeOpacity={0.9}>
                  <View style={styles.googleIcon}>
                    <GoogleGIcon size={20} />
                  </View>
                  <Text style={styles.googleLabel}>Continuar con Google</Text>
                  <Ionicons name="arrow-forward" size={19} color="#2B1E14" />
                </TouchableOpacity>
              ) : null}

              {APPLE_SIGNIN_ENABLED ? <AppleSignInButton /> : null}

              <TouchableOpacity style={styles.primaryButton} onPress={() => navigate('/(auth)/email-login')} activeOpacity={0.9}>
                <View style={styles.primaryIcon}>
                  <Ionicons name="mail-outline" size={19} color="#FFFFFF" />
                </View>
                <Text style={styles.primaryLabel}>Continuar con correo</Text>
                <Ionicons name="arrow-forward" size={19} color="#FFFFFF" />
              </TouchableOpacity>

              <TouchableOpacity style={styles.secondaryButton} onPress={() => navigate('/(auth)/register')} activeOpacity={0.86}>
                <Text style={styles.secondaryLabel}>Crear una cuenta</Text>
              </TouchableOpacity>
            </View>

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
        </ScrollView>
      </SafeAreaView>
    </ImageBackground>
  );
}

const styles = StyleSheet.create({
  background: { flex: 1 },
  safe: { flex: 1 },
  scrollContent: { flexGrow: 1 },
  container: { flex: 1, justifyContent: 'space-between', paddingHorizontal: 24, paddingTop: 30, paddingBottom: 18 },
  containerCompact: { paddingTop: 12 },
  hero: { alignItems: 'center', flex: 1, justifyContent: 'center' },
  eyebrowRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 14 },
  eyebrowLine: { width: 24, height: 1, backgroundColor: 'rgba(255,255,255,0.4)' },
  eyebrow: { color: '#F2E9DC', fontSize: 10, fontWeight: '800', letterSpacing: 2.4 },
  logoHalo: { width: 200, height: 200, alignItems: 'center', justifyContent: 'center' },
  logoHaloCompact: { width: 168, height: 168 },
  logoHaloRing: {
    ...StyleSheet.absoluteFillObject,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.4)',
  },
  logoHaloInner: {
    width: '92%',
    height: '92%',
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
    backgroundColor: '#FFFFFF',
    shadowColor: '#000000',
    shadowOpacity: 0.35,
    shadowRadius: 26,
    shadowOffset: { width: 0, height: 12 },
    elevation: 10,
  },
  logoImage: { width: '96%', height: '96%' },
  tagline: { marginTop: 18, fontFamily: 'PlayfairDisplay_700Bold', fontSize: 24, color: '#FFFFFF', textAlign: 'center' },
  benefits: { flexDirection: 'row', marginTop: 20, gap: 9 },
  benefit: {
    alignItems: 'center',
    gap: 6,
    minWidth: 84,
    paddingVertical: 10,
    borderRadius: 16,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.2)',
  },
  benefitIcon: { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(255,255,255,0.14)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.22)' },
  benefitText: { color: '#F2E9DC', fontSize: 10, fontWeight: '700' },
  actionsWrap: { gap: 10 },
  actionsCard: {
    borderRadius: 26,
    padding: 10,
    backgroundColor: '#FFFFFF',
  },
  googleButton: { minHeight: 58, borderRadius: 18, backgroundColor: '#FFFFFF', flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, gap: 12, marginBottom: 9, borderWidth: 1, borderColor: 'rgba(20,20,20,0.1)' },
  googleIcon: { width: 34, height: 34, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(20,20,20,0.05)' },
  googleLabel: { flex: 1, color: '#2B1E14', fontFamily: 'Inter_700Bold', fontSize: 15, letterSpacing: 0 },
  primaryButton: { minHeight: 58, borderRadius: 18, backgroundColor: '#2B1E14', flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, gap: 12 },
  primaryIcon: { width: 34, height: 34, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(255,255,255,0.12)' },
  primaryLabel: { flex: 1, color: '#FFFFFF', fontFamily: 'Inter_700Bold', fontSize: 15, letterSpacing: 0 },
  secondaryButton: { minHeight: 50, alignItems: 'center', justifyContent: 'center' },
  secondaryLabel: { color: '#2B1E14', fontSize: 14, fontWeight: '800' },
  legal: { color: 'rgba(255,255,255,0.6)', fontSize: 10, textAlign: 'center' },
  legalLinkButton: { alignSelf: 'center', paddingTop: 4, paddingBottom: 2, paddingHorizontal: 8 },
  legalLink: { color: '#FFFFFF', fontSize: 11, fontWeight: '800', textAlign: 'center' },
});
