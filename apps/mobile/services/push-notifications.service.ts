import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';
import Constants from 'expo-constants';
import messaging, { FirebaseMessagingTypes } from '@react-native-firebase/messaging';
import { apiClient } from './api';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
  }),
});

messaging().setBackgroundMessageHandler(async () => undefined);

export async function registerPushNotifications(): Promise<string | null> {
  if (Platform.OS === 'web') {
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
}

export function subscribeForegroundFirebaseMessages() {
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
