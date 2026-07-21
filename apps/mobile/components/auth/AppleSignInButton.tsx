import React, { useEffect, useState } from 'react';
import { Alert, Platform, StyleSheet, View } from 'react-native';
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
  const AppleButton = AppleModule?.AppleAuthenticationButton;
  if (!available || !AppleModule || !AppleButton) return null;

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
    <View style={[styles.wrapper, busy && styles.disabled]} pointerEvents={busy ? 'none' : 'auto'}>
      <AppleButton
        buttonType={AppleModule.AppleAuthenticationButtonType.SIGN_IN}
        buttonStyle={AppleModule.AppleAuthenticationButtonStyle.BLACK}
        cornerRadius={18}
        style={styles.button}
        onPress={() => void handleAppleSignIn(AppleModule)}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: { width: '100%', height: 58, marginBottom: 9 },
  button: { width: '100%', height: 58 },
  disabled: { opacity: 0.6 },
});
