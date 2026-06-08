import { apiClient, formatImageUrl } from './api';
import type { StoreCategory, StoreProduct, Pedido } from '@amare/types';

/** Convierte rutas relativas de BD (uploads/...) a URL completa de la API */
function resolveImg<T extends { imagen: string | null }>(item: T): T {
  if (item.imagen && !item.imagen.startsWith('http')) {
    return { ...item, imagen: formatImageUrl(item.imagen) ?? item.imagen };
  }
  return item;
}

/**
 * GET /store/categories
 * Obtiene todas las categorías activas de la tienda.
 */
export async function getStoreCategories(): Promise<StoreCategory[]> {
  const { data } = await apiClient.get<{ success: boolean; data: StoreCategory[] }>(
    '/store/categories'
  );
  return data.data.map(resolveImg);
}

export interface GetStoreProductsParams {
  categoria_id?: number;
  q?: string;
}

/**
 * GET /store/products?categoria_id=&q=
 * Obtiene productos de la tienda con filtros opcionales.
 */
export async function getStoreProducts(params?: GetStoreProductsParams): Promise<StoreProduct[]> {
  const queryParams: Record<string, string | number> = {};
  if (params?.categoria_id) queryParams.categoria_id = params.categoria_id;
  if (params?.q) queryParams.q = params.q;

  const { data } = await apiClient.get<{ success: boolean; data: StoreProduct[] }>(
    '/store/products',
    { params: queryParams }
  );
  return data.data.map(resolveImg);
}

/**
 * GET /store/products/:id
 * Obtiene el detalle de un producto de la tienda.
 */
export async function getStoreProductById(id: number): Promise<StoreProduct> {
  const { data } = await apiClient.get<{ success: boolean; data: { product: StoreProduct } }>(
    `/store/products/${id}`
  );
  return resolveImg(data.data.product);
}

export interface CreateStoreOrderPayload {
  product_id: number;
  quantity: number;
  unit_price: number;
  tipo_pedido: 'delivery' | 'pickup';
  direccion_id?: number;
  direccion_entrega?: string;
  subtotal?: number;
  payment_intent_id?: string;
  restaurante_id?: number;
}

/**
 * POST /orders
 * Crea un pedido para un producto de tienda.
 * Soporta delivery (envío a domicilio) y pickup (recoger en tienda).
 */
export async function createStoreOrder(payload: CreateStoreOrderPayload): Promise<Pedido> {
  const subtotal = payload.subtotal ?? (payload.unit_price * payload.quantity);

  const body: Record<string, unknown> = {
    restaurante_id: payload.restaurante_id ?? 1,
    tipo_pedido: payload.tipo_pedido,
    subtotal,
    total: subtotal,
    items: [
      {
        product_id: payload.product_id,
        quantity: payload.quantity,
        unit_price: payload.unit_price,
        options: null,
        origen: 'store',
      },
    ],
    notas: payload.tipo_pedido === 'pickup'
      ? 'Compra en Tienda Amare – Recoger en sucursal'
      : 'Compra en Tienda Amare',
  };

  // Solo incluir dirección si es delivery
  if (payload.tipo_pedido === 'delivery') {
    body.direccion_id = payload.direccion_id ?? null;
    body.direccion_entrega = payload.direccion_entrega ?? null;
  }

  if (payload.payment_intent_id) {
    body.payment_intent_id = payload.payment_intent_id;
  }

  const { data } = await apiClient.post<{
    success: boolean;
    data: { order: Pedido };
  }>('/orders', body);

  return data.data.order;
}
