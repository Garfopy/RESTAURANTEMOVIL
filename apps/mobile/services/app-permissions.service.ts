import { Alert, Linking, Platform } from 'react-native';
import * as Location from 'expo-location';

declare const require: (name: string) => any;

type ExpoNotificationsModule = typeof import('expo-notifications');
type PermissionName = 'location' | 'notifications';

type PermissionResult = {
  granted: boolean;
  canAskAgain: boolean;
};

let expoNotifications: ExpoNotificationsModule | null | undefined;

const PERMISSION_COPY: Record<PermissionName, { title: string; body: string }> = {
  location: {
    title: 'Activa tu ubicacion',
    body: 'Necesitamos tu ubicacion para detectar sucursales cercanas, delivery y opciones de recoger en tienda.',
  },
  notifications: {
    title: 'Activa las notificaciones',
    body: 'Necesitamos notificaciones para avisarte sobre pedidos, promociones y momentos importantes de tu cuenta.',
  },
};

function getExpoNotifications(): ExpoNotificationsModule | null {
  if (expoNotifications !== undefined) {
    return expoNotifications;
  }

  try {
    expoNotifications = require('expo-notifications') as ExpoNotificationsModule;
  } catch {
    expoNotifications = null;
  }

  return expoNotifications;
}

function showPermissionSettingsAlert(name: PermissionName) {
  const copy = PERMISSION_COPY[name];

  Alert.alert(copy.title, copy.body, [
    { text: 'Ahora no', style: 'cancel' },
    {
      text: 'Abrir ajustes',
      onPress: () => {
        void Linking.openSettings();
      },
    },
  ]);
}

export async function ensureLocationPermission(options?: { explainIfBlocked?: boolean }): Promise<PermissionResult> {
  if (Platform.OS === 'web') {
    return { granted: true, canAskAgain: false };
  }

  let permission = await Location.getForegroundPermissionsAsync();

  if (!permission.granted && permission.canAskAgain) {
    permission = await Location.requestForegroundPermissionsAsync();
  }

  if (!permission.granted && options?.explainIfBlocked) {
    showPermissionSettingsAlert('location');
  }

  return {
    granted: permission.granted,
    canAskAgain: permission.canAskAgain,
  };
}

export async function ensureNotificationPermission(options?: { explainIfBlocked?: boolean }): Promise<PermissionResult> {
  if (Platform.OS === 'web') {
    return { granted: true, canAskAgain: false };
  }

  const Notifications = getExpoNotifications();
  if (!Notifications) {
    return { granted: false, canAskAgain: false };
  }

  let permission = await Notifications.getPermissionsAsync();

  if (!permission.granted && permission.canAskAgain) {
    permission = await Notifications.requestPermissionsAsync();
  }

  if (!permission.granted && options?.explainIfBlocked) {
    showPermissionSettingsAlert('notifications');
  }

  return {
    granted: permission.granted,
    canAskAgain: permission.canAskAgain,
  };
}
