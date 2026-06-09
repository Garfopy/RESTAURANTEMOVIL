import React, { createContext, useContext, useState, useCallback } from 'react';
import { View } from 'react-native';
import { Toast } from '../components/ui/Toast';

export interface ToastMessage {
  id: string;
  message: string;
  type: 'error' | 'warning' | 'success' | 'info';
  duration?: number;
  isDismissible?: boolean;
  icon?: string;
}

interface ToastContextType {
  show: (message: string, type: 'error' | 'warning' | 'success' | 'info', options?: Partial<ToastMessage>) => void;
  error: (message: string, options?: Partial<ToastMessage>) => void;
  warning: (message: string, options?: Partial<ToastMessage>) => void;
  success: (message: string, options?: Partial<ToastMessage>) => void;
  info: (message: string, options?: Partial<ToastMessage>) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

  const show = useCallback(
    (message: string, type: 'error' | 'warning' | 'success' | 'info', options?: Partial<ToastMessage>) => {
      const id = Date.now().toString();
      const newToast: ToastMessage = {
        id,
        message,
        type,
        duration: 3000,
        isDismissible: true,
        ...options,
      };
      setToasts((prev) => [...prev, newToast]);
    },
    []
  );

  const error = useCallback(
    (message: string, options?: Partial<ToastMessage>) => show(message, 'error', options),
    [show]
  );

  const warning = useCallback(
    (message: string, options?: Partial<ToastMessage>) => show(message, 'warning', options),
    [show]
  );

  const success = useCallback(
    (message: string, options?: Partial<ToastMessage>) => show(message, 'success', options),
    [show]
  );

  const info = useCallback(
    (message: string, options?: Partial<ToastMessage>) => show(message, 'info', options),
    [show]
  );

  const handleDismiss = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  return (
    <ToastContext.Provider value={{ show, error, warning, success, info }}>
      {children}
      <View pointerEvents="box-none" style={{ position: 'absolute', bottom: 0, left: 0, right: 0 }}>
        {toasts.map((toast) => (
          <Toast
            key={toast.id}
            message={toast.message}
            type={toast.type}
            duration={toast.duration}
            isDismissible={toast.isDismissible}
            icon={toast.icon}
            onDismiss={() => handleDismiss(toast.id)}
          />
        ))}
      </View>
    </ToastContext.Provider>
  );
}

export function useToast(): ToastContextType {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within ToastProvider');
  }
  return context;
}
