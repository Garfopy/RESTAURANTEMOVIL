import { Platform } from 'react-native';
import axios from 'axios';
import Constants from 'expo-constants';
import * as SecureStore from 'expo-secure-store';
import type { DevicePushToken, NotificationPermissionsStatus, NotificationResponse } from 'expo-notifications';
import type { FirebaseMessagingTypes } from '@react-native-firebase/messaging';
import { apiClient } from './api';
import { normalizeAppDeepLink } from './deep-links.service';
import { useUserStore } from '../store/user.store';

declare const require: (name: string) => any;

type ExpoNotificationsModule = typeof import('expo-notifications');
type FirebaseMessagingModule = typeof import('@react-native-firebase/messaging');
type FirebaseMessaging = FirebaseMessagingModule['default'];

let expoNotifications: ExpoNotificationsModule | null | undefined;
let firebaseMessaging: FirebaseMessaging | null | undefined;
let lastPushSyncSignature: string | null = null;

const DEVICE_ID_KEY = 'amare_push_device_id';
const PUSH_TOKEN_ENDPOINTS = [
  '/api/v1/push-tokens',
  '/api/v1/mobile-push-tokens',
  '/api/v1/notification-tokens',
  '/profile/push-token',
] as const;

export function isPushRegistrationEnabled(): boolean {
  return process.env.EXPO_PUBLIC_ENABLE_PUSH_REGISTRATION === 'true';
}

function logPushDiagnostic(message: string, extra?: Record<string, unknown>) {
  const suffix = extra ? ` ${JSON.stringify(extra)}` : '';
  console.info(`[Push] ${message}${suffix}`);
}

function getExpoNotifications(): ExpoNotificationsModule | null {
  if (expoNotifications !== undefined) {
    return expoNotifications;
  }

  try {
    expoNotifications = require('expo-notifications') as ExpoNotificationsModule;
    expoNotifications.setNotificationHandler({
      handleNotification: async () => ({
        shouldShowAlert: true,
        shouldShowBanner: true,
        shouldShowList: true,
        shouldPlaySound: true,
        shouldSetBadge: true,
      }),
    });
  } catch (error) {
    expoNotifications = null;
    if (__DEV__) {
      console.warn('[Push] Expo Notifications no esta disponible en este build:', error);
    }
  }

  return expoNotifications;
}

function getFirebaseMessaging(): FirebaseMessaging | null {
  if (firebaseMessaging !== undefined) {
    return firebaseMessaging;
  }

  try {
    const firebaseModule = require('@react-native-firebase/messaging') as FirebaseMessagingModule;
    firebaseMessaging = firebaseModule.default;
  } catch (error) {
    firebaseMessaging = null;
    if (__DEV__) {
      console.warn('[Push] Firebase nativo no está disponible en este build:', error);
    }
  }

  return firebaseMessaging;
}

if (isPushRegistrationEnabled()) {
  try {
    getFirebaseMessaging()?.().setBackgroundMessageHandler(async () => undefined);
  } catch (error) {
    firebaseMessaging = null;
    if (__DEV__) {
      console.warn('[Push] No se pudo configurar background handler:', error);
    }
  }
}

