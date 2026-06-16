import React, { useEffect, useRef } from 'react';
import { Animated, View, Text, StyleSheet, TouchableOpacity, SafeAreaView, Platform, Image, StatusBar } from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { DEFAULT_RESTAURANT_LOGO_PATH } from '../../constants/branding';
import { formatImageUrl } from '../../services/api';

export default function LoginScreen() {
  const router = useRouter();
  const logoUri = formatImageUrl(DEFAULT_RESTAURANT_LOGO_PATH);

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
      <StatusBar barStyle="light-content" backgroundColor="#24272D" />
      <View style={styles.container}>
        <Animated.View style={[styles.logoArea, logoStyle]}>
          <View style={styles.logoWrap}>
            {logoUri ? (
              <Image source={{ uri: logoUri }} style={styles.logoImage} resizeMode="contain" />
            ) : (
              <Ionicons name="restaurant" size={64} color="#E9DDC8" />
            )}
          </View>
          <Text style={styles.appName}>AMARE</Text>
          <Text style={styles.tagline}>Restaurant Club</Text>
        </Animated.View>

        <Animated.View style={[styles.buttons, btnsStyle]}>
          <TouchableOpacity
            style={styles.emailBtn}
            onPress={() => router.push('/(auth)/email-login')}
            activeOpacity={0.88}
          >
            <Ionicons name="mail-outline" size={20} color="#24272D" />
            <Text style={styles.emailLabel}>Entrar con correo</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.registerLink} onPress={() => router.push('/(auth)/register')}>
            <Text style={styles.registerText}>
              No tienes cuenta? <Text style={styles.registerBold}>Registrate</Text>
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
    backgroundColor: '#24272D',
  },
  container: {
    flex: 1,
    paddingHorizontal: 28,
    justifyContent: 'center',
    paddingBottom: Platform.OS === 'ios' ? 30 : 40,
    backgroundColor: '#24272D',
  },
  logoArea: {
    alignItems: 'center',
    marginBottom: 52,
  },
  logoWrap: {
    width: 320,
    height: 270,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 18,
  },
  logoImage: {
    width: 310,
    height: 260,
  },
  appName: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 46,
    color: '#F2EBDD',
    letterSpacing: 0,
    marginBottom: 8,
  },
  tagline: {
    fontSize: 16,
    color: '#D8CDBB',
    textAlign: 'center',
    lineHeight: 22,
    paddingHorizontal: 20,
    fontWeight: '600',
  },
  buttons: {
    gap: 13,
  },
  emailBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
    backgroundColor: '#E9DDC8',
    borderRadius: 16,
    paddingVertical: 18,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 6,
    },
    shadowOpacity: 0.22,
    shadowRadius: 14,
    elevation: 6,
  },
  emailLabel: {
    fontSize: 16,
    fontWeight: '800',
    color: '#24272D',
  },
  registerLink: {
    alignItems: 'center',
    marginTop: 12,
  },
  registerText: {
    color: '#D8CDBB',
    fontSize: 14,
  },
  registerBold: {
    color: '#F4E8D2',
    fontWeight: '800',
  },
});
