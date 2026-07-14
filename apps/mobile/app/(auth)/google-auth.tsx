import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, ActivityIndicator, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import Constants from 'expo-constants';

// @react-native-google-signin requiere build nativo — no disponible en Expo Go
let GoogleSignin: any = null;
let statusCodes: Record<string, string> = {};
try {
  const mod = require('@react-native-google-signin/google-signin');
  GoogleSignin = mod.GoogleSignin;
  statusCodes = mod.statusCodes ?? {};
} catch {
  // silenciado en Expo Go
}
import { useUserStore } from '../../store/user.store';
import { loginWithGoogle } from '../../services/auth.service';
import { Colors, Typography } from '../../theme';

// Configura el Web Client ID en app.json o env
const WEB_CLIENT_ID =
  process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID ??
  (Constants.expoConfig?.extra?.googleWebClientId as string | undefined) ??
  '';

export default function GoogleAuthScreen() {
  const router = useRouter();
  const login = useUserStore((s) => s.login);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!GoogleSignin) {
      setError('Google Sign-In requiere una build nativa o dev client. No funciona dentro de Expo Go.');
      return;
    }

    if (!WEB_CLIENT_ID) {
      setError('Falta configurar EXPO_PUBLIC_GOOGLE_CLIENT_ID para iniciar sesion con Google.');
      return;
    }

    GoogleSignin.configure({
      webClientId: WEB_CLIENT_ID,
      offlineAccess: false,
    });
    signIn();
  }, []);

  async function signIn() {
    try {
      await GoogleSignin.hasPlayServices();
      const userInfo = await GoogleSignin.signIn();
      const idToken = userInfo.data?.idToken ?? userInfo.idToken;
      if (!idToken) throw new Error('No se obtuvo el ID token de Google');

      const sesion = await loginWithGoogle({
        id_token: idToken,
        platform: Platform.OS === 'ios' ? 'ios' : 'android',
      });
      await login(sesion);
      // AuthGuard en _layout.tsx redirigirá a (tabs) automáticamente
    } catch (err: unknown) {
      const code = (err as { code?: string })?.code;
      if (code === statusCodes.SIGN_IN_CANCELLED) {
        router.back();
        return;
      }
      setError('No se pudo iniciar sesión con Google. Intenta de nuevo.');
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      {error ? (
        <View style={styles.center}>
          <Text style={styles.errorText}>{error}</Text>
          <Text style={styles.link} onPress={() => router.back()}>
            Volver
          </Text>
        </View>
      ) : (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={Colors.accent} />
          <Text style={styles.msg}>Autenticando con Google…</Text>
        </View>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 16 },
  msg: { ...Typography.body, color: Colors.textMuted },
  errorText: { ...Typography.body, color: Colors.error, textAlign: 'center', paddingHorizontal: 32 },
  link: { ...Typography.body, color: Colors.accent, fontWeight: '600' },
});
