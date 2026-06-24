import { Platform } from 'react-native';

export const Shadows = {
  sm: Platform.select({
    ios:     { shadowColor: '#000', shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.06, shadowRadius: 2 },
    android: { elevation: 2 },
    default: {},
  }),
  md: Platform.select({
    ios:     { shadowColor: '#000', shadowOffset: { width: 0, height: 3 }, shadowOpacity: 0.10, shadowRadius: 6 },
    android: { elevation: 4 },
    default: {},
  }),
  lg: Platform.select({
    ios:     { shadowColor: '#000', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.12, shadowRadius: 12 },
    android: { elevation: 8 },
    default: {},
  }),
  card: Platform.select({
    ios:     { shadowColor: '#1A1A2E', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.08, shadowRadius: 8 },
    android: { elevation: 3 },
    default: {},
  }),
} as const;
