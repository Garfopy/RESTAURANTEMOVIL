export interface MobileUser {
  id: number;
  nombre: string;
  email: string;
  rol?: 'user' | 'admin' | 'mesero' | string | null;
  telefono: string | null;
  fecha_nacimiento?: string | null;
  onboarding_completed_at?: string | null;
  terms_accepted_at?: string | null;
  marketing_opt_in?: boolean | number | null;
  requires_onboarding?: boolean | null;
  foto_url: string | null;
  social_photos?: string[];
  google_id: string | null;
  activo: boolean;
  created_at: string;
  // Social profile fields (frontend names)
  edad?: number | null;
  genero?: string | null;
  sexualidad?: string | null;
  gustos?: string | null; // -> columna: intereses
  biografia?: string | null; // -> columna: descripcion
  que_busca?: string | null;
  redes_sociales?: string | null;
  instagram?: string | null; // -> columna: redes_sociales (JSON o texto)
  tiktok?: string | null; // -> columna: redes_sociales (JSON)
  is_social_active?: boolean; // -> columna: is_social_active
  modo_social?: boolean; // -> alias de is_social_active
  current_restaurante_id?: number | null;
  mesa?: string | null;
  social_consent_accepted_at?: string | null;
  social_consent_version?: string | null;
  requires_social_consent?: boolean | null;
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
  email?: string | null;
  password: string;
  telefono: string;
}
