import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';
import { API_BASE_URL } from '../constants/api';
import { useUserStore } from '../store/user.store';

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000, // Aumentamos el tiempo de espera global a 30 segundos
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// Inyectar token en cada petición
apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = useUserStore.getState().token;
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Manejar 401 → logout automático
apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    if (error.response?.status === 401) {
      await useUserStore.getState().logout();
    }
    return Promise.reject(error);
  }
);

// Extrae el mensaje de error legible de las respuestas de la API
export function getApiError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { error?: string } | undefined;
    return data?.error ?? error.message;
  }
  return String(error);
}

/**
 * Convierte una ruta relativa de la base de datos en una URL absoluta para el móvil
 */
export function formatImageUrl(path?: string | null): string | undefined {
  if (!path) return undefined;
  if (path.startsWith('http')) return path;
  return `${API_BASE_URL}/${path.replace(/^\//, '')}`;
}
