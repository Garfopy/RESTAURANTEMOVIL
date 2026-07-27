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
import { AppState, Platform, View } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StripeProvider, useStripe } from '@stripe/stripe-react-native';
import * as Linking from 'expo-linking';
import { useUserStore } from '../store/user.store';
import { hydrateCart } from '../store/cart.store';
import { hydrateTableSession } from '../store/table-session.store';
import { getMe } from '../services/auth.service';
import { extractAccountSuspension } from '../services/account-suspension.service';
import { apiClient } from '../services/api';
import { getOrders } from '../services/orders.service';
import { ToastProvider } from '../context/ToastContext';
import { GlobalCartButton } from '../components/shared/GlobalCartButton';
import { GlobalSocialNotifications } from '../components/shared/GlobalSocialNotifications';
import { TableSessionRuntime } from '../components/shared/TableSessionRuntime';
import { useThemeStore } from '../store/theme.store';
import { hydrateBranchSelection, notifyBranchConfigUpdated, subscribeBranchConfigUpdated, useBranchConfigStore, useBranchStore } from '../store/branch.store';
import {
  getInitialNotificationDeepLink,
  isPushRegistrationEnabled,
  registerPushNotifications,
  subscribeForegroundFirebaseMessages,
  subscribeNotificationResponses,
  subscribePushTokenRefresh,
} from '../services/push-notifications.service';
import { ensureLocationPermission, ensureNotificationPermission } from '../services/app-permissions.service';
import { STRIPE_IS_CONFIGURED, STRIPE_PUBLISHABLE_KEY } from '../constants/stripe';
import { hasPendingAuthReturnTo } from '../services/auth-gate.service';

void SplashScreen.preventAutoHideAsync().catch((error) => {
  console.warn('[Startup] No se pudo mantener visible el splash:', error);
});

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 2,
      staleTime: 60 * 1000,
      throwOnError: false,
    },
  },
});

const ACCOUNT_STATUS_INTERVAL_MS = 5 * 60 * 1000;
const ACCOUNT_STATUS_RESUME_STALE_MS = 60 * 1000;
const STARTUP_SESSION_TIMEOUT_MS = 8_000;
const STARTUP_FONT_TIMEOUT_MS = 4_000;
const STARTUP_WATCHDOG_MS = 12_000;
const PUSH_SESSION_STABILIZATION_MS = 5_000;
const INITIAL_PERMISSION_DELAY_MS = 600;

function withTimeout<T>(promise: Promise<T>, timeoutMs: number, message: string): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error(message)), timeoutMs);
    promise.then(
      (value) => {
        clearTimeout(timer);
        resolve(value);
      },
      (error) => {
        clearTimeout(timer);
        reject(error);
      }
    );
  });
}

