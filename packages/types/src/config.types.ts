export type MetodoPagoHabilitado = 'card' | 'cash' | 'apple_pay' | 'google_pay';

export type TipoEntregaHabilitado = 'delivery' | 'pickup' | 'eat_in';

export interface RestaurantConfig {
  restaurante_id: number;
  metodos_pago: MetodoPagoHabilitado[];
  tipos_entrega: TipoEntregaHabilitado[];
  costo_envio: number;
  pedido_minimo: number;
  activo: boolean;
}