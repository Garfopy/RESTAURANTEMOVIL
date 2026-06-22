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
  incluida?: boolean;
  visible?: boolean;
  puede_omitirse?: boolean;
  omitida_por_defecto?: boolean;
  seleccionada_por_defecto?: boolean;
  accion_al_desmarcar?: string;
  cantidad_inicial?: number;
}

export interface DishModifierSelector {
  tipo: 'personalizacion_platillo';
  titulo: string;
  visible: boolean;
  incluidas: Array<{
    id: number;
    tipo: 'exclusion';
    nombre: string;
    incluida: boolean;
    visible: boolean;
    puede_omitirse: boolean;
    omitida_por_defecto: boolean;
    seleccionada_por_defecto: boolean;
    accion_al_desmarcar: string;
  }>;
  extras: Array<{
    id: number;
    tipo: 'extra';
    nombre: string;
    precio_unitario: number;
    cantidad_inicial: number;
    max_cantidad: number;
  }>;
}

export interface DishModifierConfig {
  modificadores: BranchDishModifier[];
  selector: DishModifierSelector;
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
  platillos_modificadores: Record<string, DishModifierConfig>;
  selector: {
    exclusiones: boolean;
    extras: boolean;
  };
  activo: boolean;
}
