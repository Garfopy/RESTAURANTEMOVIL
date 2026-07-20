import '../global.css';
import React, { useEffect, useRef, useState } from 'react';
import { Stack, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import * as SplashScreen from 'expo-splash-screen';
import {
  useFonts,
  Inter_400Regular,
  Inter_500Medium,
  Inter_600SemiBold,
  Inter_700Bold,
} from '@expo-google-fonts/inter';
import {
  PlayfairDisplay_700Bold,
  PlayfairDisplay_700Bold_Italic,
} from '@expo-google-fonts/playfair-display';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StripeProvider } from '@stripe/stripe-react-native';
import { AppState, Platform, View } from 'react-native';
import * as Notifications from 'expo-notifications';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { useUserStore } from '../store/user.store';
import { hydrateCart } from '../store/cart.store';
import { hydrateTableSession } from '../store/table-session.store';
import { getMe } from '../services/auth.service';
import { ToastProvider } from '../context/ToastContext';
import { GlobalCartButton } from '../components/shared/GlobalCartButton';
import { GlobalSocialNotifications } from '../components/shared/GlobalSocialNotifications';
import { TableSessionRuntime } from '../components/shared/TableSessionRuntime';
import { useThemeStore } from '../store/theme.store';
import { hydrateBranchSelection, notifyBranchConfigUpdated, subscribeBranchConfigUpdated, useBranchConfigStore, useBranchStore } from '../store/branch.store';
import { STRIPE_PUBLISHABLE_KEY } from '../constants/stripe';
import {
  getNotificationDeepLink,
  isPushRegistrationEnabled,
  registerPushNotifications,
  subscribeForegroundFirebaseMessages,
  subscribePushTokenRefresh,
} from '../services/push-notifications.service';

SplashScreen.preventAutoHideAsync();

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 2,
      staleTime: 60 * 1000,
      throwOnError: false,
    },
  },
});

// Guard para detectar configuración incorrecta de Stripe
if (__DEV__ && !STRIPE_PUBLISHABLE_KEY) {
  console.error('ERROR: EXPO_PUBLIC_STRIPE_KEY no está configurada');
}

function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const segments = useSegments();
  const { isAuthenticated, isLoading, user } = useUserStore();
  const inAuth = segments[0] === '(auth)';
  const inPublicLegal = segments[0] === 'legal';
  const inCompleteProfile = inAuth && segments[1] === 'complete-profile';
  const inWaiter = segments[0] === '(waiter)';
  const inHostess = segments[0] === '(hostess)';
  const isWaiter = user?.rol === 'mesero';
  const isHostess = ['hostess', 'hostes', 'host', 'anfitrion', 'anfitriona'].includes(
    String(user?.rol ?? '').toLowerCase()
  );
  const needsOnboarding = Boolean(
    isAuthenticated &&
      !isWaiter &&
      !isHostess &&
      user?.google_id &&
      (user.requires_onboarding || !user.telefono || !user.fecha_nacimiento || !user.terms_accepted_at)
  );
  const redirectTo =
    !isLoading && !isAuthenticated && !inAuth && !inPublicLegal
      ? '/(auth)/login'
      : !isLoading && needsOnboarding && !inCompleteProfile
        ? '/(auth)/complete-profile'
        : !isLoading && isAuthenticated && isWaiter && !inWaiter
        ? '/(waiter)'
        : !isLoading && isAuthenticated && isHostess && !inHostess
          ? '/(hostess)'
          : !isLoading && isAuthenticated && !needsOnboarding && !isWaiter && !isHostess && (inAuth || inWaiter || inHostess)
            ? '/(tabs)'
            : null;

  useEffect(() => {
    if (redirectTo) {
      router.replace(redirectTo as never);
    }
  }, [redirectTo, router]);

  if (isLoading || redirectTo) return null;

  return <>{children}</>;
}

