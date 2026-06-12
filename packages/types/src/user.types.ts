export interface MobileUser {
  id: number;
  nombre: string;
  email: string;
  telefono: string | null;
  foto_url: string | null;
  google_id: string | null;
  activo: boolean;
  created_at: string;
}

export interface Sesion {
  token: string;
  user: MobileUser;
  expires_at: string;
}

export interface Direccion {
  id: number;
  usuario_id: number;
  alias: string;
  calle: string;
  numero: string | null;
  colonia: string | null;
  ciudad: string;
  estado_provincia: string | null;
  cp: string | null;
  lat: number | null;
  lng: number | null;
  instrucciones: string | null;
  es_principal: boolean;
  activo: boolean;
}

export interface GoogleLoginPayload {
  id_token: string;
  device_info?: string;
  platform?: 'ios' | 'android';
}

export interface EmailLoginPayload {
  email: string;
  password: string;
  device_info?: string;
  platform?: 'ios' | 'android';
}

export interface RegisterPayload {
  nombre: string;
  email: string;
  password: string;
  telefono?: string;
}
