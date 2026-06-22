export type MetodoPagoHabilitado = 'card' | 'cash' | 'apple_pay' | 'google_pay';

export type TipoEntregaHabilitado = 'delivery' | 'pickup' | 'eat_in';

export interface BranchDishModifier {
  id: number;
  tipo: 'exclusion' | 'extra';
  nombre: string;
  ingrediente_id: number | null;
  cantidad_unidad: number;
  unidad: string | null;
  precio_unitario: number;
  max_cantidad: number;
}

export interface RestaurantConfig {
  restaurante_id: number;
  metodos_pago: MetodoPagoHabilitado[];
  tipos_entrega: TipoEntregaHabilitado[];
  costo_envio: number;
  pedido_minimo: number;
  version: number;
  updated_at: string | null;
  modificadores: {
    exclusiones_habilitadas: boolean;
    extras_habilitados: boolean;
  };
  platillos_modificadores: Record<string, BranchDishModifier[]>;
  selector: {
    exclusiones: boolean;
    extras: boolean;
  };
  activo: boolean;
}
