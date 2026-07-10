import { useEffect, useRef } from 'react';
import { Alert, AppState } from 'react-native';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { getApiError } from '../../services/api';
import {
  getSocialAccountNotifications,
  markSocialAccountNotificationRead,
  respondSocialAccountCoverRequest,
  socialAccountNotificationKeys,
  type SocialAccountNotification,
} from '../../services/social-account.service';
import { getExitPass } from '../../services/orders.service';
import { respondSocialGiftRequest } from '../../services/social-gifts.service';
import { tableSessionKeys } from '../../services/table-session.service';
import { useUserStore } from '../../store/user.store';

const QUERY_KEY = socialAccountNotificationKeys.list;

export function GlobalSocialNotifications() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const isAuthenticated = useUserStore((state) => state.isAuthenticated);
  const token = useUserStore((state) => state.token);
  const user = useUserStore((state) => state.user);
  const activeAlertIdRef = useRef<number | null>(null);
  const snoozedIdsRef = useRef<Set<number>>(new Set());

  const enabled = Boolean(
    isAuthenticated &&
    token &&
    user?.id &&
    !['mesero', 'hostess', 'hostes', 'host', 'anfitrion', 'anfitriona'].includes(String(user?.rol ?? '').toLowerCase())
  );

  const query = useQuery({
    queryKey: QUERY_KEY,
    queryFn: getSocialAccountNotifications,
    enabled,
    refetchInterval: enabled ? 2500 : false,
    refetchIntervalInBackground: false,
    refetchOnMount: 'always',
    staleTime: 500,
  });

  useEffect(() => {
    if (!enabled) {
      return undefined;
    }

    const subscription = AppState.addEventListener('change', (nextState) => {
      if (nextState === 'active') {
        void query.refetch();
      }
    });

    return () => subscription.remove();
  }, [enabled, query.refetch]);

  useEffect(() => {
    if (!enabled || activeAlertIdRef.current !== null) {
      return;
    }

    const notification = (query.data ?? []).find(
      (item) => isGlobalActionableNotification(item) && !snoozedIdsRef.current.has(item.id)
    );
    if (!notification) {
      return;
    }

    activeAlertIdRef.current = notification.id;
    presentNotification(notification);
  }, [enabled, query.data]);

  async function refreshRealtimeState() {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['orders'] }),
      queryClient.invalidateQueries({ queryKey: ['social'] }),
      queryClient.invalidateQueries({ queryKey: tableSessionKeys.diagnostic }),
    ]);
  }

  async function markRead(notification: SocialAccountNotification) {
    snoozedIdsRef.current.add(notification.id);
    try {
      await markSocialAccountNotificationRead(notification.id);
    } finally {
      await refreshRealtimeState();
    }
  }

  function finishAlert(notification: SocialAccountNotification, snooze = true) {
    if (snooze) {
      snoozedIdsRef.current.add(notification.id);
    }
    activeAlertIdRef.current = null;
  }

  function presentNotification(notification: SocialAccountNotification) {
    const giftId = getNumberPayload(notification, 'gift_id');
    const coverId = getNumberPayload(notification, 'cover_id');
    const paymentMode = getStringPayload(notification, 'payment_mode');
    const exitPass = getExitPassPayload(notification);
    const coveredOrderId = getNumberPayload(notification, 'covered_order_id');
    const alertOptions = { cancelable: true, onDismiss: () => finishAlert(notification) };

    if (notification.type === 'social_gift_received' && giftId !== null) {
      Alert.alert(notification.title || 'Regalo recibido', notification.body || 'Alguien quiere enviarte un regalo.', [
        {
          text: 'Luego',
          style: 'cancel',
          onPress: () => finishAlert(notification),
        },
        {
          text: 'Rechazar',
          style: 'destructive',
          onPress: () => {
            void respondGift(notification, giftId, 'reject');
          },
        },
        {
          text: 'Aceptar',
          onPress: () => {
            void respondGift(notification, giftId, 'accept');
          },
        },
      ], alertOptions);
      return;
    }

    if (notification.type === 'social_account_cover_request' && coverId !== null) {
      Alert.alert(notification.title || 'Quieren pagar tu cuenta', notification.body || 'Un comensal quiere cubrir tu cuenta.', [
        {
          text: 'Luego',
          style: 'cancel',
          onPress: () => finishAlert(notification),
        },
        {
          text: 'Rechazar',
          style: 'destructive',
          onPress: () => {
            void respondCover(notification, coverId, 'reject');
          },
        },
        {
          text: 'Aceptar',
          onPress: () => {
            void respondCover(notification, coverId, 'accept');
          },
        },
      ], alertOptions);
      return;
    }

    if (
      (notification.type === 'social_account_covered' || notification.type === 'social_account_paid') &&
      coveredOrderId !== null
    ) {
      presentCoveredAccountActions({
        title: notification.title || 'Cuenta cubierta',
        body: notification.body || 'Tu cuenta fue cubierta.',
        onKeepOrdering: () => {
          void markRead(notification).finally(() => finishAlert(notification, false));
        },
        onGenerateQr: () => {
          void markRead(notification).finally(() => {
            finishAlert(notification, false);
            void openExitPassFromOrder(coveredOrderId, String(notification.payload?.covered_mesa ?? ''));
          });
        },
      });
      return;
    }

    if (exitPass) {
      Alert.alert(notification.title || 'Cuenta cubierta', notification.body || 'Tu cuenta fue cubierta.', [
        {
          text: 'Luego',
          style: 'cancel',
          onPress: () => finishAlert(notification),
        },
        {
          text: 'Ver QR',
          onPress: () => {
            void markRead(notification).finally(() => {
              finishAlert(notification, false);
              router.push({
                pathname: '/checkout/exit-pass',
                params: {
                  orderId: String(exitPass.pedido_id),
                  payload: String(exitPass.payload ?? ''),
                  folio: String(exitPass.folio ?? ''),
                  mesaLabel: String(notification.payload?.covered_mesa ?? exitPass.mesa_id ?? ''),
                },
              } as never);
            });
          },
        },
      ], alertOptions);
      return;
    }

    if (notification.type === 'social_account_cover_approved') {
      const title = notification.title || 'Solicitud aceptada';
      const body = notification.body || 'La otra persona acepto tu solicitud.';
      const actionLabel = paymentMode === 'stripe' ? 'Abrir pago' : 'Entendido';

      Alert.alert(title, body, [
        {
          text: 'Luego',
          style: 'cancel',
          onPress: () => finishAlert(notification),
        },
        {
          text: actionLabel,
          onPress: () => {
            if (paymentMode === 'stripe') {
              snoozedIdsRef.current.add(notification.id);
              finishAlert(notification, false);
              router.push('/(tabs)/social' as never);
              return;
            }

            void markRead(notification).finally(() => finishAlert(notification, false));
          },
        },
      ], alertOptions);
      return;
    }

    finishAlert(notification);
  }

  async function respondGift(
    notification: SocialAccountNotification,
    giftId: number,
    action: 'accept' | 'reject'
  ) {
    try {
      await respondSocialGiftRequest(giftId, action);
      await markRead(notification);
      Alert.alert(
        action === 'accept' ? 'Regalo aceptado' : 'Regalo rechazado',
        action === 'accept'
          ? 'Le avisamos al comensal que aceptaste el regalo.'
          : 'Le avisamos al comensal que preferiste no recibirlo.'
      );
    } catch (error) {
      snoozedIdsRef.current.delete(notification.id);
      Alert.alert('No se pudo responder', getApiError(error));
    } finally {
      finishAlert(notification, false);
    }
  }

  async function respondCover(
    notification: SocialAccountNotification,
    coverId: number,
    action: 'accept' | 'reject'
  ) {
    try {
      const result = await respondSocialAccountCoverRequest(coverId, action);
      await markRead(notification);
      if (
        action === 'accept' &&
        result.cover?.payment_mode !== 'stripe' &&
        typeof result.covered_order_id === 'number' &&
        result.covered_order_id > 0
      ) {
        presentCoveredAccountActions({
          title: 'Cuenta cubierta',
          body: 'Tu cuenta ya quedó cubierta. Puedes seguir pidiendo o generar tu QR de salida.',
          onKeepOrdering: () => undefined,
          onGenerateQr: () => {
            void openExitPassFromOrder(result.covered_order_id as number, result.cover?.covered_mesa ?? '');
          },
        });
        return;
      }

      Alert.alert(
        action === 'accept' ? 'Solicitud aceptada' : 'Solicitud rechazada',
        action === 'accept'
          ? result.cover?.payment_mode === 'stripe'
            ? 'Le avisamos para que termine el pago con tarjeta.'
            : 'Tu cuenta fue cubierta. Ya puedes mostrar tu QR de salida cuando corresponda.'
          : 'Le avisamos que preferiste conservar tu cuenta.'
      );
    } catch (error) {
      snoozedIdsRef.current.delete(notification.id);
      Alert.alert('No se pudo responder', getApiError(error));
    } finally {
      finishAlert(notification, false);
    }
  }

  async function openExitPassFromOrder(orderId: number, mesaLabel?: string | null) {
    try {
      const exitPass = await getExitPass(orderId);
      await refreshRealtimeState();
      router.push({
        pathname: '/checkout/exit-pass',
        params: {
          orderId: String(exitPass.pedido_id),
          payload: String(exitPass.payload ?? ''),
          folio: String(exitPass.folio ?? ''),
          mesaLabel: String(mesaLabel ?? exitPass.mesa_id ?? ''),
        },
      } as never);
    } catch (error) {
      Alert.alert('No se pudo generar el QR', getApiError(error));
    }
  }

  return null;
}

