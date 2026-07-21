import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Platform, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { loginWithApple } from '../../services/auth.service';
import { extractAccountSuspension } from '../../services/account-suspension.service';
import { getApiError } from '../../services/api';
import { useUserStore } from '../../store/user.store';

declare const require: (name: string) => any;
type AppleAuthenticationModule = typeof import('expo-apple-authentication');

export function AppleSignInButton() {
  const login = useUserStore((state) => state.login);
  const setAccountSuspension = useUserStore((state) => state.setAccountSuspension);
  const [appleAuthentication, setAppleAuthentication] = useState<AppleAuthenticationModule | null>(null);
  const [available, setAvailable] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (Platform.OS !== 'ios') return;
    let active = true;
    try {
      const module = require('expo-apple-authentication') as AppleAuthenticationModule;
      setAppleAuthentication(module);
      void module.isAvailableAsync()
        .then((isAvailable) => {
          if (active) setAvailable(isAvailable);
        })
        .catch(() => {
          if (active) setAvailable(false);
        });
    } catch {
      setAvailable(false);
    }
    return () => {
      active = false;
    };
  }, []);

  const AppleModule = appleAuthentication;
  if (!available || !AppleModule) return null;

  async function handleAppleSignIn(module: AppleAuthenticationModule) {
    if (busy) return;

    try {
      setBusy(true);
      const credential = await module.signInAsync({
        requestedScopes: [
          module.AppleAuthenticationScope.FULL_NAME,
          module.AppleAuthenticationScope.EMAIL,
        ],
      });

      if (!credential.identityToken) {
        throw new Error('Apple no devolvio un token de identidad.');
      }

      const fullName = [credential.fullName?.givenName, credential.fullName?.familyName]
        .filter(Boolean)
        .join(' ')
        .trim();
      const session = await loginWithApple({
        identity_token: credential.identityToken,
        authorization_code: credential.authorizationCode,
        full_name: fullName || null,
        platform: 'ios',
      });
      await login(session);
    } catch (error: unknown) {
      if ((error as { code?: string })?.code === 'ERR_REQUEST_CANCELED') return;

      const suspension = extractAccountSuspension(error);
      if (suspension) {
        setAccountSuspension(suspension);
        return;
      }

      Alert.alert('No pudimos entrar con Apple', getApiError(error) || 'Intenta nuevamente.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.wrapper}>
      <TouchableOpacity
        style={[styles.button, busy && styles.disabled]}
        activeOpacity={0.9}
        disabled={busy}
        onPress={() => void handleAppleSignIn(AppleModule)}
        accessibilityRole="button"
        accessibilityLabel="Continuar con Apple"
      >
        <View style={styles.icon}>
          <Ionicons name="logo-apple" size={21} color="#FFFFFF" />
        </View>
        <Text style={styles.label}>Continuar con Apple</Text>
        {busy ? (
          <ActivityIndicator size="small" color="#FFFFFF" />
        ) : (
          <Ionicons name="arrow-forward" size={19} color="#FFFFFF" />
        )}
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: { width: '100%', marginBottom: 9 },
  button: {
    width: '100%',
    minHeight: 58,
    borderRadius: 18,
    backgroundColor: '#000000',
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    gap: 12,
  },
  icon: {
    width: 34,
    height: 34,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.08)',
  },
  label: {
    flex: 1,
    color: '#FFFFFF',
    fontFamily: 'Inter_700Bold',
    fontSize: 15,
    letterSpacing: 0,
  },
  disabled: { opacity: 0.6 },
});