export async function registerPushNotifications(options?: {
  force?: boolean;
  requestPermissions?: boolean;
  reason?: string;
  userId?: number | null;
}): Promise<string | null> {
  if (Platform.OS === 'web' || !isPushRegistrationEnabled()) {
    return null;
  }

  try {
    const Notifications = getExpoNotifications();
    const messaging = getFirebaseMessaging();
    if (!Notifications) {
      logPushDiagnostic('Expo Notifications no disponible para registrar token', { platform: Platform.OS });
      return null;
    }

    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('promotions', {
        name: 'Promociones',
        importance: Notifications.AndroidImportance.HIGH,
        vibrationPattern: [0, 250, 250, 250],
        lightColor: '#E8A020',
      });
    }

    const permissions = await getNotificationPermissions(Notifications, Boolean(options?.requestPermissions));
    if (!permissions.granted) {
      logPushDiagnostic('Permiso no concedido; se omite sincronizacion push', {
        platform: Platform.OS,
        reason: options?.reason ?? 'unspecified',
      });
      return null;
    }

    const userId = Number(options?.userId ?? useUserStore.getState().user?.id ?? 0);
    if (!userId) {
      logPushDiagnostic('No hay usuario autenticado; se omite sincronizacion push', {
        platform: Platform.OS,
        reason: options?.reason ?? 'unspecified',
      });
      return null;
    }

    const token = await getBestPushToken(Notifications, messaging);
    if (!token) {
      logPushDiagnostic('No se obtuvo token push nativo', {
        platform: Platform.OS,
        reason: options?.reason ?? 'unspecified',
      });
      return null;
    }

    const deviceId = await getOrCreateDeviceId();
    const signature = `${userId}:${Platform.OS}:${deviceId}:${token}`;

    if (!options?.force && lastPushSyncSignature === signature) {
      return token;
    }

    await syncPushTokenWithBackend({
      usuario_id: userId,
      fcm_token: token,
      platform: Platform.OS === 'ios' ? 'ios' : 'android',
      device_id: deviceId,
    });

    lastPushSyncSignature = signature;
    logPushDiagnostic('Token push sincronizado con backend', {
      platform: Platform.OS,
      reason: options?.reason ?? 'unspecified',
      tokenPreview: `${token.slice(0, 12)}...`,
    });

    return token;
  } catch (error) {
    console.warn('[Push] Registro desactivado o fallido:', error);
    return null;
  }
}

export function subscribePushTokenRefresh() {
  if (Platform.OS === 'web' || !isPushRegistrationEnabled()) {
    return () => undefined;
  }

  const Notifications = getExpoNotifications();
  const messaging = getFirebaseMessaging();
  const unsubscribers: Array<() => void> = [];

  if (Notifications?.addPushTokenListener) {
    const subscription = Notifications.addPushTokenListener(() => {
      void registerPushNotifications({ force: true, reason: 'expo-token-refresh' });
    });
    unsubscribers.push(() => subscription.remove());
  }

  if (messaging) {
    try {
      const unsubscribeMessaging = messaging().onTokenRefresh(() => {
        void registerPushNotifications({ force: true, reason: 'firebase-token-refresh' });
      });
      unsubscribers.push(unsubscribeMessaging);
    } catch (error) {
      if (__DEV__) {
        console.warn('[Push] No se pudo suscribir al refresh del token FCM:', error);
      }
    }
  }

  return () => {
    unsubscribers.forEach((unsubscribe) => {
      try {
        unsubscribe();
      } catch {
        // Nada que limpiar.
      }
    });
  };
}

export function subscribeForegroundFirebaseMessages() {
  if (!isPushRegistrationEnabled()) {
    return () => undefined;
  }

  const Notifications = getExpoNotifications();
  const messaging = getFirebaseMessaging();
  if (!Notifications || !messaging) {
    return () => undefined;
  }

  try {
    return messaging().onMessage(async (remoteMessage: FirebaseMessagingTypes.RemoteMessage) => {
      const title = remoteMessage.notification?.title;
      const body = remoteMessage.notification?.body;

      if (!title && !body) {
        return;
      }

      await Notifications.scheduleNotificationAsync({
        content: {
          title: title ?? 'Amare',
          body: body ?? '',
          data: remoteMessage.data ?? {},
        },
        trigger: null,
      });
    });
  } catch (error) {
    if (__DEV__) {
      console.warn('[Push] No se pudo suscribir a mensajes foreground:', error);
    }
    return () => undefined;
  }
}

export function subscribeNotificationResponses(onDeepLink: (deepLink: string) => void) {
  if (!isPushRegistrationEnabled()) {
    return () => undefined;
  }

  const Notifications = getExpoNotifications();
  if (!Notifications) {
    return () => undefined;
  }

  const subscription = Notifications.addNotificationResponseReceivedListener((response) => {
    const deepLink = getNotificationDeepLink(response);
    if (deepLink) {
      onDeepLink(deepLink);
    }
  });

  return () => subscription.remove();
}

export async function unregisterPushNotifications(fcmToken: string): Promise<void> {
  if (!fcmToken) return;

  let lastMissingRouteError: unknown = null;

  for (const endpoint of PUSH_TOKEN_ENDPOINTS) {
    try {
      await apiClient.delete(endpoint, {
        data: { fcm_token: fcmToken },
        _suppressConsoleError: true,
      } as any);
      return;
    } catch (error) {
      if (isMissingPushRoute(error)) {
        lastMissingRouteError = error;
        continue;
      }

      throw error;
    }
  }

  if (lastMissingRouteError) {
    throw lastMissingRouteError;
  }
}

