import { apiClient } from './api';

export const socialAccountNotificationKeys = {
  list: ['social', 'account-notifications'] as const,
};

type ApiEnvelope<T> = {
  success?: boolean;
  data?: T;
};

export type SocialAccountItem = {
  id: number;
  pedido_id: number;
  platillo_id?: number | null;
  nombre: string;
  cantidad: number;
  precio_unit: number;
  subtotal: number;
};

export type SocialDinerAccount = {
  consumo_id: string;
  order_ids: number[];
  subtotal_mxn: number;
  total_mxn: number;
  items_count: number;
  items: SocialAccountItem[];
};

export type SocialAccountParticipant = {
  user_id: number;
  nombre: string;
  mesa?: string | null;
};

export type SocialDinerAccountResult = {
  available: boolean;
  recipient: SocialAccountParticipant;
  payer?: SocialAccountParticipant;
  account?: SocialDinerAccount;
  message?: string;
};

export type SocialAccountCover = {
  id: number;
  restaurante_id: number;
  payer_user_id: number;
  covered_user_id: number;
  payer_pedido_id?: number | null;
  payment_mode: 'account' | 'stripe' | 'wallet';
  status: string;
  amount_mxn: number;
  items_count: number;
  payer_mesa?: string | null;
  covered_mesa?: string | null;
  message?: string | null;
  paid_at?: string | null;
};

export type CoverSocialAccountResult = {
  cover: SocialAccountCover;
  account?: SocialDinerAccount;
  charged_to_account?: boolean;
  approval_required?: boolean;
  covered_exit_pass?: unknown;
  covered_order_id?: number | null;
  covered_visit_id?: number | null;
  post_payment_action_required?: boolean;
  wallet?: unknown;
  client_secret?: string;
  payment_intent_id?: string;
};

export type SocialAccountNotification = {
  id: number;
  actor_user_id?: number | null;
  type: string;
  title: string;
  body: string;
  payload?: Record<string, unknown>;
  read_at?: string | null;
  created_at?: string | null;
};

function unwrapEnvelope<T>(payload: ApiEnvelope<T> | T): T {
  if (
    payload &&
    typeof payload === 'object' &&
    'data' in (payload as ApiEnvelope<T>) &&
    (payload as ApiEnvelope<T>).data !== undefined
  ) {
    return (payload as ApiEnvelope<T>).data as T;
  }
  return payload as T;
}

export async function getSocialDinerAccount(
  dinerUserId: number,
  restaurantId: number
): Promise<SocialDinerAccountResult> {
  const response = await apiClient.get<ApiEnvelope<SocialDinerAccountResult> | SocialDinerAccountResult>(
    `/social/diners/${dinerUserId}/account`,
    { params: { restaurant_id: restaurantId } }
  );

  return unwrapEnvelope(response.data);
}

export async function getSocialAccountNotifications(): Promise<SocialAccountNotification[]> {
  const response = await apiClient.get<
    ApiEnvelope<{ notifications: SocialAccountNotification[] }> | { notifications: SocialAccountNotification[] }
  >('/social/account-notifications', { _suppressConsoleError: true } as any);

  return unwrapEnvelope(response.data).notifications ?? [];
}

export async function markSocialAccountNotificationRead(notificationId: number): Promise<void> {
  await apiClient.post(`/social/account-notifications/${notificationId}/read`);
}

export async function coverSocialDinerAccount(params: {
  dinerUserId: number;
  restaurantId: number;
  paymentMode: 'account' | 'stripe' | 'wallet';
  requestKey: string;
}): Promise<CoverSocialAccountResult> {
  const response = await apiClient.post<ApiEnvelope<CoverSocialAccountResult> | CoverSocialAccountResult>(
    `/social/diners/${params.dinerUserId}/cover-account`,
    {
      restaurant_id: params.restaurantId,
      payment_mode: params.paymentMode,
      request_key: params.requestKey,
    }
  );

  return unwrapEnvelope(response.data);
}

export async function respondSocialAccountCoverRequest(
  coverId: number,
  action: 'accept' | 'reject'
): Promise<CoverSocialAccountResult> {
  const response = await apiClient.post<ApiEnvelope<CoverSocialAccountResult> | CoverSocialAccountResult>(
    `/social/account-covers/${coverId}/respond`,
    { action }
  );

  return unwrapEnvelope(response.data);
}

export async function prepareSocialAccountCoverPayment(coverId: number): Promise<CoverSocialAccountResult> {
  const response = await apiClient.post<ApiEnvelope<CoverSocialAccountResult> | CoverSocialAccountResult>(
    `/social/account-covers/${coverId}/prepare-payment`
  );

  return unwrapEnvelope(response.data);
}

export async function confirmSocialAccountCoverPayment(coverId: number): Promise<CoverSocialAccountResult> {
  const response = await apiClient.post<ApiEnvelope<CoverSocialAccountResult> | CoverSocialAccountResult>(
    `/social/account-covers/${coverId}/confirm-payment`
  );

  return unwrapEnvelope(response.data);
}
