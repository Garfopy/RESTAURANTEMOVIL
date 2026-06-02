import React, { useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, SafeAreaView, Platform } from 'react-native';
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withSpring,
  withDelay,
  withTiming,
} from 'react-native-reanimated';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Colors, Spacing } from '../../theme';

export default function LoginScreen() {
  const router = useRouter();

  const logoOpacity = useSharedValue(0);
  const logoScale = useSharedValue(0.7);
  const btnsOpacity = useSharedValue(0);
  const btnsTranslate = useSharedValue(40);

  useEffect(() => {
    logoOpacity.value = withTiming(1, { duration: 600 });
    logoScale.value = withSpring(1, { damping: 14 });
    btnsOpacity.value = withDelay(400, withTiming(1, { duration: 600 }));
    btnsTranslate.value = withDelay(400, withSpring(0, { damping: 16 }));
  }, []);

  const logoStyle = useAnimatedStyle(() => ({
    opacity: logoOpacity.value,
    transform: [{ scale: logoScale.value }],
  }));

  const btnsStyle = useAnimatedStyle(() => ({
    opacity: btnsOpacity.value,
    transform: [{ translateY: btnsTranslate.value }],
  }));

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.container}>
        {/* Logo */}
        <Animated.View style={[styles.logoArea, logoStyle]}>
          <View style={styles.logoCircle}>
            <Ionicons name="restaurant" size={48} color={Colors.accent} />
          </View>
          <Text style={styles.appName}>Amare</Text>
          <Text style={styles.tagline}>Gastronomía premium a tu puerta</Text>
        </Animated.View>

        {/* Botones */}
        <Animated.View style={[styles.buttons, btnsStyle]}>
          <TouchableOpacity
            style={styles.googleBtn}
            onPress={() => router.push('/(auth)/google-auth')}
            activeOpacity={0.88}
          >
            <Ionicons name="logo-google" size={20} color={Colors.text} />
            <Text style={styles.googleLabel}>Continuar con Google</Text>
          </TouchableOpacity>

          <View style={styles.divider}>
            <View style={styles.dividerLine} />
            <Text style={styles.dividerText}>o</Text>
            <View style={styles.dividerLine} />
          </View>

          <TouchableOpacity
            style={styles.emailBtn}
            onPress={() => router.push('/(auth)/email-login')}
            activeOpacity={0.88}
          >
            <Ionicons name="mail-outline" size={20} color={Colors.white} />
            <Text style={styles.emailLabel}>Entrar con correo</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.registerLink}
            onPress={() => router.push('/(auth)/register')}
          >
            <Text style={styles.registerText}>
              ¿No tienes cuenta?{' '}
              <Text style={styles.registerBold}>Regístrate</Text>
            </Text>
          </TouchableOpacity>
        </Animated.View>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.primary },
  container: {
    flex: 1,
    paddingHorizontal: Spacing['2xl'],
    justifyContent: 'space-between',
    paddingTop: 80,
    paddingBottom: Platform.OS === 'ios' ? 40 : 60,
  },
  logoArea: { alignItems: 'center', gap: Spacing.sm },
  logoCircle: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: 'rgba(232,160,32,0.15)',
    borderWidth: 2,
    borderColor: Colors.accent,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: Spacing.sm,
  },
  appName: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 48,
    color: Colors.white,
    letterSpacing: 2,
  },
  tagline: {
    fontSize: 15,
    color: 'rgba(255,255,255,0.65)',
    letterSpacing: 0.4,
    textAlign: 'center',
  },
  buttons: { gap: Spacing.sm },
  googleBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    backgroundColor: Colors.white,
    borderRadius: 14,
    paddingVertical: 16,
  },
  googleLabel: { fontSize: 16, fontWeight: '600', color: Colors.text },
  divider: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    marginVertical: Spacing.xs,
  },
  dividerLine: { flex: 1, height: 1, backgroundColor: 'rgba(255,255,255,0.2)' },
  dividerText: { color: 'rgba(255,255,255,0.5)', fontSize: 13 },
  emailBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    backgroundColor: 'rgba(255,255,255,0.12)',
    borderRadius: 14,
    paddingVertical: 16,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.2)',
  },
  emailLabel: { fontSize: 16, fontWeight: '600', color: Colors.white },
  registerLink: { alignItems: 'center', marginTop: Spacing.xs },
  registerText: { color: 'rgba(255,255,255,0.6)', fontSize: 14 },
  registerBold: { color: Colors.accent, fontWeight: '700' },
});