export function getNotificationDeepLink(response: NotificationResponse): string | null {
  const data = response.notification.request.content.data ?? {};
  return getNotificationDataDeepLink(data);
}

export function getNotificationDataDeepLink(data: Record<string, unknown>): string | null {
  const deepLink = stringValue(data.deep_link ?? data.deepLink);
  const type = stringValue(data.type);
  const code = stringValue(data.code ?? data.codigo);
  const promotionId = stringValue(data.promotion_id ?? data.promo_id ?? data.promocion_id);
  const socialDeepLink = getSocialNotificationDeepLink(data, type);

  if (type?.toLowerCase().includes('promotion') || type?.toLowerCase().includes('promo')) {
    if (code) {
      return `/promotions?code=${encodeURIComponent(code)}`;
    }
    if (promotionId) {
      return `/promotions?promotionId=${encodeURIComponent(promotionId)}`;
    }
  }

  if (deepLink) {
    const normalized = normalizeAppDeepLink(deepLink);
    return withPromotionParams(normalized, { code, promotionId });
  }

  if (socialDeepLink) {
    return socialDeepLink;
  }

  if (code) {
    return `/promotions?code=${encodeURIComponent(code)}`;
  }

  if (promotionId) {
    return `/promotions?promotionId=${encodeURIComponent(promotionId)}`;
  }

  return null;
}

async function getNotificationPermissions(
  Notifications: ExpoNotificationsModule,
  shouldRequestPermissions: boolean
): Promise<{ granted: boolean; status: NotificationPermissionsStatus }> {
  let permissionStatus = await Notifications.getPermissionsAsync();
  logPushDiagnostic('Permiso actual', {
    platform: Platform.OS,
    status: permissionStatus.status,
    canAskAgain: permissionStatus.canAskAgain,
    granted: permissionStatus.granted,
  });

  if (!hasGrantedNotificationPermission(permissionStatus) && shouldRequestPermissions) {
    permissionStatus = await Notifications.requestPermissionsAsync();
    logPushDiagnostic('Permiso solicitado', {
      platform: Platform.OS,
      status: permissionStatus.status,
      canAskAgain: permissionStatus.canAskAgain,
      granted: permissionStatus.granted,
    });
  }

  return {
    granted: hasGrantedNotificationPermission(permissionStatus),
    status: permissionStatus,
  };
}

export function hasGrantedNotificationPermission(status: NotificationPermissionsStatus): boolean {
  if (status.granted || status.status === 'granted') {
    return true;
  }

  const iosStatus = status.ios?.status;
  const Notifications = getExpoNotifications();
  if (!Notifications || Platform.OS !== 'ios') {
    return false;
  }

  return (
    iosStatus === Notifications.IosAuthorizationStatus.PROVISIONAL ||
    iosStatus === Notifications.IosAuthorizationStatus.EPHEMERAL ||
    iosStatus === Notifications.IosAuthorizationStatus.AUTHORIZED
  );
}

async function getBestPushToken(
  Notifications: ExpoNotificationsModule,
  messaging: FirebaseMessaging | null
): Promise<string | null> {
  const nativeToken = await getNativeDevicePushToken(Notifications);

  if (Platform.OS === 'ios' && messaging) {
    try {
      await messaging().registerDeviceForRemoteMessages();
      const fcmToken = await messaging().getToken();
      if (fcmToken) {
        return fcmToken;
      }
    } catch (error) {
      if (__DEV__) {
        console.warn('[Push] No se pudo obtener token FCM de Firebase en iOS, se usa token nativo:', error);
      }
    }
  }

  if (nativeToken) {
    return nativeToken;
  }

  if (messaging) {
    try {
      const fcmToken = await messaging().getToken();
      return fcmToken || null;
    } catch (error) {
      if (__DEV__) {
        console.warn('[Push] Firebase Messaging no regreso token:', error);
      }
    }
  }

  return null;
}

