import AsyncStorage from '@react-native-async-storage/async-storage';
import { Alert } from 'react-native';
import { useUserStore } from '../store/user.store';

const AUTH_RETURN_TO_KEY = 'amare_auth_return_to';
let pendingAuthReturnTo: string | null = null;

type RouterLike = {
  push: (href: any) => void;
  replace?: (href: any) => void;
};

type RequireAuthOptions = {
  title?: string;
  message: string;
  returnTo?: string;
};

export function isSignedIn(): boolean {
  const { isAuthenticated, token } = useUserStore.getState();
  return Boolean(isAuthenticated && token);
}

export function requireAuth(router: RouterLike, options: RequireAuthOptions): boolean {
  if (isSignedIn()) return true;

  const returnTo = options.returnTo ?? '/(tabs)';
  Alert.alert(options.title ?? 'Inicia sesion', options.message, [
    { text: 'Ahora no', style: 'cancel' },
    {
      text: 'Iniciar sesion',
      onPress: () => {
        void saveAuthReturnTo(returnTo);
        router.push({ pathname: '/(auth)/login', params: { returnTo } });
      },
    },
  ]);
  return false;
}

export async function saveAuthReturnTo(path: string): Promise<void> {
  pendingAuthReturnTo = path;
  await AsyncStorage.setItem(AUTH_RETURN_TO_KEY, path);
}

export function hasPendingAuthReturnTo(): boolean {
  return Boolean(pendingAuthReturnTo);
}

export async function consumeAuthReturnTo(): Promise<string | null> {
  const path = await AsyncStorage.getItem(AUTH_RETURN_TO_KEY);
  if (path) {
    await AsyncStorage.removeItem(AUTH_RETURN_TO_KEY);
  }
  pendingAuthReturnTo = null;
  return path;
}

export async function finishAuthFlow(router: RouterLike): Promise<void> {
  const returnTo = await consumeAuthReturnTo();
  router.replace?.((returnTo || '/(tabs)') as never);
}
