import { apiClient } from './api';

export type GiftCheckoutMode = 'account' | 'stripe';

type ApiEnvelope<T> = {
  success?: boolean;
  data?: T;
};

export type SocialGiftOrder = {
  id: number;
  folio?: string | null;
  mesa_id?: number;
  mesa_label?: string | null;
  gift_nombre: string;
  gift_precio: number;
  recipient_nombre: string;
  sender_nombre?: string;
  recipient_mesa?: string | null;
  status?: string;
  sender_mesa_id?: number | null;
  pedido_id?: number | null;
  pedido_item_id?: number | null;
  charged_to_account?: boolean;
};

export type CreateSocialGiftPaymentResult = {
  gift: SocialGiftOrder;
  charged_to_account?: boolean;
  account?: {
    pedido_id: number;
    pedido_item_id: number;
  };
  client_secret?: string;
  payment_intent_id?: string;
};

type CreateSocialGiftPaymentParams = {
  restaurant_id: number;
  recipient_user_id: number;
  gift_product_id: number;
  gift_type?: 'gift' | 'menu';
  request_key: string;
  payment_mode: GiftCheckoutMode;
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

export async function createSocialGiftPayment(
  payload: CreateSocialGiftPaymentParams
): Promise<CreateSocialGiftPaymentResult> {
  const response = await apiClient.post<ApiEnvelope<CreateSocialGiftPaymentResult> | CreateSocialGiftPaymentResult>(
    '/social-gifts',
    payload
  );

  return unwrapEnvelope(response.data);
}

export async function confirmSocialGiftPayment(giftId: number): Promise<SocialGiftOrder> {
  const response = await apiClient.post<ApiEnvelope<SocialGiftOrder> | SocialGiftOrder>(
    `/social-gifts/${giftId}/confirm-payment`
  );

  return unwrapEnvelope(response.data);
}
