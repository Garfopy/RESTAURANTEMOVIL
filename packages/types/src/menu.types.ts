export interface Categoria {
  id: number;
  nombre: string;
  slug: string | null;
  imagen: string | null;
  orden: number;
  activo: boolean;
  total_platillos?: number;
}

export interface Modificador {
  id: number;
  nombre: string;
  tipo: 'radio' | 'checkbox';
  requerido: boolean;
  min_selecciones: number;
  max_selecciones: number;
  opciones: OpcionModificador[];
}

export interface OpcionModificador {
  id: number;
  modificador_id: number;
  nombre: string;
  precio_extra: number;
  activo: boolean;
}

export interface Platillo {
  id: number;
  restaurante_id: number;
  categoria_id: number;
  categoria_nombre: string;
  nombre: string;
  descripcion: string | null;
  presentacion: string | null;
  precio: number;
  imagen: string | null;
  tiempo_preparacion_min: number;
  disponible: boolean;
  activo: boolean;
  tiene_receta: boolean;
  modificadores?: Modificador[];
  /** Calculado del historial de pedidos */
  rating?: number;
  total_pedidos?: number;
}

export interface CarritoItem {
  id: string;
  platillo: Platillo;
  cantidad: number;
  modificadores_seleccionados: ModificadorSeleccionado[];
  notas: string;
  precio_unitario: number;
  subtotal: number;
}

export interface ModificadorSeleccionado {
  modificador_id: number;
  modificador_nombre: string;
  opciones: {
    opcion_id: number;
    opcion_nombre: string;
    precio_extra: number;
  }[];
}
