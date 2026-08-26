import { create } from 'zustand';
import { Colors, ThemeColors } from '../theme/colors';

// Los colores de UTEQ Cafetería son fijos (ver theme/colors.ts) y ya no se
// sincronizan con /settings/theme del panel admin. hydrateTheme() se deja
// como no-op para no tener que tocar los ~12 componentes que ya consumen
// useThemeColors().
interface ThemeState {
  colors: ThemeColors;
  isLoaded: boolean;
  hydrateTheme: () => Promise<void>;
}

export const useThemeStore = create<ThemeState>((set) => ({
  colors: { ...Colors },
  isLoaded: false,

  hydrateTheme: async () => {
    set({ isLoaded: true });
  },
}));

export function useThemeColors() {
  return useThemeStore((state) => state.colors);
}
