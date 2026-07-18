import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Platform, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import Constants from 'expo-constants';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useUserStore } from '../../store/user.store';
import { loginWithGoogle } from '../../services/auth.service';
import { getApiError } from '../../services/api';
import { Colors, Typography } from '../../theme';
import { GoogleGIcon } from '../../components/ui/GoogleGIcon';
import { registerPushNotifications } from '../../services/push-notifications.service';

type GoogleSignInModule = {
  configure: (options: { webClientId: string; iosClientId?: string; offlineAccess?: boolean; scopes?: string[] }) => void;
  hasPlayServices: (options?: { showPlayServicesUpdateDialog?: boolean }) => Promise<boolean>;
  signIn: () => Promise<{ idToken?: string | null; data?: { idToken?: string | null } | null }>;
};

let GoogleSignin: GoogleSignInModule | null = null;
let statusCodes: Record<string, string> = {};

try {
  const mod = require('@react-native-google-signin/google-signin');
  GoogleSignin = mod.GoogleSignin;
  statusCodes = mod.statusCodes ?? {};
} catch {
  // Google Sign-In is only available in native builds, not Expo Go.
}

const DEFAULT_WEB_CLIENT_ID = '859009059542-0k2foa27gsah58utigs0kvp2nnsnvgnl.apps.googleusercontent.com';
const DEFAULT_IOS_CLIENT_ID = '859009059542-3c3159vd6maibhhgpjng9t63q5rjmsl1.apps.googleusercontent.com';
const WEB_CLIENT_ID =
  process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID ??
  (Constants.expoConfig?.extra?.googleWebClientId as string | undefined) ??
  DEFAULT_WEB_CLIENT_ID;

export default function GoogleAuthScreen() {
  const router = useRouter();
  const login = useUserStore((state) => state.login);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!GoogleSignin) {
      setError('Google Sign-In requiere una build nativa o dev client. No funciona dentro de Expo Go.');
      setLoading(false);
      return;
    }

    GoogleSignin.configure({
      webClientId: WEB_CLIENT_ID,
      iosClientId: DEFAULT_IOS_CLIENT_ID,
      offlineAccess: false,
      scopes: ['profile', 'email'],
    });

    void signIn();
  }, []);

  function goToLogin() {
    router.replace('/(auth)/login' as never);
  }

  function isGoogleSignInCancelled(authError: unknown): boolean {
    const code = (authError as { code?: string })?.code;
    const message = String((authError as { message?: string })?.message ?? '').toLowerCase();

    return (
      code === statusCodes.SIGN_IN_CANCELLED ||
      code === 'SIGN_IN_CANCELLED' ||
      code === '12501' ||
      message.includes('cancel')
    );
  }

  async function signIn() {
    try {
      setLoading(true);
      setError(null);

      if (!GoogleSignin) {
        throw new Error('Google Sign-In no esta disponible en esta build.');
      }

      if (Platform.OS === 'android') {
        await GoogleSignin.hasPlayServices({ showPlayServicesUpdateDialog: true });
      }

      const userInfo = await GoogleSignin.signIn();
      const idToken = userInfo.data?.idToken ?? userInfo.idToken;
      if (!idToken) {
        throw new Error('No se obtuvo el ID token de Google');
      }

      const sesion = await loginWithGoogle({
        id_token: idToken,
        platform: Platform.OS === 'ios' ? 'ios' : 'android',
      });
      await login(sesion);
      void registerPushNotifications({ force: true, reason: 'login-success', userId: sesion.user.id });
    } catch (authError: unknown) {
      if (isGoogleSignInCancelled(authError)) {
        goToLogin();
        return;
      }

      if (__DEV__) {
        console.warn('[GoogleAuth] Error al iniciar sesion con Google:', authError);
      }
      setError(getGoogleAuthErrorMessage(authError));
    } finally {
      setLoading(false);
    }
  }

  return (
    <LinearGradient colors={['#17191E', '#23262D', '#17191E']} style={styles.gradient}>
      <SafeAreaView style={styles.safe}>
        <View pointerEvents="none" style={styles.decorations}>
          <View style={[styles.glow, styles.glowTop]} />
          <View style={[styles.glow, styles.glowBottom]} />
        </View>

        <View style={styles.content}>
          <View style={styles.googleMark}>
            <GoogleGIcon size={34} />
          </View>

          <Text style={styles.title}>{error ? 'No pudimos entrar' : 'Conectando con Google'}</Text>
          <Text style={styles.subtitle}>
            {error
              ? 'Revisa tu conexion o intenta seleccionar tu cuenta de nuevo.'
              : 'Estamos validando tu cuenta para iniciar tu experiencia en Amare.'}
          </Text>

          <View style={styles.statusCard}>
            {error ? (
              <>
                <View style={styles.errorIcon}>
                  <Ionicons name="alert-circle-outline" size={22} color="#FFB4AD" />
                </View>
                <Text style={styles.errorText}>{error}</Text>
              </>
            ) : (
              <>
                <ActivityIndicator size="small" color={Colors.accent} />
                <Text style={styles.msg}>Autenticando con Google...</Text>
              </>
            )}
          </View>

          {error ? (
            <View style={styles.actions}>
              <TouchableOpacity style={styles.primaryButton} onPress={signIn} activeOpacity={0.9} disabled={loading}>
                {loading ? (
                  <ActivityIndicator color="#24272D" />
                ) : (
                  <>
                    <GoogleGIcon size={19} />
                    <Text style={styles.primaryLabel}>Intentar otra vez</Text>
                  </>
                )}
              </TouchableOpacity>

              <TouchableOpacity style={styles.secondaryButton} onPress={goToLogin} activeOpacity={0.84}>
                <Text style={styles.secondaryLabel}>Volver al inicio</Text>
              </TouchableOpacity>
            </View>
          ) : null}
        </View>
      </SafeAreaView>
    </LinearGradient>
  );
}

