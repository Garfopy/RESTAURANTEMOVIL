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
  pedido_id?: number;
  pedido_folio?: string | null;
  platillo_id: number;
  origen?: 'menu' | 'store';
  platillo_nombre: string;
  platillo_imagen: string | null;
  cantidad: number;
  precio_unit: number;
  subtotal: number;
  notas: string | null;
  extras_json: string | null;
  exclusiones: string | null;
  extras: string | null;
  estado: EstadoPedido;
}

export type TipoOrigenPedido = 'menu' | 'store' | 'mixto';

export interface Pedido {
  id: number;
  restaurante_id: number;
  restaurante_nombre: string;
  folio: string;
  tipo_pedido: TipoPedido;
  tipo_origen: TipoOrigenPedido;
  estado: EstadoPedido;
  subtotal: number;
  descuento: number;
  envio: number;
  total: number;
  mesa_id: number | null;
  mesa_nombre: string | null;
  direccion_id: number | null;
  direccion_entrega: string | null;
  notas: string | null;
  items: PedidoItem[];
  created_at: string;
  updated_at: string;
  cuenta_abierta?: boolean | number;
  consumo_id?: string | null;
  es_consumo?: boolean;
  pedidos_count?: number;
  salida_qr_generado_at?: string | null;
  salida_validado_at?: string | null;
}

export interface ExitPass {
  pedido_id: number;
  folio: string | null;
  restaurante_id: number | null;
  mesa_id: number | null;
  payload: string;
  token: string;
  generated_at: string | null;
  validated_at: string | null;
  is_validated: boolean;
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
      modificador_nombre: string;
      opciones: {
        opcion_id: number;
        opcion_nombre: string;
        precio_extra: number;
        cantidad?: number;
        tipo_modificador?: 'exclusion' | 'extra';
      }[];
    }[];
    notas?: string;
    precio_unit: number;
  }[];
  direccion_id?: number;
  direccion_entrega?: string;
  mesa_id?: number;
  consumo_por_mesa?: boolean;
  notas?: string;
  payment_intent_id?: string;
}