function BranchConfigRuntime() {
  const isAuthenticated = useUserStore((state) => state.isAuthenticated);
  const userBranchId = useUserStore((state) => state.user?.current_restaurante_id ?? null);
  const selectedBranchId = useBranchStore((state) => state.seleccionada?.id ?? null);
  const refresh = useBranchConfigStore((state) => state.refresh);
  const startPolling = useBranchConfigStore((state) => state.startPolling);
  const stopPolling = useBranchConfigStore((state) => state.stopPolling);
  const clearConfig = useBranchConfigStore((state) => state.clear);
  const appState = useRef(AppState.currentState);
  const branchId = selectedBranchId ?? userBranchId;

  useEffect(() => {
    const socketUrl = process.env.EXPO_PUBLIC_BRANCH_CONFIG_WS_URL?.trim();
    if (!isAuthenticated || !socketUrl) return;

    let disposed = false;
    type ConfigSocket = {
      onmessage: ((message: { data: unknown }) => void) | null;
      onclose: (() => void) | null;
      close: () => void;
    };
    let socket: ConfigSocket | null = null;
    let reconnectTimer: ReturnType<typeof setTimeout> | null = null;

    const connect = () => {
      if (disposed) return;
      const nextSocket = new (globalThis.WebSocket as any)(socketUrl) as ConfigSocket;
      socket = nextSocket;
      nextSocket.onmessage = (message) => {
        try {
          const event = JSON.parse(String(message.data)) as { event?: string; branch_id?: number; version?: number };
          if (event.event === 'branch.config.updated' && Number(event.branch_id) > 0 && Number(event.version) > 0) {
            notifyBranchConfigUpdated({ branch_id: Number(event.branch_id), version: Number(event.version) });
          }
        } catch {
          // Ignorar mensajes ajenos al contrato de configuración.
        }
      };
      nextSocket.onclose = () => {
        if (!disposed) reconnectTimer = setTimeout(connect, 5_000);
      };
    };

    connect();
    return () => {
      disposed = true;
      if (reconnectTimer) clearTimeout(reconnectTimer);
      socket?.close();
    };
  }, [isAuthenticated]);

  useEffect(() => subscribeBranchConfigUpdated((event) => {
    const state = useBranchConfigStore.getState();
    if (event.branch_id === state.branchId && event.version > state.version) {
      void state.refresh(event.branch_id, { force: true }).catch(() => undefined);
    }
  }), []);

  useEffect(() => {
    if (!isAuthenticated || !branchId) {
      clearConfig();
      return;
    }

    void refresh(branchId, { force: true }).catch(() => undefined);
    if (appState.current === 'active') startPolling(branchId);

    const subscription = AppState.addEventListener('change', (nextState) => {
      appState.current = nextState;
      if (nextState === 'active') {
        void refresh(branchId).catch(() => undefined);
        startPolling(branchId);
      } else {
        stopPolling();
      }
    });

    return () => {
      subscription.remove();
      stopPolling();
    };
  }, [branchId, clearConfig, isAuthenticated, refresh, startPolling, stopPolling]);

  return null;
}

function PushNotificationRuntime() {
  const router = useRouter();
  const isAuthenticated = useUserStore((state) => state.isAuthenticated);
  const userId = useUserStore((state) => state.user?.id ?? null);
  const registeredForUserRef = useRef<number | null>(null);
  const handledNotificationIdsRef = useRef(new Set<string>());

  const handleNotificationResponse = React.useCallback((response: Notifications.NotificationResponse | null | undefined) => {
    if (!response) return;

    const id = response.notification.request.identifier;
    if (id && handledNotificationIdsRef.current.has(id)) return;
    if (id) handledNotificationIdsRef.current.add(id);

    const deepLink = getNotificationDeepLink(response);
    if (deepLink) {
      router.push(deepLink as never);
    }
  }, [router]);

  useEffect(() => {
    if (!isPushRegistrationEnabled()) {
      return;
    }

    const unsubscribeForeground = subscribeForegroundFirebaseMessages();
    const unsubscribeTokenRefresh = subscribePushTokenRefresh();
    const subscription = Notifications.addNotificationResponseReceivedListener(handleNotificationResponse);
    void Notifications.getLastNotificationResponseAsync()
      .then(handleNotificationResponse)
      .catch((error) => {
        if (__DEV__) {
          console.warn('[Push] No se pudo leer la notificacion inicial:', error);
        }
      });

    return () => {
      unsubscribeForeground();
      unsubscribeTokenRefresh();
      subscription.remove();
    };
  }, [handleNotificationResponse]);

  useEffect(() => {
    if (!isPushRegistrationEnabled() || !isAuthenticated || !userId || registeredForUserRef.current === userId) {
      return;
    }

    registeredForUserRef.current = userId;
    void registerPushNotifications({ reason: 'app-start' }).catch((error) => {
      registeredForUserRef.current = null;
      if (__DEV__) {
        console.warn('[Push] No se pudo registrar el token:', error);
      }
    });
  }, [isAuthenticated, userId]);

  useEffect(() => {
    if (!isAuthenticated) {
      registeredForUserRef.current = null;
    }
  }, [isAuthenticated]);

  return null;
}

