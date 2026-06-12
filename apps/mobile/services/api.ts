import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';
import { API_BASE_URL } from '../constants/api';
import { useUserStore } from '../store/user.store';

// Ensure URL ends with /
const normalizedBaseURL = API_BASE_URL.endsWith('/') ? API_BASE_URL : API_BASE_URL + '/';

export const apiClient = axios.create({
  baseURL: normalizedBaseURL,
  timeout: 15000, // Reducimos a 15s para fallar rápido y reintentar
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// Inyectar token en cada petición
apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = useUserStore.getState().token;
  // Eliminar slash inicial del url si existe para evitar doble slash con baseURL
  if (config.url?.startsWith('/')) config.url = config.url.substring(1);
  
  console.log(`[API] Request: ${config.method?.toUpperCase()} ${config.baseURL}${config.url}`);
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Manejar 401 → logout automático
apiClient.interceptors.response.use(
  (response) => {
    // Transformar strings numéricos a numbers (Fix para PHP PDO)
    if (response.data && typeof response.data === 'object') {
      response.data = transformStringsToNumbers(response.data);
    }
    return response;
  },
  async (error: AxiosError) => {
    if (error.response?.status === 401) {
      await useUserStore.getState().logout();
    }
    console.error(`[API] Error en ${error.config?.url}:`, error.message);
    return Promise.reject(error);
  }
);

/** Función auxiliar para convertir strings que son números (ej. "125.50") en Numbers */
function transformStringsToNumbers(obj: any): any {
  if (Array.isArray(obj)) return obj.map(transformStringsToNumbers);
  if (obj !== null && typeof obj === 'object') {
    return Object.keys(obj).reduce((acc: any, key) => {
      const value = obj[key];
      if (typeof value === 'string' && /^-?\d+\.?\d*$/.test(value) && !isNaN(parseFloat(value))) {
        acc[key] = parseFloat(value);
      } else {
        acc[key] = transformStringsToNumbers(value);
      }
      return acc;
    }, {});
  }
  return obj;
}

/** Extrae el mensaje de error legible de las respuestas de la PHP API */
export function getApiError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string; error?: string } | undefined;
    return data?.message ?? data?.error ?? error.message;
  }
  return String(error);
}

/**
 * Convierte una ruta relativa de la base de datos en una URL absoluta para el móvil
 * La nueva PHP API guarda imágenes en 'uploads/imagen.jpg'
 */
export function formatImageUrl(path?: string | null): string | undefined {
  if (!path) return undefined;
  if (path.startsWith('http')) return path;
  
  // Limpiamos 'public/' por si el backend aún no ha corrido la migración 007 de base de datos
  const cleanPath = path.replace(/^public\//, '').replace(/^\//, '');
  return `${normalizedBaseURL}${cleanPath}`;
}