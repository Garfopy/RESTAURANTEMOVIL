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
  google_id: string | null;
  apple_id?: string | null;
  activo: boolean;
  created_at: string;
  // is_social_active/modo_social ya no activan el modo social (Sprint 2), pero
  // se conservan porque el flujo de mesa (Sprint 3, pendiente) los sigue usando
  // para limpiar la sesión al salir de una mesa.
  is_social_active?: boolean;
  modo_social?: boolean;
  current_restaurante_id?: number | null;
  mesa?: string | null;
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
  fecha_nacimiento: string;
}

export interface AppleLoginPayload {
  identity_token: string;
  authorization_code?: string | null;
  full_name?: string | null;
  platform?: 'ios';
}
