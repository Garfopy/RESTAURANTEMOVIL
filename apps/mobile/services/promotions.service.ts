import { apiClient } from './api';

// ─────────────────────────────────────────────
// Tipos
// ─────────────────────────────────────────────

export interface Promotion {
  id: number;
  usuario_id: number;
  platillo_id?: number | null;
  titulo: string;
  descripcion?: string | null;
  imagen?: string | null;
  deep_link?: string | null;
  code?: string | null;
  activo: number;
  expires_at?: string | null;
  created_at: string;
  // Campos extra cuando viene del admin (JOIN con usuarios)
  usuario_nombre?: string;
  usuario_email?: string;
}

export interface PromotionQuote {
  promotion: Promotion;
  code: string;
  discount: number;
  subtotal: number;
  eligible_subtotal: number;
  total: number;
  applicable_product_ids: number[];
}

export interface PaginationMeta {
  total: number;
  page: number;
  per_page: number;
  pages: number;
}

export interface PromotionListResponse {
  promotions: Promotion[];
  pagination: PaginationMeta;
}

export interface UserOption {
  id: number;
  nombre: string;
  email: string;
  created_at: string;
}

export interface UserListResponse {
  users: UserOption[];
  pagination: PaginationMeta;
}

export interface CreatePromotionPayload {
  usuario_id: number;
  platillo_id?: number | null;
  titulo: string;
  descripcion?: string;
  imagen?: string;
  deep_link?: string;
  code?: string;
  activo?: number;
  expires_at?: string;
}

export type UpdatePromotionPayload = Partial<CreatePromotionPayload>;

// ─────────────────────────────────────────────
// Endpoints APP MOVIL (usuario autenticado)
// ─────────────────────────────────────────────

/** GET /promotions  -  Promociones activas del usuario autenticado */
export async function getMyPromotions(): Promise<Promotion[]> {
  const res = await apiClient.get('/promotions');
  return res.data.data ?? [];
}

/** GET /promotions/:id  -  Detalle de una promocion */
export async function getPromotion(id: number): Promise<Promotion> {
  const res = await apiClient.get(`/promotions/${id}`);
  return res.data.data.promotion;
}

/** POST /promotions/validate  -  Valida un código de descuento */
export async function validatePromoCode(
  code: string,
  items: Array<{ product_id: number; quantity: number; unit_price: number; origen?: string }>
): Promise<PromotionQuote> {
  const res = await apiClient.post('/promotions/validate', { code, items });
  return res.data.data;
}

// ─────────────────────────────────────────────
// Endpoints ADMIN (requiere token con rol=admin)
// ─────────────────────────────────────────────

/** GET /admin/promotions  -  Lista paginada de todas las promociones */
export async function adminGetPromotions(params?: {
  page?: number;
  per_page?: number;
  usuario_id?: number;
}): Promise<PromotionListResponse> {
  const res = await apiClient.get('/admin/promotions', { params });
  return res.data.data;
}

/** POST /admin/promotions  -  Crea una nueva promocion para un usuario */
export async function adminCreatePromotion(
  payload: CreatePromotionPayload
): Promise<Promotion> {
  const res = await apiClient.post('/admin/promotions', payload);
  return res.data.data.promotion;
}

/** PUT /admin/promotions/:id  -  Actualiza una promocion existente */
export async function adminUpdatePromotion(
  id: number,
  payload: UpdatePromotionPayload
): Promise<Promotion> {
  const res = await apiClient.put(`/admin/promotions/${id}`, payload);
  return res.data.data.promotion;
}

/** DELETE /admin/promotions/:id  -  Elimina permanentemente una promocion */
export async function adminDeletePromotion(id: number): Promise<void> {
  await apiClient.delete(`/admin/promotions/${id}`);
}

/** PUT /admin/promotions/:id/deactivate  -  Desactiva (soft-delete) una promocion */
export async function adminDeactivatePromotion(id: number): Promise<Promotion> {
  const res = await apiClient.put(`/admin/promotions/${id}/deactivate`);
  return res.data.data.promotion;
}

/** GET /admin/users  -  Lista de usuarios para el selector del panel admin */
export async function adminGetUsers(params?: {
  search?: string;
  page?: number;
  per_page?: number;
}): Promise<UserListResponse> {
  const res = await apiClient.get('/admin/users', { params });
  return res.data.data;
}