function getGoogleAuthErrorMessage(authError: unknown): string {
  const code = (authError as { code?: string })?.code;
  const rawMessage = (authError as { message?: string })?.message ?? '';

  if (code === 'DEVELOPER_ERROR' || rawMessage.includes('DEVELOPER_ERROR') || rawMessage.includes('10:')) {
    return 'Google Sign-In no esta configurado para esta build. Registra el SHA-1/SHA-256 de Android en Firebase y descarga el google-services.json actualizado.';
  }

  if (code === 'PLAY_SERVICES_NOT_AVAILABLE') {
    return 'Google Play Services no esta disponible o necesita actualizarse.';
  }

  if (rawMessage.includes('No se obtuvo el ID token')) {
    return 'Google no devolvio un token valido. Revisa que el Web Client ID sea el correcto.';
  }

  return getApiError(authError) || 'No se pudo iniciar sesion con Google. Intenta de nuevo.';
}

const styles = StyleSheet.create({
  gradient: { flex: 1 },
  safe: { flex: 1 },
  decorations: { ...StyleSheet.absoluteFillObject, overflow: 'hidden' },
  glow: { position: 'absolute', borderRadius: 999, backgroundColor: '#C6A97B' },
  glowTop: { width: 300, height: 300, top: -180, right: -110, opacity: 0.22 },
  glowBottom: { width: 230, height: 230, bottom: -140, left: -90, opacity: 0.1 },
  content: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 26 },
  googleMark: {
    width: 82,
    height: 82,
    borderRadius: 41,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.18)',
    marginBottom: 24,
  },
  title: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 32,
    lineHeight: 39,
    color: '#F6F0E6',
    textAlign: 'center',
  },
  subtitle: {
    ...Typography.body,
    color: '#BDB4A5',
    textAlign: 'center',
    marginTop: 10,
    maxWidth: 330,
  },
  statusCard: {
    width: '100%',
    maxWidth: 360,
    minHeight: 76,
    borderRadius: 22,
    paddingHorizontal: 18,
    paddingVertical: 16,
    marginTop: 28,
    backgroundColor: 'rgba(255,255,255,0.07)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  msg: { ...Typography.body, color: Colors.textMuted, textAlign: 'center' },
  errorIcon: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,180,173,0.1)',
  },
  errorText: { ...Typography.body, color: Colors.error, textAlign: 'center' },
  actions: { width: '100%', maxWidth: 360, marginTop: 18, gap: 10 },
  primaryButton: {
    minHeight: 58,
    borderRadius: 18,
    backgroundColor: '#FFFFFF',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  primaryLabel: { color: '#24272D', fontSize: 15, fontWeight: '900' },
  secondaryButton: { minHeight: 48, alignItems: 'center', justifyContent: 'center' },
  secondaryLabel: { color: '#F2EBDD', fontSize: 14, fontWeight: '800' },
});
