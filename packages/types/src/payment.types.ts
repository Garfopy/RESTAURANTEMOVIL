export interface PaymentIntent {
  id: string;
  client_secret: string;
  amount: number;
  currency: string;
  status: string;
}

export type MetodoPago = 'card' | 'apple_pay' | 'google_pay' | 'cash';

export interface ConfirmPaymentPayload {
  payment_intent_id: string;
  pedido_id: number;
  metodo: MetodoPago;
}

export interface PaymentResult {
  ok: boolean;
  pedido_id: number;
  folio: string;
  metodo_pago: MetodoPago;
}