function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const segments = useSegments();
  const { isAuthenticated, isLoading, user, accountSuspension } = useUserStore();
  const firstSegment = String(segments[0] ?? '');
  const secondSegment = String((segments as string[])[1] ?? '');
  const inAuth = firstSegment === '(auth)';
  const inPublicLegal = firstSegment === 'legal';
  const inAccountSuspended = firstSegment === 'account-suspended';
  const inCompleteProfile = inAuth && (segments as string[])[1] === 'complete-profile';
  const inWaiter = firstSegment === '(waiter)';
  const inHostess = firstSegment === '(hostess)';
  const inPublicCatalog =
    firstSegment === '(tabs)' ||
    firstSegment === 'branch-selector' ||
    firstSegment === 'cart' ||
    firstSegment === 'category' ||
    firstSegment === 'product' ||
    (firstSegment === 'store' && (secondSegment === '' || secondSegment === 'index' || secondSegment === 'product'));
  const isWaiter = user?.rol === 'mesero';
  const isHostess = ['hostess', 'hostes', 'host', 'anfitrion', 'anfitriona'].includes(
    String(user?.rol ?? '').toLowerCase()
  );
  const userName = String(user?.nombre ?? '').trim();
  const needsName = userName.length < 3 || userName.toLowerCase() === 'usuario amare';
  const needsOnboarding = Boolean(
    isAuthenticated &&
      !isWaiter &&
      !isHostess &&
      (user?.google_id || user?.apple_id) &&
      (user.requires_onboarding || needsName || !user.telefono || !user.fecha_nacimiento || !user.terms_accepted_at)
  );
  const redirectTo =
    !isLoading && accountSuspension && !inAccountSuspended
      ? '/account-suspended'
      : !isLoading && !isAuthenticated && !inAuth && !inPublicLegal && !inAccountSuspended && !inPublicCatalog
      ? '/(auth)/login'
      : !isLoading && needsOnboarding && !inCompleteProfile && !inPublicLegal
        ? '/(auth)/complete-profile'
        : !isLoading && isAuthenticated && isWaiter && !inWaiter
        ? '/(waiter)'
        : !isLoading && isAuthenticated && isHostess && !inHostess
          ? '/(hostess)'
          : !isLoading && isAuthenticated && !needsOnboarding && !isWaiter && !isHostess && (inAuth || inWaiter || inHostess)
            && !(inAuth && hasPendingAuthReturnTo())
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
  const [sessionReady, setSessionReady] = useState(false);

  const handleNotificationDeepLink = React.useCallback((deepLink: string | null) => {
    if (deepLink) {
      router.push(deepLink as never);
    }
  }, [router]);

  useEffect(() => {
    setSessionReady(false);
    if (!isPushRegistrationEnabled() || !isAuthenticated || !userId) {
      return;
    }

    const timer = setTimeout(() => setSessionReady(true), PUSH_SESSION_STABILIZATION_MS);
    return () => clearTimeout(timer);
  }, [isAuthenticated, userId]);

  useEffect(() => {
    if (!isPushRegistrationEnabled() || !sessionReady) {
      return;
    }

    const unsubscribeForeground = subscribeForegroundFirebaseMessages();
    const unsubscribeTokenRefresh = subscribePushTokenRefresh();
    const unsubscribeResponses = subscribeNotificationResponses(handleNotificationDeepLink);
    void getInitialNotificationDeepLink()
      .then(handleNotificationDeepLink)
      .catch((error) => {
        if (__DEV__) {
          console.warn('[Push] No se pudo leer la notificacion inicial:', error);
        }
      });

    return () => {
      unsubscribeForeground();
      unsubscribeTokenRefresh();
      unsubscribeResponses();
    };
  }, [handleNotificationDeepLink, sessionReady]);

  useEffect(() => {
    if (!isPushRegistrationEnabled() || !sessionReady || !userId || registeredForUserRef.current === userId) {
      return;
    }

    let retryTimer: ReturnType<typeof setTimeout> | null = null;

    const syncToken = (reason: string, retryOnce = false) => {
      registeredForUserRef.current = userId;
      void registerPushNotifications({
        reason,
        userId,
        requestPermissions: false,
      })
        .then((token) => {
          if (!token) {
            registeredForUserRef.current = null;
            if (retryOnce && !retryTimer) {
              retryTimer = setTimeout(() => syncToken('bounded-retry'), 10_000);
            }
          }
        })
        .catch((error) => {
          registeredForUserRef.current = null;
          if (__DEV__) {
            console.warn('[Push] No se pudo registrar el token:', error);
          }
        });
    };

    syncToken('authenticated-user-changed', true);

    const subscription = AppState.addEventListener('change', (nextState) => {
      if (nextState === 'active') {
        // El servicio deduplica la firma y solo escribe si usuario, dispositivo o token cambiaron.
        syncToken('app-resume');
      }
    });

    return () => {
      subscription.remove();
      if (retryTimer) clearTimeout(retryTimer);
    };
  }, [sessionReady, userId]);

  useEffect(() => {
    if (!isAuthenticated) {
      registeredForUserRef.current = null;
    }
  }, [isAuthenticated]);

  return null;
}

