/**
 * Error Service - Mapea errores de API a mensajes amigables en español
 * Centraliza el manejo de todos los tipos de error en la app
 */

export interface ApiErrorResponse {
  status?: number;
  code?: string;
  message?: string;
  statusText?: string;
  response?: {
    status?: number;
    data?:
      | {
          detail?: string;
          message?: string;
          error?: string;
          errors?: Record<string, string>;
        }
      | string;
  };
}

export type ErrorType = 'credentials' | 'validation' | 'network' | 'server' | 'payment' | 'location' | 'unknown';

export interface FriendlyError {
  message: string;
  type: ErrorType;
  icon: string;
  isDismissible: boolean;
}

/**
 * Mapea errores de la API a mensajes amigables para el usuario
 * @param error - Error de la API o cualquier otro error
 * @returns Objeto con mensaje amigable, tipo y metadata
 */
export function mapErrorToFriendly(error: any): FriendlyError {
  const apiError = error as ApiErrorResponse;
  const responseData =
    apiError?.response?.data && typeof apiError.response.data === 'object' ? apiError.response.data : undefined;
  const status = apiError?.status || apiError?.response?.status;
  const message =
    responseData?.detail ||
    responseData?.message ||
    responseData?.error ||
    apiError?.message ||
    error?.message ||
    '';

  // Credenciales inválidas
  if (
    status === 401 ||
    message.toLowerCase().includes('invalid credentials') ||
    message.toLowerCase().includes('unauthorized') ||
    message.toLowerCase().includes('credenciales') ||
    message.toLowerCase().includes('contraseña incorrecta')
  ) {
    return {
      message: 'Email o contraseña incorrectos. Verifica tus datos e intenta de nuevo.',
      type: 'credentials',
      icon: 'lock-open',
      isDismissible: true,
    };
  }

  // Usuario no encontrado
  if (message.toLowerCase().includes('not found') || message.toLowerCase().includes('no existe')) {
    return {
      message: 'No encontramos tu cuenta. ¿Necesitas crear una nueva?',
      type: 'credentials',
      icon: 'person',
      isDismissible: true,
    };
  }

  // 409 — puede ser firewall o email duplicado. Mostramos el mensaje real del servidor.
  if (status === 409) {
    const rawData = apiError?.response?.data;
    // Si la respuesta es HTML (firewall), mostrar mensaje genérico
    if (typeof rawData === 'string' && rawData.includes('<script')) {
      return {
        message: 'Verificando seguridad... Intenta de nuevo.',
        type: 'validation',
        icon: 'shield',
        isDismissible: true,
      };
    }
    // Si tiene detail del servidor, mostrarlo directamente
    if (typeof rawData === 'object' && rawData !== null && (rawData as any).detail) {
      return {
        message: (rawData as any).detail,
        type: 'validation',
        icon: 'alert-circle',
        isDismissible: true,
      };
    }
    // Fallback: mensaje genérico
    return {
      message: message || 'Error al procesar la solicitud.',
      type: 'validation',
      icon: 'alert-circle',
      isDismissible: true,
    };
  }

  // Validación general
  if (status === 400) {
    return {
      message: 'Los datos ingresados no son válidos. Revisa que sean correctos.',
      type: 'validation',
      icon: 'alert-circle',
      isDismissible: true,
    };
  }

  // Errores de red
  if (
    message.toLowerCase().includes('network') ||
    message.toLowerCase().includes('timeout') ||
    message.toLowerCase().includes('econnrefused') ||
    message.toLowerCase().includes('no internet')
  ) {
    return {
      message: 'Parece que no tienes conexión. Verifica tu wifi o datos móviles.',
      type: 'network',
      icon: 'wifi-off',
      isDismissible: true,
    };
  }

  // Timeout
  if (message.toLowerCase().includes('timeout') || message.toLowerCase().includes('took too long')) {
    return {
      message: 'La conexión es muy lenta. Intenta de nuevo.',
      type: 'network',
      icon: 'hourglass',
      isDismissible: true,
    };
  }

  // Errores de servidor 5xx
  if (status && status >= 500) {
    return {
      message: 'Nuestros servidores están teniendo problemas. Intenta en unos momentos.',
      type: 'server',
      icon: 'server',
      isDismissible: true,
    };
  }

  // Errores de pago
  if (
    message.toLowerCase().includes('payment') ||
    message.toLowerCase().includes('card') ||
    message.toLowerCase().includes('stripe') ||
    message.toLowerCase().includes('pago')
  ) {
    if (message.toLowerCase().includes('declined')) {
      return {
        message: 'Tu tarjeta fue rechazada. Verifica los datos o intenta con otro medio de pago.',
        type: 'payment',
        icon: 'card',
        isDismissible: true,
      };
    }
    if (message.toLowerCase().includes('insufficient')) {
      return {
        message: 'Tu tarjeta tiene fondos insuficientes.',
        type: 'payment',
        icon: 'wallet',
        isDismissible: true,
      };
    }
    return {
      message: 'Hubo un problema procesando el pago. Intenta de nuevo.',
      type: 'payment',
      icon: 'alert-circle',
      isDismissible: true,
    };
  }

  // Errores de ubicación
  if (
    message.toLowerCase().includes('location') ||
    message.toLowerCase().includes('gps') ||
    message.toLowerCase().includes('permission') ||
    message.toLowerCase().includes('ubicación')
  ) {
    if (message.toLowerCase().includes('permission')) {
      return {
        message: 'Necesitamos permiso para acceder a tu ubicación. Actívalo en configuración.',
        type: 'location',
        icon: 'location',
        isDismissible: true,
      };
    }
    return {
      message: 'No pudimos detectar tu ubicación. Escríbela manualmente.',
      type: 'location',
      icon: 'locate',
      isDismissible: true,
    };
  }

  // Error por defecto (desconocido)
  return {
    message: 'Algo salió mal. Por favor intenta de nuevo o contacta a soporte.',
    type: 'unknown',
    icon: 'alert-circle',
    isDismissible: true,
  };
}

