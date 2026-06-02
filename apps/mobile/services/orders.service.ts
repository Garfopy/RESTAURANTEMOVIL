import { apiClient } from './api';
import type { Pedido, CreateOrderPayload, PaymentIntent, PaymentResult, MetodoPago } from '@amare/types';

export async function createOrder(payload: CreateOrderPayload): Promise<Pedido> {
  const { data } = await apiClient.post<{ ok: boolean; data: Pedido }>('/orders', payload);
  return data.data;
}

export async function getOrders(page = 1): Promise<Pedido[]> {
  const { data } = await apiClient.get<{ ok: boolean; data: Pedido[] }>('/orders', {
    params: { page },
  });
  return data.data;
}

export async function getOrderById(id: number): Promise<Pedido> {
  const { data } = await apiClient.get<{ ok: boolean; data: Pedido }>(`/orders/${id}`);
  return data.data;
}

export async function getOrderTracking(id: number) {
  const { data } = await apiClient.get(`/orders/${id}/tracking`);
  return data.data;
}

export async function createPaymentIntent(params: {
  restaurante_id: number;
  amount: number;
  currency?: string;
}): Promise<PaymentIntent> {
  const { data } = await apiClient.post<{ ok: boolean; data: PaymentIntent }>('/payments/intent', params);
  return data.data;
}

export async function confirmPayment(params: {
  payment_intent_id: string;
  pedido_id: number;
  metodo: MetodoPago;
}): Promise<PaymentResult> {
  const { data } = await apiClient.post<{ ok: boolean; data: PaymentResult }>(
    '/payments/confirm',
    params
  );
  return data.data;
}
