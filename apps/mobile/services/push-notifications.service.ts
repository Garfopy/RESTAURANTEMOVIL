import { Platform } from 'react-native';
import Constants from 'expo-constants';
import type { NotificationResponse } from 'expo-notifications';
import type { FirebaseMessagingTypes } from '@react-native-firebase/messaging';
import { apiClient } from './api';
import { normalizeAppDeepLink } from './deep-links.service';

declare const require: (name: string) => any;

type ExpoNotificationsModule = typeof import('expo-notifications');
type FirebaseMessagingModule = typeof import('@react-native-firebase/messaging');
type FirebaseMessaging = FirebaseMessagingModule['default'];

let expoNotifications: ExpoNotificationsModule | null | undefined;
let firebaseMessaging: FirebaseMessaging | null | undefined;

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

export async function registerPushNotifications(): Promise<string | null> {
  if (Platform.OS === 'web' || !isPushRegistrationEnabled()) {
    return null;
  }

  try {
    const Notifications = getExpoNotifications();
    const messaging = getFirebaseMessaging();
    if (!Notifications || !messaging) {
      logPushDiagnostic('Modulos nativos no disponibles', {
        hasExpoNotifications: Boolean(Notifications),
        hasFirebaseMessaging: Boolean(messaging),
        platform: Platform.OS,
      });
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

    const permissions = await Notifications.getPermissionsAsync();
    let finalStatus = permissions.status;
    logPushDiagnostic('Permiso actual', {
      platform: Platform.OS,
      status: finalStatus,
      canAskAgain: permissions.canAskAgain,
      granted: permissions.granted,
    });

    if (finalStatus !== 'granted') {
      const requested = await Notifications.requestPermissionsAsync();
      finalStatus = requested.status;
      logPushDiagnostic('Permiso solicitado', {
        platform: Platform.OS,
        status: finalStatus,
        canAskAgain: requested.canAskAgain,
        granted: requested.granted,
      });
    }

    if (finalStatus !== 'granted') {
      logPushDiagnostic('Permiso denegado', { platform: Platform.OS });
      return null;
    }

    if (Platform.OS === 'ios') {
      await messaging().registerDeviceForRemoteMessages();
      logPushDiagnostic('Dispositivo iOS registrado para mensajes remotos', {
        platform: Platform.OS,
        registered: messaging().isDeviceRegisteredForRemoteMessages,
      });
    }

    const token = await messaging().getToken();
    if (!token) {
      logPushDiagnostic('Firebase no regreso token FCM', { platform: Platform.OS });
      return null;
    }
    logPushDiagnostic('Token FCM obtenido', {
      platform: Platform.OS,
      tokenPreview: `${token.slice(0, 12)}...`,
      length: token.length,
    });

    await apiClient.post('/profile/push-token', {
      fcm_token: token,
      platform: Platform.OS,
      device_id: Constants.sessionId ?? null,
    });
    logPushDiagnostic('Token push guardado en API', { platform: Platform.OS });

    return token;
  } catch (error) {
    console.warn('[Push] Registro desactivado o fallido:', error);
    return null;
  }
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
  await apiClient.delete('/profile/push-token', {
    data: { fcm_token: fcmToken },
  });
}

export function getNotificationDeepLink(response: NotificationResponse): string | null {
  const data = response.notification.request.content.data ?? {};
  const deepLink = data.deep_link ?? data.deepLink;

  if (typeof deepLink === 'string') {
    return normalizeAppDeepLink(deepLink);
  }

  const code = data.code ?? data.codigo;
  if (typeof code === 'string' && code.trim() !== '') {
    return `/promotions?code=${encodeURIComponent(code.trim())}`;
  }

  return null;
}
