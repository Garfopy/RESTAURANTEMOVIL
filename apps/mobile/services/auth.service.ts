import { apiClient } from './api';
import type {
  Sesion,
  MobileUser,
  GoogleLoginPayload,
  EmailLoginPayload,
  RegisterPayload,
} from '@amare/types';

/** Parsea la respuesta del PHP API que devuelve { success, data: { user, token } } */
function parseSesion(data: { user: MobileUser; token: string }): Sesion {
  return {
    token: data.token,
    user: data.user,
    expires_at: '', // El PHP API no envía expires_at, se maneja con refresh implícito
  };
}

export async function loginWithGoogle(payload: GoogleLoginPayload): Promise<Sesion> {
  // La PHP API espera `token` en lugar de `id_token`
  const { data } = await apiClient.post<{ success: boolean; data: { user: MobileUser; token: string } }>(
    '/auth/google',
    { token: payload.id_token }
  );
  return parseSesion(data.data);
}

export async function loginWithEmail(payload: EmailLoginPayload): Promise<Sesion> {
  const { data } = await apiClient.post<{ success: boolean; data: { user: MobileUser; token: string } }>(
    '/auth/login',
    { email: payload.email, password: payload.password }
  );
  return parseSesion(data.data);
}

export async function register(payload: RegisterPayload): Promise<Sesion> {
  // La PHP API espera `name`, `phone` en vez de `nombre`, `telefono`
  const { data } = await apiClient.post<{ success: boolean; data: { user: MobileUser; token: string } }>(
    '/auth/register',
    {
      name: payload.nombre,
      email: payload.email,
      password: payload.password,
      phone: payload.telefono,
    }
  );
  return parseSesion(data.data);
}

export async function getMe(): Promise<MobileUser> {
  const { data } = await apiClient.get<{ success: boolean; data: { user: MobileUser } }>('/auth/me');
  return data.data.user;
}

/**
 * La PHP API no tiene endpoint de logout.
 * Se elimina el token del lado del cliente únicamente.
 */
export async function logout(): Promise<void> {
  // No hay endpoint /auth/logout en la PHP API
  // El cliente limpia el token localmente en el store
}