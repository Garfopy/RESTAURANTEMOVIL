import React, { useEffect, useRef } from 'react';
import { Animated, View, Text, StyleSheet, TouchableOpacity, SafeAreaView, Platform } from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Colors, Spacing } from '../../theme';

export default function LoginScreen() {
  const router = useRouter();

  const logoOpacity = useRef(new Animated.Value(0)).current;
  const logoScale = useRef(new Animated.Value(0.7)).current;
  const btnsOpacity = useRef(new Animated.Value(0)).current;
  const btnsTranslate = useRef(new Animated.Value(40)).current;

  useEffect(() => {
    Animated.parallel([
      Animated.timing(logoOpacity, { toValue: 1, duration: 600, useNativeDriver: true }),
      Animated.spring(logoScale, { toValue: 1, damping: 14, useNativeDriver: true } as any),
      Animated.sequence([
        Animated.delay(400),
        Animated.parallel([
          Animated.timing(btnsOpacity, { toValue: 1, duration: 600, useNativeDriver: true }),
          Animated.spring(btnsTranslate, { toValue: 0, damping: 16, useNativeDriver: true } as any),
        ]),
      ]),
    ]).start();
  }, []);

  const logoStyle = { opacity: logoOpacity, transform: [{ scale: logoScale }] };
  const btnsStyle = { opacity: btnsOpacity, transform: [{ translateY: btnsTranslate }] };

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.container}>
        {/* Logo */}
        <Animated.View style={[styles.logoArea, logoStyle]}>
          <View style={styles.logoCircle}>
            <Ionicons
              name="restaurant"
              size={54}
              color={Colors.primary}
            />
          </View>
          <Text style={styles.appName}>Restaurante 1</Text>
          <Text style={styles.tagline}>Gastronomía premium a tu puerta</Text>
        </Animated.View>

        {/* Botones */}
<Animated.View style={[styles.buttons, btnsStyle]}>

  {/* Apple */}
  <TouchableOpacity
    style={styles.googleBtn}
    onPress={() => router.push('/(auth)/apple-auth')}
    activeOpacity={0.88}
  >
    <Ionicons
      name="logo-apple"
      size={20}
      color={Colors.text}
    />
    <Text style={styles.googleLabel}>
      Continuar con Apple
    </Text>
  </TouchableOpacity>

  {/* Google */}
  <TouchableOpacity
    style={styles.googleBtn}
    onPress={() => router.push('/(auth)/google-auth')}
    activeOpacity={0.88}
  >
    <Ionicons
      name="logo-google"
      size={20}
      color={Colors.text}
    />
    <Text style={styles.googleLabel}>
      Continuar con Google
    </Text>
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
    <Ionicons
      name="mail-outline"
      size={20}
      color={Colors.white}
    />
    <Text style={styles.emailLabel}>
      Entrar con correo
    </Text>
  </TouchableOpacity>

  <TouchableOpacity
    style={styles.registerLink}
    onPress={() => router.push('/(auth)/register')}
  >
    <Text style={styles.registerText}>
      ¿No tienes cuenta?{' '}
      <Text style={styles.registerBold}>
        Regístrate
      </Text>
    </Text>
  </TouchableOpacity>

</Animated.View>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },

  container: {
    flex: 1,
    paddingHorizontal: 28,
    justifyContent: 'center',
    paddingBottom: Platform.OS === 'ios' ? 30 : 40,
  },

  logoArea: {
    alignItems: 'center',
    marginBottom: 70,
  },

  logoCircle: {
    width: 110,
    height: 110,
    borderRadius: 55,

    backgroundColor: '#FFF8E8',

    borderWidth: 2,
    borderColor: Colors.accent,

    alignItems: 'center',
    justifyContent: 'center',

    marginBottom: 20,

    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 8,
    },
    shadowOpacity: 0.08,
    shadowRadius: 16,
    elevation: 8,
  },

  appName: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 42,
    color: Colors.text,
    letterSpacing: 1,
    marginBottom: 8,
  },

  tagline: {
    fontSize: 15,
    color: '#6B7280',
    textAlign: 'center',
    lineHeight: 22,
    paddingHorizontal: 20,
  },

  buttons: {
    gap: 14,
  },

  googleBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',

    gap: 12,

    backgroundColor: '#FFFFFF',

    borderWidth: 1,
    borderColor: '#E5E7EB',

    borderRadius: 18,

    paddingVertical: 18,

    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 3,
    },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },

  googleLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: Colors.text,
  },

  divider: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginVertical: 8,
  },

  dividerLine: {
    flex: 1,
    height: 1,
    backgroundColor: '#E5E7EB',
  },

  dividerText: {
    color: '#9CA3AF',
    fontSize: 13,
    fontWeight: '500',
  },

  emailBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',

    gap: 12,

    backgroundColor: Colors.primary,

    borderRadius: 18,

    paddingVertical: 18,

    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 6,
    },
    shadowOpacity: 0.12,
    shadowRadius: 10,
    elevation: 5,
  },

  emailLabel: {
    fontSize: 16,
    fontWeight: '700',
    color: '#FFFFFF',
  },

  registerLink: {
    alignItems: 'center',
    marginTop: 10,
  },

  registerText: {
    color: '#6B7280',
    fontSize: 14,
  },

  registerBold: {
    color: Colors.primary,
    fontWeight: '700',
  },
});
