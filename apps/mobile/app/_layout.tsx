import '../global.css';
import React, { useEffect, useState } from 'react';
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
import { View } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { useUserStore } from '../store/user.store';
import { hydrateCart } from '../store/cart.store';
import { hydrateTableSession } from '../store/table-session.store';
import { getMe } from '../services/auth.service';
import { ToastProvider } from '../context/ToastContext';
import { GlobalCartButton } from '../components/shared/GlobalCartButton';
import { useThemeStore } from '../store/theme.store';

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

const STRIPE_PUBLISHABLE_KEY = process.env.EXPO_PUBLIC_STRIPE_KEY ?? '';

// Guard para detectar configuración incorrecta de Stripe
if (!STRIPE_PUBLISHABLE_KEY) {
  console.error('ERROR: EXPO_PUBLIC_STRIPE_KEY no está configurada');
}

function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const segments = useSegments();
  const { isAuthenticated, isLoading, user } = useUserStore();

  useEffect(() => {
    if (isLoading) return;

    const inAuth = segments[0] === '(auth)';
    const inWaiter = segments[0] === '(waiter)';
    const isWaiter = user?.rol === 'mesero';

    if (!isAuthenticated && !inAuth) {
      router.replace('/(auth)/login');
    } else if (isAuthenticated && isWaiter && !inWaiter) {
      router.replace('/(waiter)' as never);
    } else if (isAuthenticated && !isWaiter && (inAuth || inWaiter)) {
      router.replace('/(tabs)');
    }
  }, [isAuthenticated, isLoading, segments, user?.rol]);

  return <>{children}</>;
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
      <QueryClientProvider client={queryClient}>
        <StripeProvider publishableKey={STRIPE_PUBLISHABLE_KEY}>
          <ToastProvider>
            <AuthGuard>
              <StatusBar style="auto" />
              <View style={{ flex: 1 }}>
                <Stack screenOptions={{ headerShown: false }}>
                  <Stack.Screen name="(auth)" />
                  <Stack.Screen name="(tabs)" />
                  <Stack.Screen name="(waiter)" />
                  <Stack.Screen name="branch-selector" options={{ presentation: 'modal' }} />
                  <Stack.Screen name="table-scanner" options={{ presentation: 'modal' }} />
                  <Stack.Screen name="product/[id]" options={{ presentation: 'modal' }} />
                  <Stack.Screen name="store/index" options={{ presentation: 'modal' }} />
                  <Stack.Screen name="store/product/[id]" options={{ presentation: 'modal' }} />
                  <Stack.Screen name="cart" options={{ presentation: 'modal' }} />
                  <Stack.Screen name="checkout/order-type" />
                  <Stack.Screen name="checkout/payment" />
                  <Stack.Screen name="checkout/exit-pass" />
                  <Stack.Screen name="order/[id]" />
                </Stack>
                <GlobalCartButton />
              </View>
            </AuthGuard>
          </ToastProvider>
        </StripeProvider>
      </QueryClientProvider>
    </GestureHandlerRootView>
  );
}