function AccountStatusRuntime() {
  const isAuthenticated = useUserStore((state) => state.isAuthenticated);
  const setUser = useUserStore((state) => state.setUser);
  const lastCheckedAtRef = useRef(Date.now());
  const checkInFlightRef = useRef(false);

  useEffect(() => {
    if (!isAuthenticated) return;

    let disposed = false;

    const checkAccount = async (force = false) => {
      const now = Date.now();
      if (
        disposed ||
        checkInFlightRef.current ||
        AppState.currentState !== 'active' ||
        (!force && now - lastCheckedAtRef.current < ACCOUNT_STATUS_RESUME_STALE_MS)
      ) {
        return;
      }

      checkInFlightRef.current = true;
      lastCheckedAtRef.current = now;
      try {
        const user = await getMe();
        if (!disposed) setUser(user);
      } catch {
        // El interceptor global convierte una cuenta suspendida en logout + pantalla dedicada.
      } finally {
        checkInFlightRef.current = false;
      }
    };

    const interval = setInterval(() => void checkAccount(true), ACCOUNT_STATUS_INTERVAL_MS);
    const subscription = AppState.addEventListener('change', (nextState) => {
      if (nextState === 'active') {
        void checkAccount();
      }
    });

    return () => {
      disposed = true;
      clearInterval(interval);
      subscription.remove();
    };
  }, [isAuthenticated, setUser]);

  return null;
}

function AuthenticatedDataWarmupRuntime() {
  const userId = useUserStore((state) => state.user?.id ?? null);
  const previousUserIdRef = useRef<number | null | undefined>(undefined);

  useEffect(() => {
    if (previousUserIdRef.current !== userId) {
      for (const queryKey of [['promotions'], ['favorites'], ['orders'], ['addresses'], ['social'], ['rewards']]) {
        queryClient.removeQueries({ queryKey });
      }
      previousUserIdRef.current = userId;
    }

    if (!userId) return;

    const timer = setTimeout(() => {
      void Promise.allSettled([
        queryClient.prefetchQuery({
          queryKey: ['promotions'],
          queryFn: async () => {
            const response = await apiClient.get('/promotions');
            return response.data.data ?? [];
          },
          staleTime: 5 * 60 * 1000,
        }),
        queryClient.prefetchQuery({
          queryKey: ['favorites'],
          queryFn: async () => {
            const response = await apiClient.get('/favorites');
            return response.data.data ?? [];
          },
          staleTime: 2 * 60 * 1000,
        }),
        queryClient.prefetchQuery({
          queryKey: ['orders'],
          queryFn: getOrders,
          staleTime: 15 * 1000,
        }),
      ]);
    }, 1200);

    return () => clearTimeout(timer);
  }, [userId]);

  return null;
}

