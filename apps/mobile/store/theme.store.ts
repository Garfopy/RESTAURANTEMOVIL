import AsyncStorage from '@react-native-async-storage/async-storage';
import { create } from 'zustand';
import {
  applyThemeColors,
  Colors,
  normalizeHex,
  ThemeColors,
  ThemeColorSettings,
} from '../theme/colors';
import { getThemeSettings } from '../services/theme.service';

const STORAGE_KEY = 'amare.theme.colors.v1';

interface ThemeState {
  colors: ThemeColors;
  isLoaded: boolean;
  hydrateTheme: () => Promise<void>;
}

export const useThemeStore = create<ThemeState>((set) => ({
  colors: { ...Colors },
  isLoaded: false,

  hydrateTheme: async () => {
    try {
      const cached = await AsyncStorage.getItem(STORAGE_KEY);
      if (cached) {
        const settings = JSON.parse(cached) as Partial<ThemeColorSettings>;
        const colors = applyThemeColors(settings);
        set({ colors });
      }
    } catch (error) {
      console.warn('No se pudo restaurar el tema guardado:', error);
    }

    set({ isLoaded: true });

    void (async () => {
      try {
        const remote = await getThemeSettings();
        const nextSettings: ThemeColorSettings = {
          primary: normalizeHex(remote.primary) ?? Colors.primary,
          secondary: normalizeHex(remote.secondary) ?? Colors.accent,
          background: normalizeHex(remote.background) ?? Colors.background,
          button: normalizeHex(remote.button) ?? Colors.button,
          buttonText: normalizeHex(remote.buttonText) ?? Colors.buttonText,
        };

        const colors = applyThemeColors(nextSettings);
        set({ colors });
        await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(nextSettings));
      } catch (error) {
        console.warn('No se pudo actualizar el tema remoto:', error);
      }
    })();
  },
}));

export function useThemeColors() {
  return useThemeStore((state) => state.colors);
}
