import React, { useEffect, useState } from 'react';
import { Alert, Platform, StyleSheet, View } from 'react-native';
import * as AppleAuthentication from 'expo-apple-authentication';
import { useRouter } from 'expo-router';
import { loginWithApple } from '../../services/auth.service';
import { extractAccountSuspension } from '../../services/account-suspension.service';
import { getApiError } from '../../services/api';
import { registerPushNotifications } from '../../services/push-notifications.service';
import { useUserStore } from '../../store/user.store';

export function AppleSignInButton() {
  const router = useRouter();
  const login = useUserStore((state) => state.login);
  const setAccountSuspension = useUserStore((state) => state.setAccountSuspension);
  const [available, setAvailable] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (Platform.OS !== 'ios') return;
    let active = true;
    void AppleAuthentication.isAvailableAsync().then((isAvailable) => {
      if (active) setAvailable(isAvailable);
    });
    return () => {
      active = false;
    };
  }, []);

  if (!available) return null;

  async function handleAppleSignIn() {
    if (busy) return;

    try {
      setBusy(true);
      const credential = await AppleAuthentication.signInAsync({
        requestedScopes: [
          AppleAuthentication.AppleAuthenticationScope.FULL_NAME,
          AppleAuthentication.AppleAuthenticationScope.EMAIL,
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
      void registerPushNotifications({ force: true, reason: 'apple-login-success', userId: session.user.id });
      router.replace('/' as never);
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
      <AppleAuthentication.AppleAuthenticationButton
        buttonType={AppleAuthentication.AppleAuthenticationButtonType.SIGN_IN}
        buttonStyle={AppleAuthentication.AppleAuthenticationButtonStyle.BLACK}
        cornerRadius={18}
        style={styles.button}
        onPress={() => void handleAppleSignIn()}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: { width: '100%', height: 58, marginBottom: 9 },
  button: { width: '100%', height: 58 },
  disabled: { opacity: 0.6 },
});