export default function RootLayout() {
  const { hydrateFromStorage, setUser, logout } = useUserStore();
  const hydrateTheme = useThemeStore((s) => s.hydrateTheme);
  const [appReady, setAppReady] = useState(false);

  const [fontsLoaded] = useFonts({
    Inter_400Regular,
    Inter_500Medium,
    Inter_600SemiBold,
    Inter_700Bold,
    PlayfairDisplay_700Bold,
    PlayfairDisplay_700Bold_Italic,
  });

  useEffect(() => {
    if (!fontsLoaded) return;

    let cancelled = false;

    async function init() {
      await hydrateTheme();
      await hydrateFromStorage();
      await hydrateBranchSelection();
      await hydrateCart();
      await hydrateTableSession();

      // Si se restauró un token, validarlo con el servidor
      const { isAuthenticated, token } = useUserStore.getState();
      if (isAuthenticated && token) {
        try {
          const user = await getMe();
          setUser(user);
        } catch {
          // Token inválido o expirado — cerrar sesión
          await logout();
        }
      }

      if (!cancelled) {
        setAppReady(true);
        await SplashScreen.hideAsync();
      }
    }
    init();

    return () => {
      cancelled = true;
    };
  }, [fontsLoaded, hydrateFromStorage, hydrateTheme, logout, setUser]);

  if (!fontsLoaded || !appReady) return null;

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <QueryClientProvider client={queryClient}>
          <StripeProvider publishableKey={STRIPE_PUBLISHABLE_KEY}>
            <ToastProvider>
              <BranchConfigRuntime />
              <PushNotificationRuntime />
              <TableSessionRuntime />
              <GlobalSocialNotifications />
              <AuthGuard>
                <StatusBar style="auto" />
                <View style={{ flex: 1 }}>
                  <Stack
                    screenOptions={{
                      headerShown: false,
                      gestureEnabled: true,
                      fullScreenGestureEnabled: true,
                      animation: Platform.OS === 'ios' ? 'default' : 'slide_from_right',
                    }}
                  >
                    <Stack.Screen name="(auth)" />
                    <Stack.Screen name="(tabs)" />
                    <Stack.Screen name="(waiter)" />
                    <Stack.Screen name="(hostess)" />
                    <Stack.Screen name="branch-selector" options={{ presentation: 'modal', gestureEnabled: true }} />
                    <Stack.Screen name="table-scanner" options={{ presentation: 'modal', gestureEnabled: true }} />
                    <Stack.Screen name="product/[id]" />
                    <Stack.Screen name="store/index" />
                    <Stack.Screen name="store/product/[id]" />
                    <Stack.Screen name="cart" />
                    <Stack.Screen name="checkout/order-type" />
                    <Stack.Screen name="checkout/payment" />
                    <Stack.Screen name="checkout/exit-pass" />
                    <Stack.Screen name="order/[id]" />
                    <Stack.Screen name="legal/terms" />
                    <Stack.Screen name="legal/privacy" />
                  </Stack>
                  <GlobalCartButton />
                </View>
              </AuthGuard>
            </ToastProvider>
          </StripeProvider>
        </QueryClientProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}
