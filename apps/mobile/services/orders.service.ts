import { apiClient } from './api';
import type { Pedido, CreateOrderPayload, PaymentIntent, MetodoPago } from '@amare/types';

export async function createOrder(payload: CreateOrderPayload): Promise<Pedido> {

  const safeItems = payload.items.map((item: any) => ({
    product_id: item.platillo_id ?? item.product_id ?? item.id,
    quantity: item.cantidad ?? item.quantity ?? item.qty ?? 1,
    unit_price: item.precio_unit ?? item.unit_price ?? item.price ?? 0,
    options: item.notas ?? item.options ?? null,
    modificadores: item.modificadores ?? [],
    origen: item.origen ?? 'menu',
  }));

  const subtotal = calculateSubtotal(safeItems);

  const { data } = await apiClient.post<{
    success: boolean;
    data: { order: Pedido }
  }>('/orders', {
    restaurante_id: payload.restaurante_id,
    tipo_pedido: payload.tipo_pedido,
    subtotal,
    total: subtotal,
    items: safeItems,
    direccion_id: payload.direccion_id ?? null,
    direccion_entrega: payload.direccion_entrega ?? null,
    notas: payload.notas ?? null,
  });

  return data.data.order;
}

/** Calcula subtotal de forma segura */
function calculateSubtotal(items: any[]): number {
  return items.reduce((sum, item) => {
    const price = Number(item.unit_price ?? 0);
    const qty = Number(item.quantity ?? 0);
    return sum + price * qty;
  }, 0);
}

export async function getOrders(): Promise<Pedido[]> {
  const { data } = await apiClient.get<{
    success: boolean;
    data: { orders: Pedido[] }
  }>('/orders');

  return (data.data.orders || []).map(order => ({
    ...order,
    total: Number(order.total || 0),
    subtotal: Number(order.subtotal || 0)
  }));
}

export async function getOrderById(id: number): Promise<Pedido> {
  const { data } = await apiClient.get<{
    success: boolean;
    data: { order: Pedido }
  }>(`/orders/${id}`);

  return data.data.order;
}

export async function getStoreOrders(): Promise<Pedido[]> {
  const { data } = await apiClient.get<{
    success: boolean;
    data: { orders: Pedido[] }
  }>('/orders', { params: { tipo: 'store' } });

  return (data.data.orders || []).map(order => ({
    ...order,
    total: Number(order.total || 0),
    subtotal: Number(order.subtotal || 0)
  }));
}

export async function getOrderTracking(_id: number) {
  return { tracking: [] };
}

export async function createPaymentIntent(params: {
  order_id?: number;
  amount: number;
  currency?: string;
}): Promise<PaymentIntent> {
  const payload: Record<string, unknown> = {
    amount: params.amount,
    currency: params.currency ?? 'mxn',
  };

  if (typeof params.order_id === 'number' && Number.isInteger(params.order_id) && params.order_id > 0) {
    payload.order_id = params.order_id;
  }

  const { data } = await apiClient.post<{
    success: boolean;
    data: { client_secret: string; payment_intent_id: string }
  }>('/payments/create-intent', payload);

  return {
    id: data.data.payment_intent_id,
    client_secret: data.data.client_secret,
    amount: params.amount,
    currency: params.currency ?? 'mxn',
    status: 'requires_payment',
  };
}

export async function confirmPayment(params: {
  payment_intent_id: string;
  pedido_id: number;
  metodo: MetodoPago;
}) {
  const { data } = await apiClient.post<{
    success: boolean;
    data: { ok: boolean; pedido_id: number; folio: string; metodo_pago: string }
  }>(`/orders/${params.pedido_id}/confirm-payment`, {
    payment_intent_id: params.payment_intent_id,
    metodo: params.metodo,
  });

  return data.data;
}