/**
 * Valida un email
 */
export function validateEmail(email: string): string | null {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!email.trim()) {
    return 'Email es requerido';
  }
  if (!emailRegex.test(email)) {
    return 'Email no es válido';
  }
  return null;
}

/**
 * Valida contraseña
 */
export function validateOptionalEmail(email: string): string | null {
  if (!email.trim()) {
    return null;
  }
  return validateEmail(email);
}

export function validatePhone(phone: string): string | null {
  const digits = phone.replace(/\D/g, '');
  if (!digits) {
    return 'Teléfono es requerido';
  }
  if (digits.length < 10 || digits.length > 15) {
    return 'Teléfono debe tener entre 10 y 15 dígitos';
  }
  return null;
}

export function validateLoginIdentifier(value: string): string | null {
  const clean = value.trim();
  if (!clean) {
    return 'Correo o teléfono es requerido';
  }
  if (clean.includes('@')) {
    return validateEmail(clean);
  }
  return validatePhone(clean);
}

export function validatePassword(password: string, minLength: number = 8): string | null {
  if (!password) {
    return 'Contraseña es requerida';
  }
  if (password.length < minLength) {
    return `Contraseña debe tener al menos ${minLength} caracteres`;
  }
  return null;
}

/**
 * Valida nombre
 */
export function validateName(name: string): string | null {
  if (!name.trim()) {
    return 'Nombre es requerido';
  }
  if (name.trim().length < 3) {
    return 'Nombre debe tener al menos 3 caracteres';
  }
  return null;
}

/**
 * Valida dirección
 */
export function validateAddress(address: string): string | null {
  if (!address.trim()) {
    return 'Dirección es requerida';
  }
  if (address.trim().length < 10) {
    return 'Dirección muy corta. Incluye calle, número y localidad';
  }
  return null;
}
