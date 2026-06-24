export interface PaymentIntent {
  id: string;
  client_secret: string;
  amount: number;
  currency: string;
  status: string;
}

export type MetodoPago = 'card' | 'apple_pay' | 'google_pay' | 'cash' | 'amare_wallet';

export interface ConfirmPaymentPayload {
  payment_intent_id: string;
  pedido_id: number;
  metodo: MetodoPago;
  use_points?: boolean;
}

export interface PaymentResult {
  ok: boolean;
  pedido_id: number;
  folio: string;
  metodo_pago: MetodoPago;
}
