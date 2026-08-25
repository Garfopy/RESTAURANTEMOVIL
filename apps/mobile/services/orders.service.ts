import { apiClient } from './api';
import { API_BASE_URL } from '../constants/api';
import type { InvoiceRequestPayload } from './fiscal.service';
import type { Pedido, CreateOrderPayload, PaymentIntent, MetodoPago, ExitPass, TrackingEvent } from '@amare/types';

function flattenModifierSelection(modifiers: any[]): Array<{ modificador_id: number; cantidad: number }> {
  const quantities = new Map<number, number>();
  for (const group of modifiers ?? []) {
    for (const option of group.opciones ?? []) {
      const id = Number(option.opcion_id);
      const quantity = Math.max(1, Number(option.cantidad ?? 1));
      if (id > 0) quantities.set(id, (quantities.get(id) ?? 0) + quantity);
    }
  }
  return [...quantities].map(([modificador_id, cantidad]) => ({ modificador_id, cantidad }));
}

export async function createOrder(payload: CreateOrderPayload): Promise<Pedido> {

  const safeItems = payload.items.map((item: any) => ({
    product_id: item.platillo_id ?? item.product_id ?? item.id,
    quantity: item.cantidad ?? item.quantity ?? item.qty ?? 1,
    unit_price: item.precio_unit ?? item.unit_price ?? item.price ?? 0,
    options: item.notas ?? item.options ?? null,
    modificadores: flattenModifierSelection(item.modificadores ?? []),
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
    payment_intent_id: payload.payment_intent_id ?? null,
    promo_code: payload.promo_code ?? null,
    notas: payload.notas ?? null,
  });

  return data.data.order;
}

export async function createPickupOrder(payload: {
  restaurante_id: number;
  usuario_id: number;
  cliente_nombre: string;
  comprador_telefono?: string | null;
  metodo_pago: string;
  payment_intent_id?: string | null;
  app_order_id: string;
  pagado: boolean;
  pickup_at?: string | null;
  items: Array<{
    platillo_id: number;
    cantidad: number;
    notas?: string | null;
    modificadores?: Array<{ modificador_id: number; cantidad: number }>;
  }>;
}): Promise<Pedido> {
  const { data } = await apiClient.post<{
    ok?: boolean;
    message?: string;
    data?: { pedido?: Pedido; idempotent?: boolean };
  }>(apiV1Url('/rest-pedidos'), {
    restaurante_id: payload.restaurante_id,
    usuario_id: payload.usuario_id,
    tipo_pedido: 'pickup',
    tipo_entrega: 'pickup',
    items: payload.items,
    cliente_nombre: payload.cliente_nombre,
    comprador_telefono: payload.comprador_telefono ?? null,
    metodo_pago: payload.metodo_pago,
    payment_intent_id: payload.payment_intent_id ?? null,
    app_order_id: payload.app_order_id,
    pickup_at: payload.pickup_at ?? null,
    pagado: payload.pagado,
  });

  const pedido = data.data?.pedido;
  if (!data.ok || !pedido) {
    throw new Error(data.message || 'No se pudo crear el pedido para recoger.');
  }

  return {
    ...pedido,
    total: Number(pedido.total || 0),
    subtotal: Number(pedido.subtotal || 0),
    items: Array.isArray(pedido.items) ? pedido.items : [],
  };
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

export async function getPickupOrderById(id: number, userId: number): Promise<Pedido> {
  const { data } = await apiClient.get<{
    ok?: boolean;
    message?: string;
    data?: { pedido?: Pedido };
  }>(apiV1Url(`/rest-pedidos/${id}`), {
    params: { usuario_id: userId },
    _suppressConsoleError: true,
  } as any);

  const pedido = data.data?.pedido;
  if (!data.ok || !pedido) {
    throw new Error(data.message || 'No se pudo consultar el pedido para recoger.');
  }

  return {
    ...pedido,
    total: Number(pedido.total || 0),
    subtotal: Number(pedido.subtotal || 0),
    items: Array.isArray(pedido.items) ? pedido.items : [],
  };
}

function apiV1Url(path: string): string {
  const cleanPath = path.startsWith('/') ? path : `/${path}`;
  try {
    const apiUrl = new URL(API_BASE_URL);
    return `${apiUrl.origin}/api/v1${cleanPath}`;
  } catch {
    return `/api/v1${cleanPath}`;
  }
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

export async function getOrderTracking(id: number): Promise<{ tracking: TrackingEvent[] }> {
  const { data } = await apiClient.get<{
    success: boolean;
    data: { timeline?: TrackingEvent[]; tracking?: TrackingEvent[] }
  }>(`/orders/${id}/timeline`);

  return {
    tracking: data.data.timeline ?? data.data.tracking ?? [],
  };
}

export async function createPaymentIntent(params: {
  order_id: number;
  currency?: string;
  promo_code?: string;
  use_points?: boolean;
  invoice_request?: InvoiceRequestPayload | null;
}): Promise<PaymentIntent> {
  const payload: Record<string, unknown> = {
    order_id: params.order_id,
    currency: params.currency ?? 'mxn',
  };
  if (params.promo_code) {
    payload.promo_code = params.promo_code;
  }
  if (params.use_points) {
    payload.use_points = true;
  }
  if (params.invoice_request) {
    payload.invoice_request = params.invoice_request;
  }

  const { data } = await apiClient.post<{
    success: boolean;
    data: { client_secret: string; payment_intent_id: string; amount_mxn: number; status?: string; use_points?: boolean }
  }>('/payments/create-intent', payload);

  return {
    id: data.data.payment_intent_id,
    client_secret: data.data.client_secret,
    amount: Number(data.data.amount_mxn),
    currency: params.currency ?? 'mxn',
    status: data.data.status ?? 'requires_payment',
    use_points: data.data.use_points ?? false,
  };
}

export async function confirmPayment(params: {
  payment_intent_id?: string;
  pedido_id: number;
  metodo: MetodoPago;
  use_points?: boolean;
  promo_code?: string;
  invoice_request?: InvoiceRequestPayload | null;
}) {
  const { data } = await apiClient.post<{
    success: boolean;
    message?: string;
    data: { ok: boolean; pedido_id: number; folio: string; metodo_pago: string; exit_pass?: ExitPass | null }
  }>(`/orders/${params.pedido_id}/confirm-payment`, {
    payment_intent_id: params.payment_intent_id ?? '',
    metodo: params.metodo,
    use_points: params.use_points ?? false,
    promo_code: params.promo_code ?? null,
    invoice_request: params.invoice_request ?? null,
  });

  if (!data?.data) {
    throw new Error(data?.message || 'No se pudo confirmar el pago.');
  }

  return data.data;
}