async function getNativeDevicePushToken(Notifications: ExpoNotificationsModule): Promise<string | null> {
  try {
    const devicePushToken = (await Notifications.getDevicePushTokenAsync()) as DevicePushToken | null;
    const token = extractPushTokenString(devicePushToken);

    if (token) {
      logPushDiagnostic('Token nativo obtenido con expo-notifications', {
        platform: Platform.OS,
        tokenPreview: `${token.slice(0, 12)}...`,
        type: devicePushToken?.type ?? Platform.OS,
      });
    }

    return token;
  } catch (error) {
    if (__DEV__) {
      console.warn('[Push] No se pudo obtener token nativo con expo-notifications:', error);
    }
    return null;
  }
}

function extractPushTokenString(devicePushToken: DevicePushToken | null | undefined): string | null {
  const rawToken = devicePushToken?.data;

  if (typeof rawToken === 'string' && rawToken.trim() !== '') {
    return rawToken.trim();
  }

  if (rawToken && typeof rawToken === 'object' && 'token' in rawToken) {
    const nestedToken = (rawToken as { token?: unknown }).token;
    return typeof nestedToken === 'string' && nestedToken.trim() !== '' ? nestedToken.trim() : null;
  }

  return null;
}

async function getOrCreateDeviceId(): Promise<string> {
  try {
    const existing = await SecureStore.getItemAsync(DEVICE_ID_KEY);
    if (existing?.trim()) {
      return existing.trim();
    }

    const generated = `${Platform.OS}-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
    await SecureStore.setItemAsync(DEVICE_ID_KEY, generated);
    return generated;
  } catch (error) {
    if (__DEV__) {
      console.warn('[Push] No se pudo persistir device_id, se usa fallback de sesion:', error);
    }
    return Constants.sessionId ?? `${Platform.OS}-session`;
  }
}

async function syncPushTokenWithBackend(payload: {
  usuario_id: number;
  fcm_token: string;
  platform: 'ios' | 'android';
  device_id: string;
}) {
  let lastMissingRouteError: unknown = null;

  for (const endpoint of PUSH_TOKEN_ENDPOINTS) {
    try {
      await apiClient.post(endpoint, payload, { _suppressConsoleError: true } as any);
      return;
    } catch (error) {
      if (isMissingPushRoute(error)) {
        lastMissingRouteError = error;
        continue;
      }

      throw error;
    }
  }

  throw lastMissingRouteError ?? new Error('No se encontro un endpoint de push tokens disponible.');
}

function isMissingPushRoute(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === 404;
}

function getSocialNotificationDeepLink(data: Record<string, unknown>, type: string | null): string | null {
  if (!type?.startsWith('social_')) {
    return null;
  }

  const params = new URLSearchParams({ notificationType: type });
  const giftId = stringValue(data.gift_id ?? data.giftId);
  const coverId = stringValue(data.cover_id ?? data.coverId);
  const paymentMode = stringValue(data.payment_mode ?? data.paymentMode);

  if (giftId) params.set('giftId', giftId);
  if (coverId) params.set('coverId', coverId);
  if (paymentMode) params.set('paymentMode', paymentMode);

  return `/social?${params.toString()}`;
}

function stringValue(value: unknown): string | null {
  if (typeof value === 'string') {
    return value.trim() !== '' ? value.trim() : null;
  }

  if (typeof value === 'number' && Number.isFinite(value)) {
    return String(value);
  }

  return null;
}

function withPromotionParams(route: string | null, params: { code: string | null; promotionId: string | null }): string | null {
  if (!route || !isPromotionsRoute(route)) {
    return route;
  }

  const [path, query = ''] = route.split('?');
  const searchParams = new URLSearchParams(query);
  if (params.code && !searchParams.has('code')) {
    searchParams.set('code', params.code);
  } else if (params.promotionId && !searchParams.has('promotionId') && !searchParams.has('promotion_id')) {
    searchParams.set('promotionId', params.promotionId);
  }

  const nextQuery = searchParams.toString();
  return nextQuery ? `${path}?${nextQuery}` : path;
}

function isPromotionsRoute(route: string): boolean {
  const path = route.split('?')[0]?.toLowerCase() ?? '';
  return path === '/promotions' || path === '/(tabs)/promotions';
}
