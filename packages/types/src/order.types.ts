export type TipoPedido = 'delivery' | 'pickup' | 'eat_in';

export type EstadoPedido =
  | 'pendiente'
  | 'en_preparacion'
  | 'listo'
  | 'en_camino'
  | 'entregado'
  | 'cancelado';

export interface PedidoItem {
  id: number;
  platillo_id: number;
  platillo_nombre: string;
  platillo_imagen: string | null;
  cantidad: number;
  precio_unit: number;
  subtotal: number;
  notas: string | null;
  exclusiones: string | null;
  extras: string | null;
  estado: EstadoPedido;
}

export interface Pedido {
  id: number;
  restaurante_id: number;
  restaurante_nombre: string;
  folio: string;
  tipo_pedido: TipoPedido;
  estado: EstadoPedido;
  subtotal: number;
  descuento: number;
  envio: number;
  total: number;
  mesa_id: number | null;
  mesa_nombre: string | null;
  direccion_entrega: string | null;
  notas: string | null;
  items: PedidoItem[];
  created_at: string;
  updated_at: string;
}

export interface TrackingEvent {
  estado: EstadoPedido;
  label: string;
  descripcion: string;
  completado: boolean;
  en_curso: boolean;
  timestamp: string | null;
}

export interface CreateOrderPayload {
  restaurante_id: number;
  tipo_pedido: TipoPedido;
  items: {
    platillo_id: number;
    cantidad: number;
    modificadores: {
      modificador_id: number;
      opcion_ids: number[];
    }[];
    notas?: string;
    precio_unit: number;
  }[];
  direccion_id?: number;
  mesa_id?: number;
  notas?: string;
  payment_intent_id?: string;
}