function presentCoveredAccountActions({
  title,
  body,
  onKeepOrdering,
  onGenerateQr,
}: {
  title: string;
  body: string;
  onKeepOrdering: () => void;
  onGenerateQr: () => void;
}) {
  Alert.alert(
    title,
    body,
    [
      {
        text: 'Seguir pidiendo',
        style: 'cancel',
        onPress: onKeepOrdering,
      },
      {
        text: 'Generar QR',
        onPress: onGenerateQr,
      },
    ],
    { cancelable: true, onDismiss: onKeepOrdering }
  );
}

function isGlobalActionableNotification(notification: SocialAccountNotification): boolean {
  if (notification.type === 'social_gift_received') {
    return getNumberPayload(notification, 'gift_id') !== null;
  }
  if (notification.type === 'social_account_cover_request') {
    return getNumberPayload(notification, 'cover_id') !== null;
  }
  if (notification.type === 'social_account_cover_approved') {
    return true;
  }
  if (notification.type === 'social_account_covered' || notification.type === 'social_account_paid') {
    return getNumberPayload(notification, 'covered_order_id') !== null;
  }
  return getExitPassPayload(notification) !== null;
}

function getNumberPayload(notification: SocialAccountNotification, key: string): number | null {
  const value = notification.payload?.[key];
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

function getStringPayload(notification: SocialAccountNotification, key: string): string | null {
  const value = notification.payload?.[key];
  return typeof value === 'string' ? value : null;
}

function getExitPassPayload(notification: SocialAccountNotification): {
  pedido_id: number | string;
  payload?: string | null;
  folio?: string | null;
  mesa_id?: number | string | null;
} | null {
  const exitPass = notification.payload?.exit_pass;
  if (!exitPass || typeof exitPass !== 'object') {
    return null;
  }
  if (!('pedido_id' in exitPass) || !('payload' in exitPass)) {
    return null;
  }
  return exitPass as {
    pedido_id: number | string;
    payload?: string | null;
    folio?: string | null;
    mesa_id?: number | string | null;
  };
}
