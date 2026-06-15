import type { TipoEntregaHabilitado } from './config.types';

export interface Sucursal {
  id: number;
  nombre: string;
  slug: string;
  descripcion: string | null;
  direccion: string;
  telefono: string | null;
  logo: string | null;
  imagen_banner: string | null;
  lat: number | null;
  lng: number | null;
  color_primario: string;
  color_secundario: string;
  horario_apertura: string | null;
  horario_cierre: string | null;
  horarios_json: string | null;
  mesas_habilitadas: boolean;
  reservas_habilitadas: boolean;
  activo: boolean;
  tipos_entrega: TipoEntregaHabilitado[];
  /** Calculado por el API según coordenadas del usuario (km) */
  distancia_km?: number;
}

export interface Coordenadas {
  lat: number;
  lng: number;
}
