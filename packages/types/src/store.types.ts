export interface StoreCategory {
  id: number;
  nombre: string;
  descripcion: string | null;
  imagen: string | null;
  activo: number;
  created_at: string;
}

export type TipoProductoStore = 'fisico' | 'comida';

export interface StoreProduct {
  id: number;
  categoria_id: number;
  categoria_nombre: string;
  nombre: string;
  descripcion: string | null;
  tipo_producto: TipoProductoStore;
  presentacion: string | null;
  precio: number;
  imagen: string | null;
  stock: number;
  activo: number;
  created_at: string;
}