function InitialPermissionsRuntime() {
  const isAuthenticated = useUserStore((state) => state.isAuthenticated);
  const userId = useUserStore((state) => state.user?.id ?? null);
  const requestedForUserRef = useRef<number | null>(null);

  useEffect(() => {
    if (!isAuthenticated || !userId) {
      requestedForUserRef.current = null;
      return;
    }
    if (requestedForUserRef.current === userId) return;

    requestedForUserRef.current = userId;
    let cancelled = false;
    const timer = setTimeout(() => {
      void (async () => {
        await ensureLocationPermission();
        if (cancelled) return;

        const notificationPermission = await ensureNotificationPermission();
        if (cancelled || !notificationPermission.granted) return;

        await registerPushNotifications({
          reason: 'initial-authenticated-access',
          userId,
          requestPermissions: false,
        });
      })().catch((error) => {
        if (__DEV__) {
          console.warn('[Permissions] No se pudieron solicitar los permisos iniciales:', error);
        }
      });
    }, INITIAL_PERMISSION_DELAY_MS);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [isAuthenticated, userId]);

  return null;
}

function StripeUrlRuntime() {
  const { handleURLCallback } = useStripe();

  useEffect(() => {
    const handleUrl = ({ url }: { url: string }) => {
      void handleURLCallback(url).catch((error) => {
        console.warn('[Stripe] No se pudo procesar el retorno del pago:', error);
      });
    };
    const subscription = Linking.addEventListener('url', handleUrl);
    void Linking.getInitialURL().then((url) => {
      if (url) handleUrl({ url });
    });
    return () => subscription.remove();
  }, [handleURLCallback]);

  return null;
}

export default function RootLayout() {
  const { hydrateFromStorage, setUser, logout } = useUserStore();
  const hydrateTheme = useThemeStore((s) => s.hydrateTheme);
  const [appReady, setAppReady] = useState(false);
  const [fontWaitExpired, setFontWaitExpired] = useState(false);
  const [splashHidden, setSplashHidden] = useState(false);
  const [optionalRuntimesReady, setOptionalRuntimesReady] = useState(false);

  const [fontsLoaded, fontError] = useFonts({
    Inter_400Regular,
    Inter_500Medium,
    Inter_600SemiBold,
    Inter_700Bold,
    PlayfairDisplay_700Bold,
    PlayfairDisplay_700Bold_Italic,
  });
  const fontsReady = fontsLoaded || Boolean(fontError) || fontWaitExpired;

  useEffect(() => {
    const timer = setTimeout(() => setFontWaitExpired(true), STARTUP_FONT_TIMEOUT_MS);
    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => {
      useUserStore.setState({ isLoading: false });
      setAppReady(true);
    }, STARTUP_WATCHDOG_MS);
    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    let cancelled = false;

    async function init() {
      const startupResults = await Promise.allSettled([
        hydrateTheme(),
        hydrateFromStorage(),
        hydrateBranchSelection(),
        hydrateCart(),
        hydrateTableSession(),
      ]);

      startupResults.forEach((result, index) => {
        if (result.status === 'rejected') {
          console.error(`[Startup] Fallo la tarea de inicializacion ${index + 1}:`, result.reason);
        }
      });

      // Si se restauró un token, validarlo con el servidor
      const { isAuthenticated, token } = useUserStore.getState();
      if (isAuthenticated && token) {
        try {
          const user = await withTimeout(
            getMe(),
            STARTUP_SESSION_TIMEOUT_MS,
            'La validacion de sesion excedio el tiempo de arranque.'
          );
          setUser(user);
        } catch (error: unknown) {
          // Token inválido o expirado — cerrar sesión
          try {
            await logout({ accountSuspension: extractAccountSuspension(error) });
          } catch {
            useUserStore.setState({
              user: null,
              token: null,
              accountSuspension: extractAccountSuspension(error),
              isAuthenticated: false,
              isLoading: false,
            });
          }
        }
      }

      if (!cancelled) {
        setAppReady(true);
      }
    }
    void init()
      .catch((error) => {
        console.error('[Startup] La inicializacion de la app fallo:', error);
        useUserStore.setState({ isLoading: false });
      })
      .finally(() => {
        if (!cancelled) setAppReady(true);
      });

    return () => {
      cancelled = true;
    };
  }, [hydrateFromStorage, hydrateTheme, logout, setUser]);

  useEffect(() => {
    if (!appReady || !fontsReady) return;
    let active = true;
    const fallback = setTimeout(() => {
      if (active) setSplashHidden(true);
    }, 1_000);

    void SplashScreen.hideAsync()
      .catch(() => undefined)
      .finally(() => {
        if (active) setSplashHidden(true);
      });

    return () => {
      active = false;
      clearTimeout(fallback);
    };
  }, [appReady, fontsReady]);

  useEffect(() => {
    if (!splashHidden) return;
    const timer = setTimeout(() => setOptionalRuntimesReady(true), 1_000);
    return () => clearTimeout(timer);
  }, [splashHidden]);

  if (!fontsReady || !appReady || !splashHidden) return null;

  const content = (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <QueryClientProvider client={queryClient}>
          <ToastProvider>
              <BranchConfigRuntime />
              {optionalRuntimesReady ? <InitialPermissionsRuntime /> : null}
              {optionalRuntimesReady ? <PushNotificationRuntime /> : null}
              <AccountStatusRuntime />
              <AuthenticatedDataWarmupRuntime />
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
                    <Stack.Screen name="account-suspended" />
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
        </QueryClientProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );

  if (!STRIPE_IS_CONFIGURED) return content;

  return (
    <StripeProvider
      publishableKey={STRIPE_PUBLISHABLE_KEY}
      merchantIdentifier="merchant.com.amare.app"
      urlScheme="amare"
    >
      <StripeUrlRuntime />
      {content}
    </StripeProvider>
  );
}
