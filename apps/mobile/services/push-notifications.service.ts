import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';
import Constants from 'expo-constants';
import type { FirebaseMessagingTypes } from '@react-native-firebase/messaging';
import { apiClient } from './api';

declare const require: (name: string) => any;

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
  }),
});

type FirebaseMessagingModule = typeof import('@react-native-firebase/messaging');
type FirebaseMessaging = FirebaseMessagingModule['default'];

let firebaseMessaging: FirebaseMessaging | null | undefined;

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

try {
  getFirebaseMessaging()?.().setBackgroundMessageHandler(async () => undefined);
} catch (error) {
  firebaseMessaging = null;
  if (__DEV__) {
    console.warn('[Push] No se pudo configurar background handler:', error);
  }
}

export function isPushRegistrationEnabled(): boolean {
  return process.env.EXPO_PUBLIC_ENABLE_PUSH_REGISTRATION === 'true';
}

export async function registerPushNotifications(): Promise<string | null> {
  if (Platform.OS === 'web' || !isPushRegistrationEnabled()) {
    return null;
  }

  try {
    const messaging = getFirebaseMessaging();
    if (!messaging) {
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

    if (finalStatus !== 'granted') {
      const requested = await Notifications.requestPermissionsAsync();
      finalStatus = requested.status;
    }

    if (finalStatus !== 'granted') {
      return null;
    }

    if (Platform.OS === 'ios') {
      await messaging().registerDeviceForRemoteMessages();
    }

    const token = await messaging().getToken();
    if (!token) {
      return null;
    }

    await apiClient.post('/profile/push-token', {
      fcm_token: token,
      platform: Platform.OS,
      device_id: Constants.sessionId ?? null,
    });

    return token;
  } catch (error) {
    if (__DEV__) {
      console.warn('[Push] Registro desactivado o fallido:', error);
    }
    return null;
  }
}

export function subscribeForegroundFirebaseMessages() {
  const messaging = getFirebaseMessaging();
  if (!messaging) {
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

export async function unregisterPushNotifications(fcmToken: string): Promise<void> {
  if (!fcmToken) return;
  await apiClient.delete('/profile/push-token', {
    data: { fcm_token: fcmToken },
  });
}

export function getNotificationDeepLink(response: Notifications.NotificationResponse): string | null {
  const data = response.notification.request.content.data ?? {};
  const deepLink = data.deep_link ?? data.deepLink;

  return typeof deepLink === 'string' && deepLink.trim() !== '' ? deepLink : null;
}
